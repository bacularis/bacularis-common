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

/**
 * General requirement class with common dependencies both for API and Web.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Requirements
 * @package Baculum Common
 */
abstract class GeneralRequirements {

	/**
	 * Required PHP extensions.
	 *
	 * Note, requirements page is visible before any language is set and before
	 * translation engine initialization. From this reason all messages are not
	 * translated.
	 */
	private $req_exts = array(
		array(
			'ext' => 'json',
			'help_msg' => 'Please install <b>PHP JSON module</b>.'
		),
		array(
			'ext' => 'dom',
			'help_msg' => 'Please install <b>PHP DOM module</b> to support XML documents (usually included in php-xml binary package).'
		)
	);

	/**
	 * Required read/write access for these application directories.
	 */
	private $req_app_rw_dirs = array(
		'assets', 'protected/runtime',
	);

	/**
	 * Required read/wrie access for these directories in base directory.
	 */
	private $req_base_rw_dirs = array(
		'Config', 'Logs',
	);

	/**
	 * Generic help message for directories without fulfilled dependencies.
	 */
	const DIR_HELP_MSG = 'Please make readable and writeable by the web server user the following directory: <b>%s</b>';

	/**
	 * This static variable stores all dependency messages to show on the page.
	 * If empty, dependencies are fulfilled.
	 */
	protected static $requirements = array();

	public function __construct($app_dir, $base_dir) {
		$this->validateEnvironment($app_dir, $base_dir);
	}

	/**
	 * Validate all environment depenencies.
	 *
	 * @param string $app_dir full path to main application directory
	 * @param string $base_dir full path to service specific base directory
	 * @return none
	 */
	private function validateEnvironment($app_dir, $base_dir) {
		$this->validateDirectories($app_dir, $base_dir);
		$this->validateExtensions($this->req_exts);
	}

	/**
	 * Validate directory access depenencies.
	 *
	 * @param string $app_dir full path to main application directory
	 * @param string $base_dir full path to service specific base directory
	 * @return none
	 */
	private function validateDirectories($app_dir, $base_dir) {
		for ($i = 0; $i < count($this->req_app_rw_dirs); $i++) {
			$dir = $app_dir . '/' . $this->req_app_rw_dirs[$i];
			if (is_readable($dir) && is_writeable($dir)) {
				// test passed, skip
				continue;
			}
			self::$requirements[] = sprintf(self::DIR_HELP_MSG, $dir);
		}
		for ($i = 0; $i < count($this->req_base_rw_dirs); $i++) {
			$dir = $base_dir . '/' . $this->req_base_rw_dirs[$i];
			if (is_readable($dir) && is_writeable($dir)) {
				// test passed, skip
				continue;
			}
			self::$requirements[] = sprintf(self::DIR_HELP_MSG, $dir);
		}
	}

	/**
	 * Validate PHP extensions.
	 *
	 * @param array $req_exts extension list
	 * @return none
	 */
	protected static function validateExtensions($req_exts) {
		for ($i = 0; $i < count($req_exts); $i++) {
			if (!extension_loaded($req_exts[$i]['ext'])) {
				self::$requirements[] = $req_exts[$i]['help_msg'];
			}
		}
	}

	/**
	 * Simple method to show results.
	 *
	 * @param string $product product name ('Baculum Web' or 'Baculum API'...etc.)
	 * @return none
	 */
	protected static function showResult($product) {
		if (count(self::$requirements) > 0) {
			echo '<html><body><h2>' . $product . ' - Missing dependencies</h2><ul>';
			for ($i = 0; $i < count(self::$requirements); $i++) {
				echo '<li>' . self::$requirements[$i] . '</li>';
			}
			echo '</ul>';
			echo 'To run ' . $product . ' <u>please correct above requirements</u> and refresh this page in web browser.';
			echo '</body></html>';
			exit();
		}
	}
}
?>
