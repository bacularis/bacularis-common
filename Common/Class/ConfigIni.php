<?php
/*
 * Bacula(R) - The Network Backup Solution
 * Baculum   - Bacula web interface
 *
 * Copyright (C) 2013-2021 Kern Sibbald
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

Prado::using('Application.Common.Class.Interfaces');
Prado::using('Application.Common.Class.CommonModule');

/**
 * Module to read/write INI-style config.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 * @package Baculum Common
 */
class ConfigIni extends CommonModule implements ConfigFormat {

	/**
	 * Write config data to file in INI format.
	 *
	 * @access public
	 * @param string $source config file path
	 * @param array $config config data
	 * @return boolean true if config written successfully, otherwise false
	 */
	public function write($source, $config) {
		$content = $this->prepareConfig($config);
		$orig_umask = umask(0);
		umask(0077);
		$result = file_put_contents($source, $content);
		umask($orig_umask);
		return is_int($result);
	}

	/**
	 * Read config data from file in INI format.
	 *
	 * @access public
	 * @param string $source config file path
	 * @return array config data
	 */
	public function read($source) {
		$content = parse_ini_file($source, true);
		if (!is_array($content)) {
			$content = array();
		}
		return $content;
	}

	/**
	 * Prepare config data to save in INI format.
	 *
	 * @access public
	 * @param array $config config data
	 * @return string config content
	 */
	public function prepareConfig($config) {
		$content = '';
		foreach($config as $section => $options) {
			$content .= "[$section]\n";
				foreach($options as $option => $value) {
					if (is_array($value)) {
						$str_keys = array_filter(array_keys($value), 'is_string');
						$is_assoc = (count($str_keys) > 0); // check if array is associative
						foreach($value as $k => $v) {
							$v = $this->prepareValue($v);
							if (!$is_assoc) {
								// array with numeric indexes, set empty key
								$k = '';
							}
							$content .= "{$option}[$k] = $v\n";
						}
					} else {
						$value = $this->prepareValue($value);
						$content .= "$option = $value\n";
					}
				}
			$content .= "\n";
		}
		return $content;
	}

	/**
	 * Prepare value written to INI-style config.
	 *
	 * @access private
	 * @param string $value text to prepare
	 * @return string value ready to write
	 */
	private function prepareValue($value) {
		$value = str_replace('"', '\"', $value);
		$value = "\"$value\"";
		return $value;
	}

	/**
	 * Validate config to INI format.
	 *
	 * @access public
	 * @param array $required_options required options grouped in sections
	 * @param array $config config
	 * @param string $source config file path
	 * @return boolean true if config valid, otherwise false
	 */
	public function isConfigValid(array $required_options, array $config = array(), $source) {
		$valid = true;
		$invalid = array('required' => null);

		//if (count($config) === 0) {
			// Check existing config
		//	$config = $this->getConfig();
		//}

		foreach ($required_options as $section => $options) {
			if (array_key_exists($section, $config)) {
				if (!is_array($config[$section]) || count($config[$section]) === 0) {
					// Empty section (no options)
					$invalid['required'] = array('value' => $section, 'type' => 'empty_section');
					$valid = false;
					break;
				}
				for ($i = 0; $i < count($options); $i++) {
					if (!array_key_exists($options[$i], $config[$section])) {
						// Required option not found
						$invalid['required'] = array('value' => $options[$i], 'type' => 'option');
						$valid = false;
						break;
					}
				}
				
			} else {
				// Required section not found
				$invalid['required'] = array('value' => $section, 'type' => 'section');
				$valid = false;
				break;
			}
		}
		if ($valid != true) {
			$emsg = '';
			if ($invalid['required']['type'] === 'section' || $invalid['required']['type'] === 'option') {
				$emsg = "ERROR [$source] Required {$invalid['required']['type']} '{$invalid['required']['value']}' not found in config.";
			} elseif ($invalid['required']['type'] === 'empty_section') {
				$emsg = "ERROR [$source] Required section '{$invalid['required']['value']}' is empty in config.";
			} else {
				// it shouldn't happen
				$emsg = "ERROR [$source] Internal error";
			}

			$this->getModule('logging')->log(
				__FUNCTION__,
				$emsg,
				Logging::CATEGORY_APPLICATION,
				__FILE__,
				__LINE__
			);
		}
		return $valid;
	}
}
?>
