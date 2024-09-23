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
	 * Initialize API plugins.
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
	 * Run plugin actions on config event.
	 *
	 * @param string $ftype plugin type
	 * @param string $method method to call
	 * @param array $args method parameters
	 */
	public function callPluginAction(string $ftype, string $method, ...$args): void
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
}
