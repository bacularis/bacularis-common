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

namespace Bacularis\Common\Portlets;

use Prado\Prado;
use Bacularis\Common\Modules\AuditLog;
use Bacularis\Common\Modules\IBacularisActionPlugin;
use Bacularis\Common\Modules\PluginConfigBase;
use Bacularis\Common\Modules\PluginConfigParameter;
use Bacularis\Common\Portlets\PortletTemplate;

/**
 * Plugin list control.
 * It enables to manage Bacularis plugins.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Control
 */
class PluginList extends PortletTemplate
{
	public function onLoad($param)
	{
		parent::onLoad($param);
		if ($this->getPage()->IsPostBack || $this->getPage()->IsCallBack) {
			return;
		}
		$this->setPluginNames();
	}

	/**
	 * Plugin settings list loader.
	 *
	 * @param TCallback $sender sender object
	 * @param TCallbackEventParameter $param callback parameter object
	 */
	public function loadPluginSettingsList($sender, $param)
	{
		$plugin_config = $this->getModule('plugin_config');
		$plugins = $plugin_config->getConfig();
		$cb = $this->getPage()->getCallbackClient();
		$cb->callClientFunction(
			'oPlugins.load_plugin_settings_list_cb',
			[$plugins]
		);
	}

	/**
	 * Plugin list loader.
	 *
	 * @param TCallback $sender sender object
	 * @param TCallbackEventParameter $param callback parameter object
	 */
	public function loadPluginPluginsList($sender, $param)
	{
		$plugin_config = $this->getModule('plugin_config');
		$plugins = $plugin_config->getPlugins();
		$cb = $this->getPage()->getCallbackClient();
		$cb->callClientFunction(
			'oPlugins.load_plugin_plugins_list_cb',
			[$plugins]
		);
	}

	/**
	 * Load plugin parameters.
	 *
	 * @param TCallback $sender sender object
	 * @param TCallbackEventParameter $param callback parameter object
	 */
	public function loadPluginParameterList($sender, $param)
	{
		$setting = $param->getCallbackParameter();
		if (!isset($setting->name) || !isset($setting->plugin)) {
			return;
		}
		$plugin_config = $this->getModule('plugin_config');
		$plugins = $plugin_config->getPlugins(null, $setting->plugin);
		$this->addPluginSettingsResources($plugins);
		$cb = $this->getPage()->getCallbackClient();
		$cb->callClientFunction(
			'oPlugins.load_plugin_settings_form_cb',
			[$setting->name, $setting->plugin, $plugins]
		);
	}

	/**
	 * Add to plugin settings available resources.
	 * Resources mean both Bacula resources (such as 'Job') and Bacularis resources (such as 'api_host').
	 * They are values that can be used in the list type elements.
	 */
	private function addPluginSettingsResources(&$plugins)
	{
		foreach ($plugins as $plugin_name => &$setting) {
			if (!isset($setting['parameters']) || !is_array($setting['parameters'])) {
				continue;
			}
			for ($i = 0; $i < count($setting['parameters']); $i++) {
				if (in_array($setting['parameters'][$i]['type'], [PluginConfigParameter::TYPE_ARRAY_MULTIPLE, PluginConfigParameter::TYPE_ARRAY_MULTIPLE_ORDERED])) {
					if (!key_exists('resource', $setting['parameters'][$i])) {
						continue;
					}
					switch ($setting['parameters'][$i]['resource']) {
						case 'api_host': {
							$setting['parameters'][$i]['data'] = $this->getAPIHostData();
							break;
						}
						case 'user': {
							$setting['parameters'][$i]['data'] = $this->getWebUserData();
							break;
						}
						case 'job_action': {
							$setting['parameters'][$i]['data'] = $this->getPluginActionSettingsData('Job');
							break;
						}
					}
				}
			}
		}
	}

	/**
	 * Get web user list.
	 *
	 * @return array web user list
	 */
	private function getWebUserData(): array
	{
		$web_user = $this->getModule('user_config');
		$users = $web_user->getConfig();
		return array_keys($users);
	}

	/**
	 * Get API host list.
	 *
	 * @return array API host list
	 */
	private function getAPIHostData(): array
	{
		$host_config = $this->getModule('host_config');
		$hosts = $host_config->getConfig();
		$api_hosts = array_keys($hosts);
		$api_hosts = array_filter(
			$api_hosts,
			fn ($host) => $host !== $host_config::MAIN_CATALOG_HOST
		);
		return array_values($api_hosts);
	}

	/**
	 * Get action settings list by plugin resource.
	 *
	 * @param string $resource type
	 * @return array plugin action setting names
	 */
	private function getPluginActionSettingsData(string $resource): array
	{
		$plugin_config = $this->getModule('plugin_config');
		$settings = $plugin_config->getPluginSettingsByType(
			$plugin_config::PLUGIN_TYPE_ACTION
		);
		$action_settings = [];
		$plugin_manager = $this->getModule('plugin_manager');
		foreach ($settings as $name => $props) {
			$res = $plugin_manager->callPluginActionByName(
				$props['plugin'],
				'getResource'
			);
			if ($res != $resource) {
				continue;
			}
			$action_settings[$name] = sprintf(
				'(%s) %s',
				$props['plugin'],
				$name
			);
		}
		return $action_settings;
	}

	/**
	 * Set plugin names in the plugin combobox.
	 */
	public function setPluginNames()
	{
		$data = ['none' => Prado::localize('Select plugin')];
		$plugin_config = $this->getModule('plugin_config');
		$plugins = $plugin_config->getPlugins();
		uasort($plugins, fn ($a, $b) => strnatcmp($a['type'], $b['type']));
		foreach ($plugins as $cls => $prop) {
			$data[$cls] = sprintf('[%s] %s', $prop['type'], $prop['name']);
		}
		$this->PluginSettingsPluginName->DataSource = $data;
		$this->PluginSettingsPluginName->dataBind();
	}

	/**
	 * Save plugin settings.
	 *
	 * @param TCallback $sender sender object
	 * @param TCallbackEventParameter $param callback parameter object
	 */
	public function savePluginSettingsForm($sender, $param)
	{
		$fields = $param->getCallbackParameter();
		if (!is_object($fields)) {
			return false;
		}
		$fields = (array) $fields;
		$name = $this->PluginSettingsName->Text;
		$enabled = $this->PluginSettingsEnabled->Checked ? '1' : '0';
		$plugin_name = $this->PluginSettingsPluginName->Text;
		$settings = [
			'plugin' => $plugin_name,
			'enabled' => $enabled,
			'parameters' => $fields
		];
		$cb = $this->getPage()->getCallbackClient();
		$plugin_config = $this->getModule('plugin_config');
		$win_mode = $this->PluginSettingsWindowMode->Value;
		if ($win_mode == 'add' && $plugin_config->isPluginSettings($name)) {
			$cb->show('plugin_list_plugin_settings_exists');
			return false;
		}

		$result = $plugin_config->setPluginSettings($name, $settings);
		$audit = $this->getModule('audit');
		if ($result === true) {
			$cb->callClientFunction(
				'oPlugins.show_plugin_settings_window',
				[false]
			);
			$this->loadPluginPluginsList($sender, $param);
			if (is_object($audit)) {
				$audit->audit(
					AuditLog::TYPE_INFO,
					AuditLog::CATEGORY_APPLICATION,
					"Save plugin settings. Plugin: {$plugin_name}, Settings: {$name}"
				);
			}
		} else {
			$cb->update(
				'plugin_list_plugin_settings_error',
				'Error while saving plugin form'
			);
			$cb->show('plugin_list_plugin_settings_error');
			if (is_object($audit)) {
				$audit->audit(
					AuditLog::TYPE_ERROR,
					AuditLog::CATEGORY_APPLICATION,
					"Error while saving plugin settings. Plugin: {$plugin_name}, Settings: {$name}"
				);
			}
		}
	}

	/**
	 * Remove plugin settings.
	 *
	 * @param TCallback $sender sender object
	 * @param TCallbackEventParameter $param callback parameter object
	 */
	public function removePluginSettings($sender, $param)
	{
		$settings = explode('|', $param->getCallbackParameter());
		$plugin_config = $this->getModule('plugin_config');
		for ($i = 0; $i < count($settings); $i++) {
			$result = $plugin_config->removePluginSettings($settings[$i]);
			$audit = $this->getModule('audit');
			if ($result) {
				if (is_object($audit)) {
					$audit->audit(
						AuditLog::TYPE_INFO,
						AuditLog::CATEGORY_APPLICATION,
						"Remove plugin settings. Settings: {$settings[$i]}"
					);
				}
			} else {
				if (is_object($audit)) {
					$audit->audit(
						AuditLog::TYPE_ERROR,
						AuditLog::CATEGORY_APPLICATION,
						"Error while removing plugin settings. Settings: {$settings[$i]}"
					);
				}
				break;
			}
		}

		// Refresh plugin list
		$this->loadPluginSettingsList($sender, $param);
	}
}
