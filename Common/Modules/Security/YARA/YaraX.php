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

use Bacularis\Common\Modules\Errors\GenericError;
use InvalidArgumentException;
use RuntimeException;
use stdClass;
use Throwable;

/**
 * YARA-X CLI adapter with strict NDJSON record validation.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class YaraX extends Common
{
	/**
	 * The display path can contain literal newlines.
	 * Only known filesystem I/O errors qualify.
	 */
	private const FILE_ERROR_PATTERN = '~\Gerror: (?:scanning .+?: can not (?:open|read|mmap) .+?|IO error for operation on .+?): [^\r\n]+ \(os error [0-9]+\)(?=\n|\z)~s';

	/**
	 * Diagnostic pattern.
	 */
	private const COMPILER_DIAGNOSTIC_PATTERN = '/\A[ \t]*(?<severity>warning|error)\[[A-Za-z0-9_-]+\]: [^\r\n]+/';

	/**
	 * Continuation pattern.
	 */
	private const COMPILER_CONTINUATION_PATTERN = '/\A(?:[ \t]*(?:-->|:::|\||(?:= )?(?:note|help):[ \t]+[^\r\n]+)|[ \t]*[0-9]+[ \t]+[|+-]|[ \t]*\.{3}[ \t]*\z)/';

	/**
	 * Scan one file or a directory using the scanner's native recursive traversal.
	 * YARA-X 1.22.1 does not follow descendant symlinks by default.
	 *
	 * @param string $bin_path directory containing yr, empty for PATH
	 * @param string $rules_path source file/directory or one compiled file
	 * @param string $target_path regular file or directory
	 * @param bool $compiled_rules enable scan --compiled-rules
	 * @param int $timeout complete process budget in seconds
	 * @param string $config_path optional configuration, empty preserves native lookup
	 * @param int $max_output_bytes combined stdout/stderr capture limit
	 * @return array normalized Common::newResult() structure
	 */
	public static function runScan(string $bin_path, string $rules_path, string $target_path, bool $compiled_rules = false, int $timeout = self::DEFAULT_TIMEOUT, string $config_path = '', int $max_output_bytes = self::DEFAULT_OUTPUT_LIMIT): array
	{
		$result = self::newResult(self::TYPE_YARAX);
		try {
			$binary = self::getBinaryPath($bin_path, 'yr');
			$config = self::getConfigPath($config_path);
			$rules = self::getRuleFiles($rules_path, $compiled_rules);
			foreach ($rules as $rule) {
				if (strpos($rule, ':') !== false) {
					throw new InvalidArgumentException(
						'YARA-X cannot represent colon-containing rule paths without changing namespace interpretation.'
					);
				}
			}
			$target = self::getTargetPath($target_path);
			$command = [$binary];
			if ($config !== '') {
				array_push($command, '--config', $config);
			}
			array_push(
				$command,
				'scan',
				'--output-format=ndjson',
				'--print-namespace',
				'--disable-console-logs'
			);
			if ($compiled_rules) {
				$command[] = '--compiled-rules';
			}
			if (is_dir($target)) {
				$command[] = '--recursive';
			}
			$command = array_merge($command, $rules, [$target]);
			$result = self::execute(self::TYPE_YARAX, $command, $timeout, $max_output_bytes);
			self::parseDiagnostics($result);
			if ($result['error'] === 0) {
				$matches = self::parseMatches($result['stdout']);
				$result['matches'] = $matches;
				$result['match_count'] = count($matches);
			}
		} catch (InvalidArgumentException $exception) {
			$message = $exception->getMessage();
			$result = self::failResult(
				$result,
				GenericError::ERROR_INVALID_COMMAND,
				$message
			);
		} catch (Throwable $exception) {
			$message = $exception->getMessage();
			$result = self::failResult(
				$result,
				GenericError::ERROR_INTERNAL_ERROR,
				$message
			);
		}
		return $result;
	}

	/**
	 * Get the version using the same optional global configuration argument.
	 *
	 * @param string $bin_path binary directory, empty for PATH
	 * @param int $timeout process timeout in seconds
	 * @param string $config_path optional readable configuration
	 * @param int $max_output_bytes combined capture limit
	 * @return array normalized version result
	 */
	public static function getVersion(string $bin_path = '', int $timeout = self::DEFAULT_VERSION_TIMEOUT, string $config_path = '', int $max_output_bytes = self::DEFAULT_VERSION_OUTPUT_LIMIT): array
	{
		$result = self::newResult(self::TYPE_YARAX);
		try {
			$binary = self::getBinaryPath($bin_path, 'yr');
			$config = self::getConfigPath($config_path);
			$command = [$binary];
			if ($config !== '') {
				array_push($command, '--config', $config);
			}
			$command[] = '--version';
			$result = self::execute(self::TYPE_YARAX, $command, $timeout, $max_output_bytes);
			self::parseDiagnostics($result);
			$result = self::parseVersion(
				$result,
				'/\Ayara-x-cli (?<version>[0-9]+\.[0-9]+\.[0-9]+(?:[-+][A-Za-z0-9._+-]+)?)\z/D'
			);
		} catch (InvalidArgumentException $exception) {
			$message = $exception->getMessage();
			$result = self::failResult($result, GenericError::ERROR_INVALID_COMMAND, $message);
		} catch (Throwable $exception) {
			$message = $exception->getMessage();
			$result = self::failResult($result, GenericError::ERROR_INTERNAL_ERROR, $message);
		}
		return $result;
	}

	/**
	 * Validate every object and flatten each matching rule into one record.
	 * Optional extra engine fields are ignored; required fields are never coerced.
	 *
	 * @param string $stdout newline-terminated NDJSON output
	 * @return array matching rule-target records
	 */
	private static function parseMatches(string $stdout): array
	{
		$matches = [];
		$lines = self::getOutputLines($stdout);
		foreach ($lines as $line) {
			$record = json_decode($line);
			if (json_last_error() !== JSON_ERROR_NONE || !($record instanceof stdClass) || !isset($record->path, $record->rules) || !is_string($record->path) || $record->path === '' || $record->path[0] !== '/' || strpos($record->path, "\0") !== false || !is_array($record->rules) || !$record->rules) {
				throw new RuntimeException('Malformed YARA-X NDJSON file record.');
			}
			foreach ($record->rules as $rule) {
				if (!($rule instanceof stdClass) || !isset($rule->identifier, $rule->namespace) || !is_string($rule->identifier) || preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/D', $rule->identifier) !== 1 || !is_string($rule->namespace) || $rule->namespace === '' || strpos($rule->namespace, "\0") !== false) {
					throw new RuntimeException('Malformed YARA-X NDJSON rule record.');
				}
				$matches[] = [
					'path' => $record->path,
					'path_encoding' => 'literal',
					'namespace' => $rule->namespace,
					'identifier' => $rule->identifier
				];
			}
		}
		return $matches;
	}

	/**
	 * Group compiler reports, including YARA-X 1.22.1 help and replacement frames.
	 * Reuse common file-error recognition, including literal newlines in paths.
	 * Both warning and error reports have continuations. A new header, file error
	 * or unknown message ends the previous block. Never absorb arbitrary indented
	 * text just because it follows a warning. Codes/indentation may vary, while
	 * unfamiliar layouts remain separate diagnostics and fail conservatively.
	 *
	 * @param string $stderr raw scanner diagnostics
	 * @return array complete compiler reports and standalone scanner messages
	 */
	private static function getDiagnosticBlocks(string $stderr): array
	{
		$messages = parent::getDiagnosticMessages($stderr, self::FILE_ERROR_PATTERN);
		$blocks = [];
		$compiler = false;
		foreach ($messages as $message) {
			$is_header = preg_match(self::COMPILER_DIAGNOSTIC_PATTERN, $message) === 1;
			if ($compiler && !$is_header && preg_match(self::COMPILER_CONTINUATION_PATTERN, $message) === 1) {
				$last = count($blocks) - 1;
				$blocks[$last] .= "\n" . $message;
				continue;
			}
			$blocks[] = $message;
			$compiler = $is_header;
		}
		return $blocks;
	}

	/**
	 * Recognize recoverable filesystem errors and structured compiler warnings.
	 * Preserve unfamiliar diagnostics as execution failures, never successful scans.
	 *
	 * @param array $result normalized result reference
	 */
	private static function parseDiagnostics(array &$result): void
	{
		$messages = self::getDiagnosticBlocks($result['stderr']);
		foreach ($messages as $message) {
			if (preg_match(self::FILE_ERROR_PATTERN, $message, $record) === 1 && $record[0] === $message) {
				self::addDiagnostic($result, 'file_error', $message);
			} elseif (preg_match(self::COMPILER_DIAGNOSTIC_PATTERN, $message, $record) === 1 && $record['severity'] === 'warning') {
				self::addDiagnostic($result, 'warning', $message);
			} else {
				// Preserve the complete report internally, but expose only its
				// error header and source location, never the annotated source.
				$summary = '';
				if (preg_match('/\A[ \t]*error(?:\[[A-Za-z0-9_-]+\])?:[ \t]+[^\r\n]+/', $message, $header) === 1) {
					$summary = $header[0];
					if (preg_match('/\n[ \t]*-->[ \t]+([^\r\n]+:[0-9]+:[0-9]+)[ \t]*(?:\n|\z)/', $message, $location) === 1) {
						$summary .= ' at ' . $location[1];
					}
				}
				self::addDiagnostic($result, 'execution_error', $message, null, $summary);
			}
		}
	}
}
