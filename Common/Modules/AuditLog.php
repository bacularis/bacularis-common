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

/**
 * Base audit log class.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
abstract class AuditLog extends CommonModule
{
	/**
	 * Message categories.
	 */
	public const CATEGORY_CONFIG = 'Config'; // save configuration
	public const CATEGORY_ACTION = 'Action'; // run backup, run restore
	public const CATEGORY_APPLICATION = 'Application'; // save application settings like change auth method
	public const CATEGORY_SECURITY = 'Security'; // log in, log out, login failed

	/**
	 * Message types.
	 */
	public const TYPE_INFO = 'INFO'; // problem
	public const TYPE_WARNING = 'WARNING'; // problem
	public const TYPE_ERROR = 'ERROR'; // problem

	/**
	 * Default config values.
	 */
	public const DEF_ENABLED = true;
	public const DEF_MAX_LINES = 10000;
	public const DEF_TYPES = [
		self::TYPE_INFO,
		self::TYPE_WARNING,
		self::TYPE_ERROR
	];
	public const DEF_CATEGORIES = [
		self::CATEGORY_APPLICATION,
		self::CATEGORY_SECURITY
	];

	private $enabled;
	private $types;
	private $categories;
	private $max_lines;

	/**
	 * Initialize module.
	 *
	 * @param TXmlElement $config module configuration
	 */
	public function init($config)
	{
		$web_config = $this->getModule('web_config')->getConfig('baculum');
		if (count($web_config) > 0) {
			$this->enabled = (bool) ($web_config['enable_audit_log'] ?? self::DEF_ENABLED);
			$this->types = $web_config['audit_log_types'] ?? self::DEF_TYPES;
			$this->categories = $web_config['audit_log_categories'] ?? self::DEF_CATEGORIES;
			$this->max_lines = (int) ($web_config['audit_log_max_lines'] ?? self::DEF_MAX_LINES);
		}
	}

	/**
	 * Get audit log categories.
	 *
	 * @return array log categories
	 */
	private function getCategories()
	{
		return [
			self::CATEGORY_CONFIG,
			self::CATEGORY_ACTION,
			self::CATEGORY_APPLICATION,
			self::CATEGORY_SECURITY,
		];
	}

	/**
	 * Validate log category.
	 *
	 * @param string $category log category
	 * @return bool true if is valid, otherwise false
	 */
	private function validateCategory($category)
	{
		$valid = true;
		$categories = $this->getCategories();
		if (!in_array($category, $categories)) {
			$valid = false;
			$emsg = 'Wrong audit log category.';
			Logging::log(
				Logging::CATEGORY_APPLICATION,
				$emsg
			);
		} elseif (!in_array($category, $this->categories)) {
			$valid = false;
		}
		return $valid;
	}

	/**
	 * Get audit log types.
	 *
	 * @return array log types
	 */
	private function getTypes()
	{
		return [
			self::TYPE_INFO,
			self::TYPE_WARNING,
			self::TYPE_ERROR
		];
	}

	/**
	 * Validate log type.
	 *
	 * @param string $type log type
	 * @return bool true if is valid, otherwise false
	 */
	private function validateType($type)
	{
		$valid = true;
		$types = $this->getTypes();
		if (!in_array($type, $types)) {
			$valid = false;
			$emsg = 'Wrong audit log type.';
			Logging::log(
				Logging::CATEGORY_APPLICATION,
				$emsg
			);
		} elseif (!in_array($type, $this->types)) {
			$valid = false;
		}
		return $valid;
	}

	/**
	 * Format log message.
	 *
	 * @param string $ip_address user IP address
	 * @param string $user username
	 * @param string $date current date
	 * @param string $type log type
	 * @param string $category log category
	 * @param string $action log message
	 */
	private function formatLog($ip_address, $user, $date, $type, $category, $action)
	{
		return sprintf(
			'%s %s [%s] [%s] [%s] %s',
			$ip_address,
			$user,
			$date,
			$type,
			$category,
			$action
		);
	}

	public function audit($type, $category, $action)
	{
		if (!$this->enabled) {
			// audit log is disabled
			return;
		}

		if (!$this->validateType($type)) {
			// not supported or wrong log type
			return;
		}

		if (!$this->validateCategory($category)) {
			// not supported or wrong log category
			return;
		}

		$ip_address = $_SERVER['REMOTE_ADDR'];
		$user = $this->Application->User->getUsername();
		$date = date('Y-m-d H:i:s');
		$log = $this->formatLog(
			$ip_address,
			$user,
			$date,
			$type,
			$category,
			$action
		);
		$this->append([$log]);
	}

	/**
	 * Append audit message to audit logs.
	 * NOTE: Max. lines limit is taken into acocunt.
	 *
	 * @param array $logs log messages
	 * @return array logs stored in log file
	 */
	public function append(array $logs)
	{
		$logs_all = [];
		$f = $this->getConfigFile();
		$fp = fopen($f, 'c+');
		if (flock($fp, LOCK_EX)) {
			$audit_file = file_get_contents($f);
			$logs_file = explode(PHP_EOL, $audit_file);
			$logs_all = array_merge($logs_file, $logs);
			$all_len = count($logs_all);
			if ($all_len > $this->max_lines) {
				$len = $all_len - $this->max_lines;
				array_splice($logs_all, 0, $len);
			}
			$audit = implode(PHP_EOL, $logs_all);
			rewind($fp);
			ftruncate($fp, 0);
			fwrite($fp, $audit);
			fflush($fp);
			flock($fp, LOCK_UN);
		} else {
			$emsg = 'Could not get the exclusive lock: ' . $f;
			Logging::log(
				Logging::CATEGORY_APPLICATION,
				$emsg
			);
		}
		fclose($fp);
		return $logs_all;
	}

	/**
	 * Read logs from file.
	 *
	 * @return array log messages
	 */
	public function read()
	{
		$logs = [];
		$f = $this->getConfigFile();
		if (!file_exists($f)) {
			return $logs;
		}
		$fp = fopen($f, 'r');
		if (flock($fp, LOCK_SH)) {
			$audit = file_get_contents($f);
			$logs = explode(PHP_EOL, $audit);
			flock($fp, LOCK_UN);
		} else {
			$emsg = 'Could not get the shared lock: ' . $f;
			Logging::log(
				Logging::CATEGORY_APPLICATION,
				$emsg
			);
		}
		fclose($fp);
		return $logs;
	}

	public function getLogs()
	{
		$ret = [];
		$logs = $this->read();
		for ($i = 0; $i < count($logs); $i++) {
			$line = $this->parseLog($logs[$i]);
			if (count($line) > 0) {
				$ret[] = $line;
			}
		}
		return $ret;
	}

	private function parseLog($log)
	{
		$ret = [];
		$pattern = '/^(?P<ip_address>\S+)\s(?P<username>\S+)\s\[(?P<date>[\S\s]+)?\]\s\[(?P<type>\S+)\]\s\[(?P<category>\S+)\]\s(?P<log>.+)$/';
		if (preg_match($pattern, $log, $match) === 1) {
			$ret = $match;
		}
		return $ret;
	}

	abstract public function getConfigFile();
}
