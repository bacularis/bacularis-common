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

use Bacularis\Common\Modules\Shell\BaculaConsoleActionBase;

/**
 * Generic Bacula console command action.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class BaculaConsoleAction extends BaculaConsoleActionBase
{
	/**
	 * Main action.
	 */
	protected $action = 'console';

	/**
	 * Action methods.
	 */
	protected $methods = ['start', 'stop'];

	/**
	 * Required command parameters.
	 */
	protected $parameters = [
		[
			'session-id' => 'session-id',
			'director' => 'director',
			'bconsole-bin' => 'path',
			'bconsole-conf' => 'path'
		],
		[
			'session-id' => 'session-id'
		]
	];

	/**
	 * Optional command parameters.
	 */
	protected $optional = [
		[
			'use-sudo' => null,
			'sudo-user' => 'user',
			'sudo-group' => 'group'
		],
		[
		]
	];

	/**
	 * Command descriptions.
	 */
	protected $description = [
		'Bacula console commands',
		'Start session using Bacula console.',
		'End session using Bacula console.',
	];

	/**
	 * All command parameters with values.
	 */
	public $params = [];

	/**
	 * Start Bacula console session action.
	 *
	 * @param array $args command parameters
	 * @return bool true it is always valid
	 */
	public function actionStart($args): bool
	{
		$sudo_prop = [
			'use_sudo' => ($this->params['use-sudo'] ?? false),
			'user' => $this->params['sudo-user'] ?? '',
			'group' => $this->config['sudo-group'] ?? ''
		];
		$bconsole = $this->Application->getModule('bacula-console');
		$bconsole->setEnvironmentParams(
			$this->params['bconsole-bin'],
			$this->params['bconsole-conf'],
			$sudo_prop
		);
		$director = null;
		$cmd = [];
		$command = $bconsole->prepareBconsoleCommand(
			$director,
			$cmd,
			$bconsole::PTYPE_OPEN_CMD
		);
		$result = $this->runConsole(
			$this->params['session-id'],
			$command['cmd']
		);
		return $result;
	}

	/**
	 * Stop Bacula console session action.
	 *
	 * @param array $args command parameters
	 */
	public function actionStop($args): void
	{
		$this->finishConsole(
			$this->params['session-id']
		);
	}
}
