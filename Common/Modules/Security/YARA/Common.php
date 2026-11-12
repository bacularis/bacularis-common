<?php
/*
 * Bacularis - Bacula web interface
 *
 * Copyright (C) 2021-2026 Marcin Haba
 *
 * The main author of Bacularis is Marcin Haba, with contributors, whose
 * full list can be found in the AUTHORS file.
 *
 * You may use this file and others of this release according to the
 * license defined in the LICENSE file, which includes the Affero General
 * Public License, v3.0 ("AGPLv3") and some additional permissions and
 * terms pursuant to its AGPLv3 Section 7.
 */

namespace Bacularis\Common\Modules\Security\YARA;

use Bacularis\Common\Modules\CommonModule;
use Bacularis\Common\Modules\Errors\GenericError;
use Bacularis\Common\Modules\ExecuteCommand;
use Bacularis\Common\Modules\Miscellaneous;
use DirectoryIterator;
use InvalidArgumentException;
use RuntimeException;

/**
 * Shared YARA command helpers.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class Common extends CommonModule
{
	/**
	 * Classic YARA engine type.
	 */
	public const TYPE_YARA = 'yara';

	/**
	 * YARA-X engine type.
	 */
	public const TYPE_YARAX = 'yarax';

	/**
	 * YARA rule file extensions.
	 */
	public const RULE_FILE_EXTENSIONS = ['yar', 'yara'];

	/**
	 * Default single scan command timeout (in seconds).
	 */
	public const DEFAULT_TIMEOUT = 300;

	/**
	 * Default output limit for scan command (10 MiB).
	 */
	public const DEFAULT_OUTPUT_LIMIT = 16777216;

	/**
	 * Default timeout for version command (in seconds).
	 */
	public const DEFAULT_VERSION_TIMEOUT = 5;

	/**
	 * Default output limit for version command  (64 KiB)
	 */
	public const DEFAULT_VERSION_OUTPUT_LIMIT = 65536;

	/**
	 * Select an engine explicitly, without a fallback.
	 *
	 * @param string $type engine identifier
	 * @throws InvalidArgumentException for unsupported engines
	 * @return string engine class
	 */
	public static function getEngineClass(string $type): string
	{
		switch ($type) {
			case self::TYPE_YARA:
				return Yara::class;
			case self::TYPE_YARAX:
				return YaraX::class;
			default:
				throw new InvalidArgumentException('Unsupported YARA engine: ' . $type);
		}
	}

	/**
	 * Create the common result contract for scans, versions and validation errors.
	 *
	 * - error is zero only when execution and parsing succeed. File_error, warning
	 *   and runtime_warning diagnostics alone do not set it. Nonzero application
	 *   exits/signals use ERROR_WRONG_EXITCODE. runner errors retain their original
	 *   code, parsing errors use ERROR_INTERNAL_ERROR and invalid inputs use ERROR_INVALID_COMMAND.
	 * - timed_out/output_limited retain the runner flags.
	 * - exitcode and signal retain the actual nullable child status.
	 * - stdout/stderr are unmodified captured bytes.
	 * - match_count is null until a complete successful scan is parsed, including
	 *   when partial matches preceded failure.
	 * - Each matches entry contains path, path_encoding (literal or yara-text),
	 *   namespace and identifier. Classic yara-text paths must not be unescaped
	 *   or deduplicated: newline escaping is ambiguous with literal backslashes.
	 * - diagnostics entries contain type (file_error, warning, runtime_warning,
	 *   execution_error), nullable path and message. Recognized scanner errors may
	 *   also contain a summary: the error header and optional location, without source excerpts.
	 *   It remains unescaped; consumers must quote it before displaying it.
	 *   Classic runtime warnings include rule and results_may_be_incorrect fields.
	 *   Such warnings do not make the reported match count a completeness guarantee.
	 * - version is set only by getVersion(). Scan results do not query a version.
	 *
	 * @param string $type engine identifier
	 * @return array identical normalized structure for both operations and engines
	 */
	protected static function newResult(string $type): array
	{
		return [
			'engine' => $type,
			'error' => GenericError::ERROR_NO_ERRORS,
			'error_message' => '',
			'exitcode' => null,
			'signal' => null,
			'timed_out' => false,
			'output_limited' => false,
			'stdout' => '',
			'stderr' => '',
			'match_count' => null,
			'matches' => [],
			'diagnostics' => [],
			'version' => null
		];
	}

	/**
	 * Mark failure without exposing a partial match count as a completed scan.
	 *
	 * @param array $result current normalized result
	 * @param int $error error code
	 * @param string $message failure explanation without matched content
	 * @return array failed result
	 */
	protected static function failResult(array $result, int $error, string $message): array
	{
		$result['error'] = $error;
		$result['error_message'] = $message;
		$result['match_count'] = null;
		$result['matches'] = [];
		$result['version'] = null;
		$result['diagnostics'][] = [
			'type' => 'execution_error',
			'path' => null,
			'message' => $message
		];
		return $result;
	}

	/**
	 * Resolve a directory containing an executable, or leave lookup to PATH.
	 *
	 * @param string $bin_path binary directory, empty for PATH
	 * @param string $binary fixed executable name
	 * @return string literal executable argument
	 */
	protected static function getBinaryPath(string $bin_path, string $binary): string
	{
		if ($bin_path === '') {
			return $binary;
		}
		$directory = Miscellaneous::getRealPath($bin_path);
		$path = $directory . DIRECTORY_SEPARATOR . $binary;
		if (!is_dir($directory) || !is_file($path) || !is_executable($path)) {
			throw new InvalidArgumentException('Selected scanner executable is unavailable.');
		}
		return $path;
	}

	/**
	 * Select one readable file or immediate .yar/.yara files in a source directory.
	 * Only rule inputs are enumerated. No target paths are enumerated here.
	 *
	 * @param string $rules_path source file/directory or compiled file
	 * @param bool $compiled_rules require exactly one compiled file
	 * @return array sorted literal absolute rule paths
	 */
	protected static function getRuleFiles(string $rules_path, bool $compiled_rules): array
	{
		$path = Miscellaneous::getRealPath($rules_path);
		if (is_file($path) && is_readable($path)) {
			return [$path];
		}
		if ($compiled_rules || !is_dir($path) || !is_readable($path) || !is_executable($path)) {
			throw new InvalidArgumentException(
				'Rules must be a readable file or a readable source-rules directory.'
			);
		}
		$files = [];
		$iterator = new DirectoryIterator($path);
		foreach ($iterator as $entry) {
			$name = $entry->getFilename();
			$extension = pathinfo($name, PATHINFO_EXTENSION);
			if (!in_array($extension, self::RULE_FILE_EXTENSIONS, true) || !$entry->isFile()) {
				continue;
			}
			$file = $entry->getPathname();
			if (!is_readable($file)) {
				throw new InvalidArgumentException('Source rule file is unreadable: ' . $file);
			}
			$files[] = $file;
		}
		if (!$files) {
			throw new InvalidArgumentException(
				'Source-rules directory contains no immediate .yar or .yara files.'
			);
		}
		sort($files, SORT_STRING);
		return $files;
	}

	/**
	 * Accept files/directories and resolve an absolute filesystem target.
	 * Classic YARA interprets a numeric target operand as a process ID. Resolving
	 * even a numeric filename (for example, 12345) to an absolute path prevents
	 * accidentally scanning a running process instead of the restored file.
	 * Direct symlink targets are rejected; descendants are handled by the engine.
	 *
	 * @param string $target_path restored file or directory
	 * @return string absolute target path
	 */
	protected static function getTargetPath(string $target_path): string
	{
		$path = Miscellaneous::getRealPath($target_path);
		$link_path = rtrim($target_path, DIRECTORY_SEPARATOR);
		if (is_link($link_path) || (!is_file($path) && !is_dir($path))) {
			throw new InvalidArgumentException(
				'Target must be a regular file or directory, not a symbolic link.'
			);
		}
		if (is_dir($path) && (!is_readable($path) || !is_executable($path))) {
			throw new InvalidArgumentException(
				'Target directory cannot be read or searched.'
			);
		}
		return $path;
	}

	/**
	 * Validate an explicitly supplied YARA-X configuration file.
	 *
	 * @param string $config_path optional local configuration
	 * @return string absolute file, or empty to preserve native configuration lookup
	 */
	protected static function getConfigPath(string $config_path): string
	{
		if ($config_path === '') {
			return '';
		}
		$path = Miscellaneous::getRealPath($config_path);
		if (!is_file($path) || !is_readable($path)) {
			throw new InvalidArgumentException(
				'YARA-X configuration must be a readable regular file.'
			);
		}
		return $path;
	}

	/**
	 * Execute argv with the existing runner and preserve its raw output/status.
	 *
	 * @param string $type engine identifier
	 * @param array $command literal argv
	 * @param int $timeout complete process budget
	 * @param int $max_output_bytes combined output capture limit
	 * @return array normalized execution result, with no successful match count yet
	 */
	protected static function execute(string $type, array $command, int $timeout, int $max_output_bytes): array
	{
		$result = self::newResult($type);
		$execution = ExecuteCommand::execProcess(
			$command,
			$timeout,
			['LC_ALL' => 'C'],
			$max_output_bytes
		);
		$result = array_replace($result, $execution);
		if ($result['error'] !== 0) {
			$message = $result['error_message'];
			$error = $result['error'];
			return self::failResult($result, $error, $message);
		}
		if ($result['signal'] !== null || $result['exitcode'] !== 0) {
			return self::failResult(
				$result,
				GenericError::ERROR_WRONG_EXITCODE,
				'Scanner exited unsuccessfully or was terminated by a signal.'
			);
		}
		return $result;
	}

	/**
	 * Split LF-delimited scanner records without trimming filename bytes.
	 *
	 * @param string $stdout raw scanner output
	 * @return array complete records
	 */
	protected static function getOutputLines(string $stdout): array
	{
		if ($stdout === '') {
			return [];
		}
		if (substr($stdout, -1) !== "\n") {
			throw new RuntimeException(
				'Scanner output is incomplete: missing final newline.'
			);
		}
		$body = substr($stdout, 0, -1);
		return explode("\n", $body);
	}

	/**
	 * Preserve file errors containing literal newlines and indented warning details.
	 * Only known warning layout markers are continuations. A fresh diagnostic
	 * prefix cannot be part of a multiline filename: ambiguous text fails
	 * conservatively instead of hiding an error inside another message.
	 *
	 * @param string $stderr raw scanner diagnostics
	 * @param string $file_error_pattern engine regex anchored at the current offset with \G
	 * @return array diagnostic messages, including multiline compiler warnings
	 */
	protected static function getDiagnosticMessages(string $stderr, string $file_error_pattern): array
	{
		$messages = [];
		$warning = false;
		$offset = 0;
		$length = strlen($stderr);
		while ($offset < $length) {
			if (preg_match($file_error_pattern, $stderr, $record, 0, $offset) === 1 && preg_match('/\n[ \t]*(?:error(?::| scanning )|warning(?::|\[)|fatal:)/', $record[0]) !== 1) {
				$messages[] = $record[0];
				$offset += strlen($record[0]);
				if ($offset < $length && $stderr[$offset] === "\n") {
					$offset++;
				}
				$warning = false;
				continue;
			}
			$end = strpos($stderr, "\n", $offset);
			if ($end === false) {
				$end = $length;
			}
			$line = substr($stderr, $offset, $end - $offset);
			$offset = $end + 1;
			if ($line === '') {
				continue;
			}
			if ($warning && preg_match('/\A(?:[ \t]*(?:-->|\||= (?:note|help):)|[ \t]*[0-9]+[ \t]*\|)/', $line) === 1) {
				$last = count($messages) - 1;
				$messages[$last] .= "\n" . $line;
				continue;
			}
			$messages[] = $line;
			$warning = preg_match('/\Awarning\[[A-Za-z0-9_]+\]:/', $line) === 1;
		}
		return $messages;
	}

	/**
	 * Retain diagnostic severity independently of match counts and thresholds.
	 *
	 * @param array $result normalized result reference
	 * @param string $type file_error, warning, runtime_warning or execution_error
	 * @param string $message scanner diagnostic
	 * @param null|string $path reliably parsed filesystem path, otherwise null
	 * @param string $summary optional scanner error header without source excerpts
	 */
	protected static function addDiagnostic(array &$result, string $type, string $message, ?string $path = null, string $summary = ''): void
	{
		$diagnostic = ['type' => $type, 'path' => $path, 'message' => $message];
		if ($summary !== '') {
			$diagnostic['summary'] = $summary;
		}
		$result['diagnostics'][] = $diagnostic;
		if ($type === 'execution_error' && $result['error'] === 0) {
			$result['error'] = GenericError::ERROR_INTERNAL_ERROR;
			$result['error_message'] = 'Scanner reported an unrecognized or fatal diagnostic.';
		}
	}

	/**
	 * Validate version output without changing the scan-only match fields.
	 *
	 * @param array $result normalized result
	 * @param string $pattern engine-specific regex with a named version group
	 * @return array version result
	 */
	protected static function parseVersion(array $result, string $pattern): array
	{
		if ($result['error'] !== 0) {
			return $result;
		}
		$output = trim($result['stdout']);
		if (preg_match($pattern, $output, $matches) !== 1) {
			return self::failResult(
				$result,
				GenericError::ERROR_INTERNAL_ERROR,
				'Invalid scanner version output.'
			);
		}
		$result['version'] = $matches['version'];
		return $result;
	}
}
