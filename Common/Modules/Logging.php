<?php
/*
 * Bacularis - Bacula web interface
 *
 * Copyright (C) 2021-2024 Marcin Haba
 *
 * The main author of Bacularis is Marcin Haba, with contributors, whose
 * full list can be found in the AUTHORS file.
 *
 * Bacula(R) - The Network Backup Solution
 * Baculum   - Bacula web interface
 *
 * Copyright (C) 2013-2019 Kern Sibbald
 *
 * The main author of Baculum is Marcin Haba.
 * The original author of Bacula is Kern Sibbald, with contributions
 * from many others, a complete list can be found in the file AUTHORS.
 *
 * You may use this file and others of this release according to the
 * license defined in the LICENSE file, which includes the Affero General
 * Public License, v3.0 ("AGPLv3") and some additional permissions and
 * terms pursuant to its AGPLv3 Section 7.
 *
 * This notice must be preserved when any source code is
 * conveyed and/or propagated.
 *
 * Bacula(R) is a registered trademark of Kern Sibbald.
 */

namespace Bacularis\Common\Modules;

use Prado\Prado;
use Prado\Util\TLogger;

/**
 * Logger class.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class Logging extends CommonModule
{
	public static $debug_enabled = false;

	public const CATEGORY_EXECUTE = 'Execute';
	public const CATEGORY_EXTERNAL = 'External';
	public const CATEGORY_APPLICATION = 'Application';
	public const CATEGORY_GENERAL = 'General';
	public const CATEGORY_SECURITY = 'Security';

	private static function getLogCategories()
	{
		return [
			self::CATEGORY_EXECUTE,
			self::CATEGORY_EXTERNAL,
			self::CATEGORY_APPLICATION,
			self::CATEGORY_GENERAL,
			self::CATEGORY_SECURITY
		];
	}

	public static function log($category, $message)
	{
		if (self::$debug_enabled !== true) {
			return;
		}
		$current_mode = Prado::getApplication()->getMode();

		// switch application to debug mode
		Prado::getApplication()->setMode('Debug');

		if (!in_array($category, self::getLogCategories())) {
			$category = self::CATEGORY_SECURITY;
		}

		self::prepareLog($message);

		Prado::log($message, TLogger::INFO, $category);

		// switch back application to original mode
		Prado::getApplication()->setMode($current_mode);
	}

	/**
	 * Prepare log line to write to file.
	 *
	 * @param array|object|string $log message to log
	 */
	private static function prepareLog(&$log)
	{
		if (is_object($log) || is_array($log)) {
			$log = print_r($log, true);
		}
		$file_line = '';
		$trace = debug_backtrace();
		if (isset($trace[1]['file']) && isset($trace[1]['line'])) {
			$file_line = sprintf(
				'%s:%s: ',
				basename($trace[1]['file']),
				$trace[1]['line']
			);
			$log = $file_line . $log;
		}
		$log .= PHP_EOL . PHP_EOL;
	}

	/**
	 * Prepare command type log.
	 * It is output from binaries and scripts.
	 *
	 * @param string $command executed command
	 * @param array|object|string $output command output
	 * @return string formatted command output log
	 */
	public static function prepareCommand($command, $output)
	{
		if (is_array($output)) {
			$output = implode(PHP_EOL . ' ', $output);
		} elseif (is_object($output)) {
			$output = print_r($output, true);
		}
		return sprintf(
			"\n\n==> COMMAND: %s\n\n==> OUTPUT: %s",
			$command,
			$output
		);
	}
}
