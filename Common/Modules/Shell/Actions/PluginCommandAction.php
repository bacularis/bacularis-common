<?php
/*
 * Bacularis - Bacula web interface
 *
 * Copyright (C) 2021-2026 Marcin Haba
 *
 * The main author of Bacularis is Marcin Haba, with contributors, whose
 * full list can be found in the AUTHORS file.
 *
 * You may use this file and others of this release according to the
 * license defined in the LICENSE file, which includes the Affero General
 * Public License, v3.0 ("AGPLv3") and some additional permissions and
 * terms pursuant to its AGPLv3 Section 7.
 */

namespace Bacularis\Common\Modules\Shell\Actions;

use Bacularis\Common\Modules\PluginConfigParameter;
use Bacularis\Common\Modules\Shell\BShellAction;
use Prado\Shell\TShellWriter;

/**
 * Bacularis plugin command action.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class PluginCommandAction extends BShellAction
{
	protected $action = 'command';
	protected $methods = ['list', 'backup', 'restore'];
	protected $parameters = [
		['plugin-name' => 'name', 'plugin-config' => 'name'],
		['plugin-name' => 'name'],
		['plugin-config' => 'name']
	];
	protected $optional = [[], [], []];
	protected $description = [
		'Plugin commands',
		'List all single command lines used for backup.',
		'Run backup command',
		'Run restore command'
	];
	public $params = [];


	/**
	 * Run pre-run action.
	 *
	 * @param object $plugin plugin instance
	 */
	private function preRunAction(object $plugin): void
	{
		if (method_exists($plugin, 'initialize')) {
			$plugin->initialize($this->params);
		}
	}

	/**
	 * Check a decoded restore parameter against its plugin field definition.
	 *
	 * @param array $definition plugin parameter definition
	 * @param mixed $value decoded parameter value
	 * @return bool true if the value matches the declared type, otherwise false
	 */
	private function isValidRestoreParameter(array $definition, $value): bool
	{
		$type = $definition['type'] ?? '';
		if (in_array($type, [PluginConfigParameter::TYPE_STRING, PluginConfigParameter::TYPE_STRING_LONG, 'password'], true)) {
			return is_string($value) && preg_match('/[\x00-\x1F\x7F]/', $value) !== 1;
		}
		if ($type === PluginConfigParameter::TYPE_INTEGER) {
			return is_int($value) || (is_string($value) && preg_match('/^\d+$/D', $value) === 1);
		}
		if ($type === PluginConfigParameter::TYPE_BOOLEAN) {
			return in_array($value, [true, false, 0, 1, '0', '1'], true);
		}
		if ($type === PluginConfigParameter::TYPE_ARRAY) {
			$data = $definition['data'] ?? [];
			return in_array($value, $data);
		}
		if (in_array($type, [PluginConfigParameter::TYPE_ARRAY_MULTIPLE, PluginConfigParameter::TYPE_ARRAY_MULTIPLE_ORDERED], true)) {
			if (!is_array($value)) {
				return false;
			}
			$data = $definition['data'] ?? [];
			for ($i = 0; $i < count($value); $i++) {
				if (!in_array($value[$i], $data)) {
					return false;
				}
			}
			return true;
		}
		return false;
	}

	/**
	 * Filter decoded restore parameters to fields explicitly exposed by the
	 * plugin for restore and validate their declared types.
	 *
	 * @param object $plugin plugin instance
	 * @param array $config decoded restore parameters
	 * @return null|array filtered parameters or null if a value is invalid
	 */
	private function filterRestoreParameters(object $plugin, array $config): ?array
	{
		$plugin_class = get_class($plugin);
		$categories = $plugin_class::getRestoreParameterCategories();
		$definitions = $plugin_class::getParameters();
		$allowed = [];
		for ($i = 0; $i < count($definitions); $i++) {
			$parameter_categories = $definitions[$i]['category'] ?? [];
			$is_restore_parameter = count($parameter_categories) === 0 || count(array_intersect($parameter_categories, $categories)) > 0;
			if ($is_restore_parameter) {
				$allowed[$definitions[$i]['name']] = $definitions[$i];
			}
		}

		$params = [];
		foreach ($config as $key => $value) {
			if (!is_string($key) || !key_exists($key, $allowed)) {
				continue;
			}
			if (!$this->isValidRestoreParameter($allowed[$key], $value)) {
				return null;
			}
			$params[$key] = $value;
		}
		return $params;
	}

	/**
	 * List plugin commands action.
	 *
	 * @param array $args command parameters
	 * @return bool true it is always valid
	 */
	public function actionList($args)
	{
		$plugins = $this->Application->getModule('plugins');
		echo $plugins->getCommand($this->params);
		return true;
	}

	/**
	 * Backup plugin action.
	 *
	 * @param array $args command parameters
	 * @return bool true on success, otherwise false
	 */
	public function actionBackup($args)
	{
		$plugins = $this->Application->getModule('plugins');
		$plugin = $plugins->getPluginByName($this->params['plugin-name']);
		$plugin->addDefaultParameterValues($this->params);
		$this->preRunAction($plugin);
		return $plugin->doBackup($this->params);
	}

	/**
	 * Restore plugin action.
	 *
	 * @param array $args command parameters
	 * @return bool true on success, otherwise false
	 */
	public function actionRestore($args)
	{
		$plugins = $this->Application->getModule('plugins');
		$plugin = $plugins->getPluginByName($this->params['plugin-name']);
		$plugin->addDefaultParameterValues($this->params);
		$params = $this->params;
		if (key_exists('where', $this->params) && strpos($this->params['where'], '#') === 0) {
			// add config parameters on restore
			$encoded_where = substr($this->params['where'], 1);
			$where = base64_decode($encoded_where, true);
			if ($where === false) {
				return false;
			}
			$config = json_decode($where, true);
			if (!is_array($config)) {
				return false;
			}
			$config = $this->filterRestoreParameters($plugin, $config);
			if (is_null($config)) {
				return false;
			}
			$params = array_merge($this->params, $config);
			$params['where'] = '/'; // it means restore to original location (not local file restore)
		}
		$this->preRunAction($plugin);
		return $plugin->doRestore($params);
	}

	/**
	 * Help command renderer.
	 *
	 * @param string $cmd command
	 */
	public function renderHelpCommand($cmd)
	{
		$this->showHelpCommand(
			$this->action,
			$this->methods,
			$this->parameters,
			$this->optional,
			$this->description,
			$cmd
		);
	}
}
