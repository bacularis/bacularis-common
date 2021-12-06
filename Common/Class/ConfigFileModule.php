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
 * Generic config file module.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 * @package Baculum Common
 */
class ConfigFileModule extends CommonModule {
	
	const CONFIG_FILE_EXT = '.conf';

	const DEFAULT_CONFIG_FILE_FORMAT = 'ini';

	/**
	 * Get config real path.
	 *
	 * @access protected
	 * @param string $path config file path
	 * @return string config system path
	 */
	protected function getConfigRealPath($path) {
		$pos = strpos($path, '/');
		if (!$pos && $pos !== 0) {
			$path = Prado::getPathOfNamespace($path, self::CONFIG_FILE_EXT);
		}
		return $path;
	}

	/**
	 * Check if config file exists.
	 *
	 * @access protected
	 * @param string $path config file path
	 * @return boolean true if file exists, otherwise false
	 */
	protected function isConfig($path) {
		$real_path = $this->getConfigRealPath($path);
		return file_exists($real_path);
	}

	/**
	 * Write config to file.
	 *
	 * @access protected
	 * @param array $config config
	 * @param string $path config file path
	 * @param string $format config file format
	 * @return boolean true if config written successfully, otherwise false
	 */
	protected function writeConfig(array $config, $path, $format) {
		$real_path = $this->getConfigRealPath($path);
		$config_module = $this->getConfigFormatModule($format);
		$result = $config_module->write($real_path, $config);
		return $result;
	}

	/**
	 * Read config from file.
	 *
	 * @access public
	 * @param string $path config file path
	 * @param string $format config file format
	 * @return array config
	 */
	protected function readConfig($path, $format) {
		$config = array();
		if ($this->isConfig($path) === true) {
			$real_path = $this->getConfigRealPath($path);
			$config_module = $this->getConfigFormatModule($format);
			$config = $config_module->read($real_path);
		}
		return $config;
	}

	/**
	 * Prepare config in specified format.
	 * In all config format implementations it is done internally before saving config.
	 * Here it is exposed, because sometimes a format can use to validation an external tool.
	 *
	 * @param array $config config
	 * @param string $format config file format
	 * @return string config content
	 */
	protected function prepareConfig($config, $format) {
		$config_module = $this->getConfigFormatModule($format);
		$config_content = $config_module->prepareConfig($config);
		return $config_content;
	}

	/**
	 * Validate config.
	 * Config validation should be used as early as config data is available.
	 * Validation is done in read/write config methods.
	 *
	 * @access protected
	 * @param array $required_options required options grouped in sections
	 * @param array $config config
	 * @param string $format config file format
	 * @param string $path config file path
	 * @return boolean true if config valid, otherwise false
	 */
	protected function isConfigValid($required_options, array $config = array(), $format, $path = '') {
		$config_format = $this->getConfigFormatModule($format);
		$is_valid = $config_format->isConfigValid($required_options, $config, $path);
		return $is_valid;
	}

	/**
	 * Get module that supports read/write config file in specified format.
	 *
	 * @access private
	 * @param string $format config file format
	 * @return object module responsible for read/write config file
	 */
	private function getConfigFormatModule($format) {
		$module_id = $this->getFormatModuleId($format);
		$module = $this->getModule($module_id);
		if (is_null($module)) {
			$module_id = $this->getFormatModuleId(self::DEFAULT_CONFIG_FILE_FORMAT);
			$module = $this->getModule($module_id);
		}
		return $module;
	}

	/**
	 * Get ID for module that supports specified format.
	 *
	 * @access private
	 * @param string $format config file format
	 * @return string module ID
	 */
	private function getFormatModuleId($format) {
		$module_id = "config_$format";
		return $module_id;
	}
}
?>
