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

namespace Bacularis\Common\Modules\Shell\Actions;

use Bacularis\Common\Modules\Shell\BShellAction;
Use Prado\Shell\TShellWriter;

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
	protected $parameters = [['plugin', 'config'], ['plugin'], ['config']];
	protected $optional = [[], [], []];
	protected $description = [
		'Plugin commands',
		'List all single command lines used for backup.',
		'Run backup command',
		'Run restore command'
	];
	public $params = [];

	/**
	 * List plugin commands action.
	 *
	 * @return bool true it is always valid
	 */
	public function actionList()
	{
		$plugins = $this->Application->getModule('plugins');
		echo $plugins->getCommand($this->params);
		return true;
	}

	/**
	 * Backup plugin action.
	 *
	 * @return bool true on success, otherwise false
	 */
	public function actionBackup()
	{
		$plugins = $this->Application->getModule('plugins');
		$plugin = $plugins->getPluginByName($this->params['plugin-name']);
		return $plugin->doBackup($this->params);
	}

	/**
	 * Restore plugin action.
	 *
	 * @return bool true on success, otherwise false
	 */
	public function actionRestore()
	{
		$plugins = $this->Application->getModule('plugins');
		$plugin = $plugins->getPluginByName($this->params['plugin-name']);
		$params = $this->params;
		if (key_exists('where', $this->params) && strpos($this->params['where'], '#') === 0) {
			// add config parameters on restore
			$where = base64_decode(ltrim($this->params['where'], '#'));
			$config = json_decode($where, true);
			$params = array_merge($this->params, $config);
			$params['where'] = '/'; // it means restore to original location (not local file restore)
		}
		return $plugin->doRestore($params);
	}

	/**
	 * Help command renderer.
	 *
	 * @param string $cmd command
	 */
	public function renderHelpCommand($cmd)
	{
		$this->output_writer->write("\nUsage: ");
		$this->output_writer->writeLine("plugin {$this->action}/<action>", [TShellWriter::BLUE, TShellWriter::BOLD]);
		$this->output_writer->writeLine("\nexample: plugin {$this->action}/{$this->methods[0]}\n");
		$this->output_writer->writeLine("The following actions are available:");
		$this->output_writer->writeLine();
		foreach ($this->methods as $i => $method) {
			$params = [];
			if ($this->parameters[$i]) {
				$parameters = is_array($this->parameters[$i]) ? $this->parameters[$i] : [$this->parameters[$i]];
				foreach ($parameters as $v) {
					$params[] = '<' . $v . '>';
				}
			}
			$parameters = implode(' ', $params);
			$options = [];
			if ($this->optional[$i]) {
				$optional = is_array($this->optional[$i]) ? $this->optional[$i] : [$this->optional[$i]];
				foreach ($optional as $v) {
					$options[] = '[' . $v . ']';
				}
			}
			$optional = (strlen($parameters) ? ' ' : '') . implode(' ', $options);

			$description = $this->getWriter()->wrapText($this->description[$i + 1], 10);
			$parameters = $this->getWriter()->format($parameters, [TShellWriter::BLUE, TShellWriter::BOLD]);
			$optional = $this->getWriter()->format($optional, [TShellWriter::BLUE]);
			$description = $this->getWriter()->format($description, TShellWriter::DARK_GRAY);

			$this->output_writer->write('  ');
			$this->output_writer->writeLine($this->action . '/' . $method . ' ' . $parameters . $optional, [TShellWriter::BLUE, TShellWriter::BOLD]);
			$this->output_writer->writeLine('         ' . $description);
			$this->output_writer->writeLine();
		}
	}
}
