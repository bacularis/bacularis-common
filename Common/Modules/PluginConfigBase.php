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

use Prado\Prado;

/**
 * Base plugin configuration module.
 * It provides base tools to manage Bacularis plugin configuration.
 * All plugin config modules should inherit it.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
abstract class PluginConfigBase extends ConfigFileModule
{
	/**
	 * Settings name pattern.
	 */
	public const SETTINGS_NAME_PATTERN = '(?!^\d+$)[\p{L}\p{N}\p{Z}\-\'\\/\\(\\)\\{\\}:.#~_,+!$]{1,100}';

	/**
	 * Plugin types.
	 */
	public const PLUGIN_TYPE_BACULA_CONFIGURATION = 'bacula-configuration';
	public const PLUGIN_TYPE_NOTIFICATION = 'notification';

	/**
	 * Plugin script file pattern.
	 */
	protected const PLUGIN_FILE_PATTERN = '*.php';


	/**
	 * Stores plugin config content.
	 */
	private $config;

	/**
	 * Stores plugin list.
	 */
	private $plugins;

	public function init($config)
	{
		if (is_null($this->plugins)) {
			$this->plugins = $this->getPlugins();
		}
	}

	/**
	 * Get plugins config.
	 *
	 * @param string $section specific config settings section
	 * @return array plugins config
	 */
	public function getConfig($section = null): array
	{
		$config = [];
		if (is_null($this->config)) {
			$config_file_path = $this->getConfigFilePath();
			$config_file_format = $this->getConfigFileFormat();
			$config = $this->readConfig(
				$config_file_path,
				$config_file_format
			);
			$this->prepareConfigUse($config);
			$this->config = $config;
		}
		if (is_string($section)) {
			$config = key_exists($section, $this->config) ? $this->config[$section] : [];
		} else {
			$config = $this->config;
		}
		return $config;
	}

	/**
	 * Set plugins config.
	 *
	 * @param array $config config
	 * @return bool true if config saved successfully, otherwise false
	 */
	public function setConfig(array $config): bool
	{
		$this->prepareConfigSave($config);
		$config_file_path = $this->getConfigFilePath();
		$config_file_format = $this->getConfigFileFormat();
		$result = $this->writeConfig(
			$config,
			$config_file_path,
			$config_file_format
		);
		if ($result === true) {
			$this->config = null;
		}
		return $result;
	}

	/**
	 * Prepare settings config to use.
	 *
	 * @param int $config config reference
	 */
	private function prepareConfigUse(array &$config): void
	{
		foreach ($config as $name => &$settings) {
			$settings['name'] = $name;
			if (!key_exists($settings['plugin'], $this->plugins)) {
				// settings exist but plugin is not installed
				continue;
			}
			for ($i = 0; $i < count($this->plugins[$settings['plugin']]['parameters']); $i++) {
				foreach ($settings['parameters'] as $sparam => &$svalue) {
					$pprops = $this->plugins[$settings['plugin']]['parameters'][$i];
					if ($pprops['name'] != $sparam) {
						continue;
					}
					// Prepare data for specific field types
					if ($pprops['type'] == 'array_multiple') {
						$svalue = !empty($svalue) ? explode(',', $svalue) : [];
					} elseif ($pprops['type'] == 'string_long') {
						$svalue = str_replace(['\\n', '\\r'], ["\n", "\r"], $svalue);
					}
				}
			}
		}
	}

	/**
	 * Prepare settings config to save.
	 *
	 * @param int $config config reference
	 */
	private function prepareConfigSave(array &$config): void
	{
		foreach ($config as $name => &$settings) {
			if (!key_exists($settings['plugin'], $this->plugins)) {
				// settings exist but plugin is not installed
				continue;
			}
			for ($i = 0; $i < count($this->plugins[$settings['plugin']]['parameters']); $i++) {
				foreach ($settings['parameters'] as $sparam => &$svalue) {
					$pprops = $this->plugins[$settings['plugin']]['parameters'][$i];
					if ($pprops['name'] != $sparam) {
						continue;
					}
					// Prepare data for specific field types
					if ($pprops['type'] == 'array_multiple') {
						$svalue = implode(',', $svalue);
					} elseif ($pprops['type'] == 'string_long') {
						$svalue = str_replace(["\n", "\r"], ['\\n', '\\r'], $svalue);
					}
				}
			}
		}
	}

	/**
	 * Check if plugin settings exists.
	 *
	 * @param string $name settings name
	 * @return bool true if setting exists, otherwise false
	 */
	public function isPluginSettings(string $name): bool
	{
		$config = $this->getConfig();
		return key_exists($name, $config);
	}

	/**
	 * Set single plugin settings.
	 *
	 * @param string $name settings name
	 * @param array $settings plugin settings
	 * @return bool true if config saved successfully, otherwise false
	 */
	public function setPluginSettings(string $name, array $settings): bool
	{
		$config = $this->getConfig();
		$config[$name] = $settings;
		$result = $this->setConfig($config);
		return $result;
	}

	/**
	 * Remove single plugin settings.
	 *
	 * @param string $name settings name
	 * @return bool true if config saved successfully, otherwise false
	 */
	public function removePluginSettings($name)
	{
		$config = $this->getConfig();
		if (key_exists($name, $config)) {
			unset($config[$name]);
		}
		$result = $this->setConfig($config);
		return $result;
	}

	/**
	 * Get installed plugin list with properties.
	 *
	 * @param string $ftype plugin type
	 * @return array plugin list
	 */
	public function getPlugins(?string $ftype = null): array
	{
		$plugins = [];
		$plugin_dir_path = $this->getPluginDirPath();
		$path = Prado::getPathOfNamespace($plugin_dir_path);
		$pattern = $path . DIRECTORY_SEPARATOR . static::PLUGIN_FILE_PATTERN;
		$iterator = new \GlobIterator($pattern);
		while ($iterator->valid()) {
			$plugin = $iterator->current()->getFilename();
			$ppath = $iterator->current()->getPathname();
			require_once($ppath);
			$cls = rtrim($plugin, '.php');
			$name = $cls::getName();
			$version = $cls::getVersion();
			$type = $cls::getType();
			$parameters = $cls::getParameters();
			if (is_string($ftype) && $type !== $ftype) {
				// filter by type
				continue;
			}
			$plugins[$cls] = [
				'cls' => $cls,
				'path' => $ppath,
				'name' => $name,
				'version' => $version,
				'type' => $type,
				'parameters' => $parameters
			];
			$iterator->next();
		}
		return $plugins;
	}

	/**
	 * Get configuration file path in dot notation.
	 *
	 * @return string config file path
	 */
	abstract protected function getConfigFilePath(): string;

	/**
	 * Get configuration file format.
	 *
	 * @return string config file format
	 */
	abstract protected function getConfigFileFormat(): string;

	/**
	 * Get directory to store plugins.
	 *
	 * @return string plugin directory path
	 */
	abstract protected function getPluginDirPath(): string;
}
