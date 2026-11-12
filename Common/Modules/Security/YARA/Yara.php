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
use Throwable;

/**
 * Classic YARA CLI adapter with conservative text parsing.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class Yara extends Common
{
	/**
	 * CLI diagnostics do not escape newlines in filenames.
	 * Stop at the I/O suffix.
	 */
	private const FILE_ERROR_PATTERN = '~\Gerror scanning (?<path>.+?): could not (?:open|read|map) file\.?(?=\n|\z)~s';

	/**
	 * Scan a regular file or recursively scan a directory without following links.
	 *
	 * @param string $bin_path directory containing yara, empty for PATH
	 * @param string $rules_path source file/directory or one compiled file
	 * @param string $target_path regular file or directory
	 * @param bool $compiled_rules enable --compiled-rules
	 * @param int $timeout complete process budget in seconds
	 * @param string $config_path must be empty for classic YARA
	 * @param int $max_output_bytes combined stdout/stderr capture limit
	 * @return array normalized Common::newResult() structure
	 */
	public static function runScan(string $bin_path, string $rules_path, string $target_path, bool $compiled_rules = false, int $timeout = self::DEFAULT_TIMEOUT, string $config_path = '', int $max_output_bytes = self::DEFAULT_OUTPUT_LIMIT): array
	{
		$result = self::newResult(self::TYPE_YARA);
		try {
			if ($config_path !== '') {
				throw new InvalidArgumentException(
					'Classic YARA does not support a YARA-X configuration file.'
				);
			}
			$binary = self::getBinaryPath($bin_path, 'yara');
			$rules = self::getRuleFiles($rules_path, $compiled_rules);
			$target = self::getTargetPath($target_path);
			$command = [
				$binary,
				'--print-namespace',
				'--disable-console-logs',
				'--no-follow-symlinks'
			];
			if ($compiled_rules) {
				$command[] = '--compiled-rules';
			}
			if (is_dir($target)) {
				$command[] = '--recursive';
			}
			$command = array_merge($command, $rules, [$target]);
			$result = self::execute(self::TYPE_YARA, $command, $timeout, $max_output_bytes);
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
	 * Get a classic YARA version without logging command output.
	 *
	 * @param string $bin_path binary directory, empty for PATH
	 * @param int $timeout process timeout in seconds
	 * @param string $config_path must be empty for classic YARA
	 * @param int $max_output_bytes combined capture limit
	 * @return array normalized version result
	 */
	public static function getVersion(string $bin_path = '', int $timeout = self::DEFAULT_VERSION_TIMEOUT, string $config_path = '', int $max_output_bytes = self::DEFAULT_VERSION_OUTPUT_LIMIT): array
	{
		$result = self::newResult(self::TYPE_YARA);
		try {
			if ($config_path !== '') {
				throw new InvalidArgumentException(
					'Classic YARA does not support a YARA-X configuration file.'
				);
			}
			$binary = self::getBinaryPath($bin_path, 'yara');
			$command = [$binary, '--version'];
			$result = self::execute(self::TYPE_YARA, $command, $timeout, $max_output_bytes);
			self::parseDiagnostics($result);
			$result = self::parseVersion(
				$result,
				'/\A(?<version>[0-9]+\.[0-9]+\.[0-9]+(?:[-+][A-Za-z0-9._+-]+)?)\z/D'
			);
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
	 * Parse minimal namespace:identifier + space + path records.
	 * Do not decode ambiguous newline escapes or deduplicate rendered paths.
	 *
	 * @param string $stdout raw match output
	 * @return array matching rule-target records
	 */
	private static function parseMatches(string $stdout): array
	{
		$matches = [];
		$lines = self::getOutputLines($stdout);
		foreach ($lines as $line) {
			if (preg_match('/\A(?<namespace>[^\s:\x00]+):(?<identifier>[A-Za-z_][A-Za-z0-9_]*) (?<path>\/[^\r\n\x00]*)\z/D', $line, $record) !== 1) {
				throw new RuntimeException('Malformed classic YARA match record.');
			}
			$matches[] = [
				'path' => $record['path'],
				'path_encoding' => 'yara-text',
				'namespace' => $record['namespace'],
				'identifier' => $record['identifier']
			];
		}
		return $matches;
	}

	/**
	 * Recognize file I/O failures, compiler warnings and rule runtime warnings.
	 * Runtime warnings do not invalidate the scan under the checker policy, but
	 * excessive string matches are marked so their reliability impact is logged.
	 * All other stderr, including zero-exit timeouts, remains an execution error.
	 *
	 * @param array $result normalized result reference
	 */
	private static function parseDiagnostics(array &$result): void
	{
		$messages = self::getDiagnosticMessages($result['stderr'], self::FILE_ERROR_PATTERN);
		foreach ($messages as $message) {
			if (preg_match(self::FILE_ERROR_PATTERN, $message, $record) === 1 && $record[0] === $message) {
				self::addDiagnostic($result, 'file_error', $message, $record['path']);
			} elseif (preg_match('/\Awarning: rule "[A-Za-z_][A-Za-z0-9_]*" in [^\r\n]+\([0-9]+\): [^\r\n]+\z/D', $message) === 1) {
				self::addDiagnostic($result, 'warning', $message);
			} elseif (preg_match('/\Awarning: rule "(?<rule>[A-Za-z_][A-Za-z0-9_]*)": (?<message>[^\r\n]+)\z/D', $message, $record) === 1) {
				self::addDiagnostic($result, 'runtime_warning', $message);
				$last = count($result['diagnostics']) - 1;
				$result['diagnostics'][$last]['rule'] = $record['rule'];
				$result['diagnostics'][$last]['results_may_be_incorrect'] = (
					preg_match('/\Atoo many matches for \$[A-Za-z0-9_]*, results for this rule may be incorrect\.?\z/D', $record['message']) === 1
				);
			} else {
				// Classic YARA reports compiler errors on one line. Unknown text
				// stays internal. Only recognized error headers are displayable.
				$summary = '';
				if (preg_match('/\A(?:[^\r\n]+\([0-9]+\): error: [^\r\n]+|error: [^\r\n]+|(?:unknown|unrecognized|invalid) option[^\r\n]*|(?:invalid|unsupported|corrupt|incompatible) compiled rules[^\r\n]*)/', $message, $header) === 1) {
					$summary = $header[0];
				}
				self::addDiagnostic($result, 'execution_error', $message, null, $summary);
			}
		}
	}
}

