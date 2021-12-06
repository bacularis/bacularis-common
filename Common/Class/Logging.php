<?php
/*
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

Prado::using('Application.Common.Class.CommonModule');

/**
 * Logger class.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 * @package Baculum Common
 */
class Logging extends CommonModule {

	public static $debug_enabled = false;

	const CATEGORY_EXECUTE = 'Execute';
	const CATEGORY_EXTERNAL = 'External';
	const CATEGORY_APPLICATION = 'Application';
	const CATEGORY_GENERAL = 'General';
	const CATEGORY_SECURITY = 'Security';

	private function getLogCategories() {
		$categories = array(
			self::CATEGORY_EXECUTE,
			self::CATEGORY_EXTERNAL,
			self::CATEGORY_APPLICATION,
			self::CATEGORY_GENERAL,
			self::CATEGORY_SECURITY
		);
		return $categories;
	}

	public function log($cmd, $output, $category, $file, $line) {
		if(self::$debug_enabled !== true) {
			return;
		}
		$current_mode = $this->Application->getMode();

		// switch application to debug mode
		$this->Application->setMode('Debug');

		if(!in_array($category, $this->getLogCategories())) {
			$category = self::CATEGORY_SECURITY;
		}

		$log = sprintf(
			'Command=%s, Output=%s, File=%s, Line=%d',
			$cmd,
			print_r($output, true),
			$file,
			intval($line)
		);

		Prado::trace($log, $category);

		// switch back application to original mode
		$this->Application->setMode($current_mode);
	}
}

?>
