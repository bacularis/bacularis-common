<?php
/*
 * Bacularis - Bacula web interface
 *
 * Copyright (C) 2021-2026 Marcin Haba
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
	/**
	 * Direct common log file name.
	 */
	private const COMMON_DIRECT_LOG = 'bacularis-common.log';

	/**
	 * Dedicated script log file.
	 * Created and used for storing debug messages.
	 */
	private const SCRIPT_DIRECT_LOG = 'bacularis-%s.log';

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

	public static function log($category, $message, $force = false)
	{
		if (self::$debug_enabled !== true && $force === false) {
			return;
		}
		$current_mode = Prado::getApplication()->getMode();

		// switch application to debug mode
		Prado::getApplication()->setMode('Debug');

		if (!in_array($category, self::getLogCategories())) {
			$category = self::CATEGORY_SECURITY;
		}

		self::prepareLog($message);

		if (php_sapi_name() == 'cli') {
			// All cli executions go to direct log
			self::directLog($message);
		} else {
			Prado::log($message, TLogger::INFO, $category);
		}

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
		// First reduce log if needed and convert to string
		self::reduceLogStr($log);

		// Then prepare markers
		$file_line = '';
		$trace = debug_backtrace();
		if (isset($trace[1]['file']) && isset($trace[1]['line'])) {
			$file_line = sprintf(
				'[%s:%s]: ',
				basename($trace[1]['file']),
				$trace[1]['line']
			);
			$log = $file_line . $log;
		}
		$log .= PHP_EOL . PHP_EOL;
	}

	/**
	 * Minimize log size to string
	 * This is for reducing very large logs and converts it into string.
	 *
	 * @param mixed $log log value
	 */
	private static function reduceLogStr(&$log): void
	{
		if (is_string($log) || is_array($log) || is_object($log)) {
			if (is_array($log)) {
				$count = count($log);
				if ($count > 200) {
					$log = [
						'count' => $count,
						'first' => array_slice($log, 0, 100, true),
						'last' => array_slice($log, -100, 100, true)
					];
				}
				$log = print_r($log, true);
			} elseif (is_string($log)) {
				$length = strlen($log);
				if ($length > 100000) {
					$log =
						substr($log, 0, 50000)
						. PHP_EOL
						. sprintf('[... %d bytes omitted ...]', $length - 100000)
						. PHP_EOL
						. substr($log, -50000);
				}
			} elseif (is_object($log)) {
				if (property_exists($log, 'output') && is_array($log->output)) {
					$count = count($log->output);
					if ($count > 200) {
						$log = (object) [
							'error' => $log->error ?? null,
							'output' => [
								'count' => $count,
								'first' => array_slice($log->output, 0, 100, true),
								'last' => array_slice($log->output, -100, 100, true)
							]
						];
					}
				}
				$log = print_r($log, true);
			}
		}
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

	/**
	 * Direct logging to common file.
	 * This log is used for logging executions from command line.
	 *
	 * @param string $message message to write in log
	 */
	public static function directLog(string $message): void
	{
		$script_name = '';
		if (isset($_SERVER['argv'][0])) {
			$script_name = sprintf(self::SCRIPT_DIRECT_LOG, basename($_SERVER['argv'][0]));
		} else {
			$script_name = self::COMMON_DIRECT_LOG;
		}
		$dir = Prado::getPathOfNamespace('Bacularis.Common.Working');
		$file = implode(DIRECTORY_SEPARATOR, [$dir, $script_name]);
		file_put_contents($file, $message . PHP_EOL, LOCK_EX | FILE_APPEND);
	}
}
