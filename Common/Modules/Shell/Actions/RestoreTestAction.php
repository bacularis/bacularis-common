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

use Bacularis\Common\Modules\RestoreVerification;
use Bacularis\Common\Modules\Shell\BShellAction;

/**
 * Bacula restore test command action.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class RestoreTestAction extends BShellAction
{
	/**
	 * Main action.
	 */
	protected $action = 'verification';

	/**
	 * Action methods.
	 */
	protected $methods = ['run', 'verify', 'finalize'];

	/**
	 * Command required parameters.
	 */
	protected $parameters = [
		[
			'token' => 'token',
		],
		[
			'test-id' => 'test-id'
		],
		[
			'token' => 'token'
		]
	];

	/**
	 * Command optional parameters.
	 */
	protected $optional = [
		[
			'web-protocol' => 'protocol',
			'web-address' => 'address',
			'web-port' => 'port'
		],
		[],
		[
			'web-protocol' => 'protocol',
			'web-address' => 'address',
			'web-port' => 'port'
		]
	];

	/**
	 * Command descriptions.
	 */
	protected $description = [
		'Verify restore test commands',
		'Run restore test.',
		'Run restore data verification.',
		'Finalize restore data verification.'
	];

	/**
	 * All command parameters.
	 */
	public $params = [];

	/**
	 * Run restore test.
	 *
	 * @param array $args command line parameters
	 * @return bool true it is always valid
	 */
	public function actionRun(array $args = []): bool
	{
		$token = $args['token'] ?? '';
		$web_protocol = self::getWebProtocol($args);
		$web_address = self::getWebAddress($args);
		$web_port = self::getWebPort($args);
		$level = self::getJobLevel($args);
		return RestoreVerification::runRestoreTest(
			$token,
			$web_protocol,
			$web_address,
			$web_port,
			$level
		);
	}

	/**
	 * Get web interface protocol.
	 *
	 * @param array $args command parameters
	 * @return string web interface protocol
	 */
	private static function getWebProtocol(array $args): string
	{
		$protocol = (string) ($args['web-protocol'] ?? RestoreVerification::DEFAULT_WEB_ACCESS_PROTOCOL);
		$protocol = strtolower(trim($protocol));
		if (!in_array($protocol, ['http', 'https'])) {
			$protocol = RestoreVerification::DEFAULT_WEB_ACCESS_PROTOCOL;
		}
		return $protocol;
	}

	/**
	 * Get web interface address.
	 *
	 * @param array $args command parameters
	 * @return string web interface address
	 */
	private static function getWebAddress(array $args): string
	{
		$address = (string) ($args['web-address'] ?? RestoreVerification::DEFAULT_WEB_ACCESS_ADDRESS);
		$address = trim($address);
		if ($address === '') {
			$address = RestoreVerification::DEFAULT_WEB_ACCESS_ADDRESS;
		}
		return $address;
	}

	/**
	 * Get web interface port.
	 *
	 * @param array $args command parameters
	 * @return string web interface port
	 */
	private static function getWebPort(array $args): string
	{
		$port = (string) ($args['web-port'] ?? RestoreVerification::DEFAULT_WEB_ACCESS_PORT);
		$port = trim($port);
		if (!preg_match('/^\d+$/', $port)) {
			$port = RestoreVerification::DEFAULT_WEB_ACCESS_PORT;
		}
		return $port;
	}

	/**
	 * Get job level.
	 *
	 * @param array $args command parameters
	 * @return string job level
	 */
	private static function getJobLevel(array $args): string
	{
		$level = $args['level'] ?? '';
		return $level;
	}

	/**
	 * Run restore verification.
	 *
	 * @param array $args command line parameters
	 * @return bool true on success, false otherwise
	 */
	public function actionVerify(array $args = []): bool
	{
		$test_id = $this->params['test-id'] ?? '';
		return RestoreVerification::runTestPlan($test_id);
	}

	/**
	 * Finalize restore verification.
	 *
	 * @param array $args command line parameters
	 * @return bool true on success, false otherwise
	 */
	public function actionFinalize(array $args = []): bool
	{
		$token = $args['token'] ?? '';
		$web_protocol = self::getWebProtocol($args);
		$web_address = self::getWebAddress($args);
		$web_port = self::getWebPort($args);
		return RestoreVerification::finalizeRestoreTest(
			$token,
			$web_protocol,
			$web_address,
			$web_port
		);
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
