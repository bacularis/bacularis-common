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

use Prado\Prado;

/**
 * Base plugin module manager.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class PluginManagerBase extends CommonModule
{
	/**
	 * Config plugin instance list.
	 */
	private static $plugins = [];

	/**
	 * Initialize plugins.
	 *
	 * @param null|string $ftype plugin type or null (all plugins)
	 */
	protected function initPlugins(?string $ftype = null): void
	{
		$plugin_config = $this->getModule('plugin_config');
		$plugins = $plugin_config->getPlugins($ftype);
		$settings = $plugin_config->getConfig();
		foreach ($plugins as $name => $params) {
			foreach ($settings as $setting => $props) {
				if ($props['plugin'] != $name) {
					continue;
				}
				if ($props['enabled'] != 1) {
					// not enabled, skip it
					continue;
				}
				$obj = Prado::createComponent($name, $props);
				self::$plugins[] = $obj;
			}
		}
	}

	/**
	 * Run plugin actions by plugin type on config event.
	 *
	 * @param string $ftype plugin type
	 * @param string $method method to call
	 * @param array $args method parameters
	 */
	public function callPluginActionByType(string $ftype, string $method, ...$args): void
	{
		for ($i = 0; $i < count(self::$plugins); $i++) {
			if (self::$plugins[$i]->getType() != $ftype) {
				continue;
			}
			if (!method_exists(self::$plugins[$i], $method)) {
				continue;
			}
			call_user_func_array([self::$plugins[$i], $method], $args);
		}
	}

	/**
	 * Run plugin actions by plugin name on config event.
	 *
	 * @param string $name plugin name
	 * @param string $method method to call
	 * @param array $args method parameters
	 * @return mixed callback result or null if plugin not found or if callback returned it
	 */
	public function callPluginActionByName(string $name, string $method, ...$args)
	{
		$result = null;
		for ($i = 0; $i < count(self::$plugins); $i++) {
			if (get_class(self::$plugins[$i]) != $name) {
				continue;
			}
			if (!method_exists(self::$plugins[$i], $method)) {
				continue;
			}
			$result = call_user_func_array([self::$plugins[$i], $method], $args);
		}
		return $result;
	}

	/**
	 * Run plugin actions by plugin setting name on config event.
	 *
	 * @param string $name plugin setting name
	 * @param string $method method to call
	 * @param array $args method parameters
	 * @return mixed callback result or null if plugin not found or if callback returned it
	 */
	public function callPluginActionBySettingName(string $name, string $method, ...$args)
	{
		$result = null;
		$plugin_config = $this->getModule('plugin_config');
		$setting = $plugin_config->getConfig($name);
		if (count($setting) == 0) {
			return $result;
		}
		for ($i = 0; $i < count(self::$plugins); $i++) {
			if (get_class(self::$plugins[$i]) != $setting['plugin']) {
				continue;
			}
			if (!method_exists(self::$plugins[$i], $method)) {
				continue;
			}
			$result = call_user_func_array([self::$plugins[$i], $method], $args);
		}
		return $result;
	}
}
