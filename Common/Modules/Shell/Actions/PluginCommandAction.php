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
			$where = base64_decode(ltrim($this->params['where'], '#'));
			$config = json_decode($where, true);
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
