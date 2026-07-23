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

use Bacularis\Common\Modules\Logging;
use Bacularis\Common\Modules\Shell\BaculaConsoleActionBase;
use Bacularis\Common\Modules\ConsoleMenuItem;

/**
 * Bacula console restore command action.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class BaculaConsoleRestoreAction extends BaculaConsoleActionBase
{
	/**
	 * Bacula resources to set in console restore preparation.
	 */
	private const PARAM_MENU_LIST = [
		'restore_client' => [
			'item_no' => 5,
			'type' => ConsoleMenuItem::ITEM_TYPE_SELECT
		],
		'where' => [
			'item_no' => 9,
			'type' => ConsoleMenuItem::ITEM_TYPE_INPUT
		],
		'strip_prefix' => [
			'item_no' => 1,
			'type' => ConsoleMenuItem::ITEM_TYPE_INPUT
		],
		'add_prefix' => [
			'item_no' => 2,
			'type' => ConsoleMenuItem::ITEM_TYPE_INPUT
		],
		'add_suffix' => [
			'item_no' => 3,
			'type' => ConsoleMenuItem::ITEM_TYPE_INPUT
		],
		'regex_where' => [
			'item_no' => 4,
			'type' => ConsoleMenuItem::ITEM_TYPE_INPUT
		],
		'file_relocation_start' => [
			'item_no' => 10,
			'type' => ConsoleMenuItem::ITEM_TYPE_OPTION
		],
		'file_relocation_end' => [
			'item_no' => 6,
			'type' => ConsoleMenuItem::ITEM_TYPE_OPTION
		],
		'replace' => [
			'item_no' => 11,
			'type' => ConsoleMenuItem::ITEM_TYPE_SELECT,
			'map' => [
				'never' => 'Never',
				'ifnewer' => 'IfNewer',
				'ifolder' => 'IfOlder',
				'always' => 'Always'
			]
		]
	];

	/**
	 * Main action.
	 */
	protected $action = 'restore';

	/**
	 * Action methods.
	 */
	protected $methods = ['start', 'command', 'output'];

	/**
	 * Command required parameters.
	 */
	protected $parameters = [
		[
			'session-id' => 'session-id',
			'job-id' => 'job-id',
			'client' => 'client',
			'fileset' => 'fileset',
			'restorejob' => 'restorejob'
		],
		[
			'session-id' => 'session-id',
			'command' => 'command'
		],
		[
			'session-id' => 'session-id',
			'command-id' => 'command-id'
		]
	];

	/**
	 * Command optional parameters.
	 */
	protected $optional = [
		[],
		[
			'async' => '0/1'
		],
		[
			'timeout' => 'timeout_ms'
		]
	];

	/**
	 * Command descriptions.
	 */
	protected $description = [
		'Restore commands',
		'Start restore with Bacula console.',
		'Execute restore Bacula console command.',
		'Get restore command output.'
	];

	/**
	 * All command parameters.
	 */
	public $params = [];

	public function __construct()
	{
		parent::__construct();
		for ($i = 0; $i < count($this->parameters); $i++) {
			$this->parameters[$i] = array_merge(
				parent::BASE_PARAMETERS,
				$this->parameters[$i]
			);
		}
		for ($i = 0; $i < count($this->optional); $i++) {
			$this->optional[$i] = array_merge(
				parent::BASE_OPTIONAL,
				$this->optional[$i]
			);
		}
	}

	/**
	 * Start restore session action.
	 *
	 * @param array $args command parameters
	 */
	public function actionStart($args): bool
	{
		$this->waitOnSession($this->params['session-id'], 10000);

		$command = [
			'restore',
			'jobid="' . $this->params['job-id'] . '"',
			'client="' . $this->params['client'] . '"',
			'fileset="' . $this->params['fileset'] . '"',
			'restorejob="' . $this->params['restorejob'] . '"'
			//'select' - NOTE: select takes latest jobids
		];
		$cid = $this->generateCommandId();
		$result = $this->executeConsole(
			$this->params['session-id'],
			$cid,
			$command
		);
		if ($result) {
			$out = [
				'session-id' => $this->params['session-id'],
				'command-id' => $cid
			];
			echo json_encode($out);
		}
		return $result;
	}

	/**
	 * Restore session command.
	 *
	 * @param array $args command parameters
	 */
	public function actionCommand($args): bool
	{
		$command = [];
		$result = [];
		$async = (isset($this->params['async']) && $this->params['async'] == 1);
		switch ($this->params['command']) {
			case 'ls': {
				$command = ['ls'];
				break;
			}
			case 'cd': {
				$command = ['cd', '"' . $this->params['path'] . '"'];
				break;
			}
			case 'mark': {
				$command = ['mark', '"' . $this->params['path'] . '"'];
				break;
			}
			case 'markall': {
				$command = ['mark', '*'];
				break;
			}
			case 'done': {
				$command = ['done'];
				break;
			}
			case 'quit': {
				$command = ['quit'];
				break;
			}
			case 'yes': {
				$command = ['yes'];
				break;
			}
			case 'modify': {
				$result = $this->modifyItem(
					$this->params['session-id'],
					$this->params['parameter'],
					$this->params['value']
				);
				break;
			}
			case 'select': {
				$result = $this->selectItem(
					$this->params['session-id'],
					$this->params['parameter'],
					$this->params['value'],
				);
				break;
			}
		}
		if ($command) {
			$result = $this->runCommand(
				$this->params['session-id'],
				$command,
				$async
			);
			if ($result) {
				echo $result;
			}
		}
		return ($result ? true : false);
	}

	/**
	 * Run restore command.
	 *
	 * @param string $sid session identifier
	 * @param array $command command to execute
	 * @param bool $async determines if command is executed asynchronously or not
	 * @return string command result
	 */
	private function runCommand(string $sid, array $command, bool $async = false): string
	{
		$cid = $this->generateCommandId();
		$result = $this->executeConsole(
			$sid,
			$cid,
			$command
		);

		if ($async) {
			// Asynchronous execution - result available via another request
			$response = [
				'session-id' => $this->params['session-id'],
				'command-id' => $cid
			];
			$result = json_encode($response);
		} else {
			// Synchronous execution - result available immediately
			$result = $this->waitOnResult(
				$this->params['session-id'],
				$cid,
				25000
			);
			if (is_null($result)) {
				$result = '';
			}
		}
		return $result;
	}

	/**
	 * Modify restore command.
	 *
	 * @param string $sid session identifier
	 * @param string $param command parameter name
	 * @param string $value command parameter value
	 * @return string modify command outuput
	 */
	private function modifyItem(string $sid, string $param, string $value): string
	{
		$cid = $this->generateCommandId();
		$this->executeConsole(
			$sid,
			$cid,
			["mod"]
		);
		$result = $this->waitOnResult(
			$this->params['session-id'],
			$cid,
			3000
		);
		if (!$result) {
			return '';
		}

		return $this->selectItem($sid, $param, $value, $result);
	}

	/**
	 * Select item from the restore console menu.
	 *
	 * @param string $sid session identifier
	 * @param string $param command aprameter name
	 * @param string $value command parameter value
	 * @param string $output modify command outuput with menu items
	 * @return string command result (output)
	 */
	private function selectItem(string $sid, string $param, string $value, string $output = ''): string
	{
		$cmd = [];
		$menu_item = self::PARAM_MENU_LIST[$param] ?? '';
		$cid = $this->generateCommandId();

		switch ($menu_item['type']) {
			case ConsoleMenuItem::ITEM_TYPE_INPUT: {
				$cmd[] = $menu_item['item_no'];
				$cmd[] = $value;
				break;
			}
			case ConsoleMenuItem::ITEM_TYPE_SELECT: {
				$output = $this->executeMenuCommand($sid, $cid, $menu_item['item_no']);
				if (key_exists('map', $menu_item) && key_exists($value, $menu_item['map'])) {
					$value = $menu_item['map'][$value];
				}
				$item_number = $this->findMenuNumber($output, $value);
				$cmd[] = $item_number;
				break;
			}
			case ConsoleMenuItem::ITEM_TYPE_OPTION: {
				$cmd[] = $menu_item['item_no'];
				break;
			}
		}

		$result = '';
		if ($cmd) {
			$command = implode("\n", $cmd);
			$result = $this->executeMenuCommand($sid, $cid, $command);
		}
		return $result;
	}

	/**
	 * Execute single menu command.
	 *
	 * @param string $sid session identifier
	 * @param string $cid command identifier
	 * @param string $command to execute
	 * @return null|string command output or null on error
	 */
	private function executeMenuCommand($sid, $cid, $command): ?string
	{
		$this->executeConsole(
			$sid,
			$cid,
			[$command]
		);
		$result = $this->waitOnResult(
			$this->params['session-id'],
			$cid,
			3000
		);

		Logging::log(
			Logging::CATEGORY_EXECUTE,
			"Select restore menu option '{$command}' Output=" . var_export($result, true)
		);

		return $result;
	}

	/**
	 * Find menu item on the menu item list.
	 *
	 * @param string $output menu item list output
	 * @param string $value value to find
	 * @return int found item number (-1 if item not found)
	 */
	private function findMenuNumber(string $output, string $value): int
	{
		$number = -1;
		$out = explode(PHP_EOL, $output);
		for ($i = 0; $i < count($out); $i++) {
			if (preg_match("/^\s+(?P<number>\d+):\s{$value}$/", $out[$i], $match) === 1) {
				$number = (int) $match['number'];
				break;
			}
		}
		return $number;
	}

	/**
	 * Get command output.
	 *
	 * @param array $args command parameters
	 * @return bool true on success, false otherwise
	 */
	public function actionOutput($args): bool
	{
		$result = $this->waitOnResult(
			$this->params['session-id'],
			$this->params['command-id'],
			($this->params['timeout'] ?? 0)
		);
		if ($result) {
			echo $result;
		}
		return is_string($result);
	}

	/**
	 * Help command renderer.
	 *
	 * @param string $cmd command
	 */
	public function renderHelpCommand($cmd): void
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
