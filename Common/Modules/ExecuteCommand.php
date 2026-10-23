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

namespace Bacularis\Common\Modules;

use Bacularis\Common\Modules\Errors\GenericError;
use Bacularis\Common\Modules\Logging;

/**
 * Module responsible for executing a program.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class ExecuteCommand extends CommonModule
{
	/**
	 * Command pattern used to execute command.
	 */
	public const COMMAND_PATTERN = '%s 2>&1';

	/**
	 * Get command pattern.
	 *
	 * @return string command pattern
	 */
	private static function getCmdPattern(): string
	{
		// Default command pattern
		return self::COMMAND_PATTERN;
	}

	/**
	 * Execute a shell expression assembled from command fragments.
	 * Shell operators and already-composed fragments are interpreted by the shell.
	 *
	 * @param array $params command with parameters
	 */
	public static function execCommand(array $params): array
	{
		$cmd_pattern = self::getCmdPattern();
		$base_cmd = implode(' ', $params);
		$cmd = sprintf($cmd_pattern, $base_cmd);
		exec($cmd, $output, $exitcode);
		Logging::log(
			Logging::CATEGORY_EXECUTE,
			Logging::prepareCommand($cmd, $output)
		);
		$result = self::prepareResult($output, $exitcode);
		return $result;
	}

	/**
	 * Execute a program with literal arguments, escaping every array element.
	 * Shell operators such as pipes and redirects are treated as literal arguments.
	 * Unlike execCommand(), this method does not accept shell expressions.
	 * Temporary environment values are restored before logging and result preparation.
	 * Environment values are not logged, but child output is logged without redaction.
	 *
	 * @param array $params program name followed by individual literal arguments
	 * @param array $environment temporary environment variable names and scalar values
	 * @return array output and error status, with a nonzero error for invalid input
	 */
	public static function execProgram(array $params, array $environment = []): array
	{
		if (count($params) === 0) {
			$output = [GenericError::MSG_ERROR_INVALID_COMMAND];
			return self::prepareResult($output, GenericError::ERROR_INVALID_COMMAND);
		}
		$temporary_environment = [];
		foreach ($environment as $name => $value) {
			if (!is_string($name) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $name) !== 1 || !is_scalar($value)) {
				$output = [GenericError::MSG_ERROR_INVALID_COMMAND];
				return self::prepareResult($output, GenericError::ERROR_INVALID_COMMAND);
			}
			$value = (string) $value;
			if (strpos($value, "\0") !== false) {
				$output = [GenericError::MSG_ERROR_INVALID_COMMAND];
				return self::prepareResult($output, GenericError::ERROR_INVALID_COMMAND);
			}
			$temporary_environment[$name] = $value;
		}
		$cmd_pattern = self::getCmdPattern();
		$safe_params = array_map('escapeshellarg', $params);
		$base_cmd = implode(' ', $safe_params);
		$cmd = sprintf($cmd_pattern, $base_cmd);
		$previous_environment = [];
		foreach ($temporary_environment as $name => $value) {
			$previous_environment[$name] = getenv($name);
		}
		$environment_set = true;
		try {
			foreach ($temporary_environment as $name => $value) {
				if (!putenv("{$name}={$value}")) {
					$environment_set = false;
					break;
				}
			}
			if ($environment_set) {
				exec($cmd, $output, $exitcode);
			}
		} finally {
			foreach ($previous_environment as $name => $value) {
				if ($value === false) {
					putenv($name);
				} else {
					putenv("{$name}={$value}");
				}
			}
		}
		if (!$environment_set) {
			$output = [GenericError::MSG_ERROR_INVALID_COMMAND];
			return self::prepareResult($output, GenericError::ERROR_INVALID_COMMAND);
		}
		$log_message = Logging::prepareCommand($cmd, $output);
		Logging::log(Logging::CATEGORY_EXECUTE, $log_message);
		$result = self::prepareResult($output, $exitcode);
		return $result;
	}

	/**
	 * Prepare command result.
	 *
	 * @param array $output output from command execution
	 * @param int $exitcode command exit code
	 * @return array result with output and exitcode
	 */
	public static function prepareResult($output, $exitcode)
	{
		$result = [
			'output' => $output,
			'error' => $exitcode
		];
		return $result;
	}
}
