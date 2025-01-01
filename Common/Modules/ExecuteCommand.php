<?php
/*
 * Bacularis - Bacula web interface
 *
 * Copyright (C) 2021-2024 Marcin Haba
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
	 * Execute command.
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
