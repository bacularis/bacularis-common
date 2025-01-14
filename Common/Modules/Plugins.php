<?php
/*
 * Bacularis - Bacula web interface
 *
 * Copyright (C) 2021-2025 Marcin Haba
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

use DateTime;
use Prado\Prado;

/**
 * Generic plugins class responsible for providing basic tools to plugin modules.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class Plugins extends CommonModule
{
	/**
	 * Plugin log categories.
	 */
	public const LOG_INFO = 0;
	public const LOG_WARNING = 1;
	public const LOG_ERROR = 2;
	public const LOG_DEBUG = 3;

	/**
	 * Plugin log destinations.
	 */
	public const LOG_DEST_DISABLED = 0;
	public const LOG_DEST_STDOUT = 1;
	public const LOG_DEST_FILE = 2;

	/**
	 * Get plugin by name.
	 *
	 * @param string $name plugin name
	 * @return null|object plugin object
	 */
	public function getPluginByName(string $name)
	{
		$pname = strtolower($name);
		$plugin = $this->getModule($pname . '_plugin');
		if (!($plugin instanceof IBacularisPlugin)) {
			$plugin = null;
		}
		return $plugin;
	}

	/**
	 * Get plugin command.
	 *
	 * @param array $params plugin params
	 * @return string plugin command
	 */
	public function getCommand(array $params): string
	{
		$cmd = '';
		if (key_exists('plugin-name', $params)) {
			// add some common attributes
			$dt = new DateTime();
			$params['job-starttime'] = $dt->format('Y-m-d_His');
			$plugin = $this->getPluginByName($params['plugin-name']);
			if ($plugin) {
				$plugin->initialize($params);
				$cmd = $plugin->getPluginCommands($params);
				$cmd = implode(PHP_EOL, $cmd);
			}
		}
		return $cmd;
	}

	/**
	 * Plugin log function.
	 * Logging can work on stdout or to a file.
	 *
	 * @param string $category log category
	 * @param string $msg log message
	 * @param int $dest log destination
	 */
	public static function log(string $category, string $msg, int $dest = self::LOG_DEST_STDOUT): void
	{
		$msg_type = '';
		if ($category == self::LOG_INFO) {
			$msg_type = 'INFO';
		} elseif ($category == self::LOG_WARNING) {
			$msg_type = 'WARNING';
		} elseif ($category == self::LOG_ERROR) {
			$msg_type = 'ERROR';
		} elseif ($category == self::LOG_DEBUG) {
			$msg_type = 'DEBUG';
		}
		$message = sprintf('[%s] %s', $msg_type, $msg);
		if ($dest === self::LOG_DEST_STDOUT) {
			fwrite(STDERR, $message);
		} elseif ($dest === self::LOG_DEST_FILE) {
			Logging::directLog($message);
		}
	}

	/**
	 * Plugin debug function.
	 * It log messages and provides line and file from where it was called.
	 *
	 * @param mixed $msg log message
	 * @param string $dest log destination
	 */
	public static function debug($msg, int $dest = self::LOG_DEST_STDOUT): void
	{
		if (!is_string($msg)) {
			$msg = var_export($msg, true);
		}
		$trace = debug_backtrace();
		if (isset($trace[1]['file']) && isset($trace[1]['line'])) {
			$file_line = sprintf(
				'%s:%s: ',
				basename($trace[1]['file']),
				$trace[1]['line']
			);
			$msg = $file_line . $msg;
		}
		self::log(
			Plugins::LOG_DEBUG,
			$msg,
			$dest
		);
	}
}
