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

/**
 * Common shell command module.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class ShellCommandModule extends CommonModule
{
	/**
	 * Sudo command.
	 *
	 * @var string
	 */
	private const SUDO = 'sudo -S';

	/**
	 * Set common command parameters.
	 *
	 * @param array $command command reference
	 * @param array $params command parameters
	 */
	protected static function setCommandParameters(array &$command, array $params)
	{
		if (key_exists('use_shell', $params) && $params['use_shell']) {
			$command = array_map(
				fn ($item) => str_replace(['"'], ['\\\"'], $item),
				$command
			);
			array_unshift($command, 'sh', '-c', '"');
			array_push($command, '"');
		}
		if (key_exists('use_sudo', $params) && $params['use_sudo']) {
			array_unshift($command, self::SUDO);
		}
		array_unshift($command, 'LANG=C');
	}

	/**
	 * Get command to check if file exists.
	 *
	 * @param string $file file path
	 * @param string $cmd_params command parameters
	 * @return array command to check if file exists
	 */
	public static function getFileExistsCommand(string $file, array $cmd_params = []): array
	{
		$ret = [
			'[',
			'-e',
			$file,
			']',
			'||',
			'exit 1'
		];
		static::setCommandParameters($ret, $cmd_params);
		return $ret;
	}

	/**
	 * Check if file exists.
	 *
	 * @param string $file file path
	 * @param array $cmd_params command parameters (use_sudo, login, password...)
	 * @return bool true if file exists, otherwise false
	 */
	public function fileExists(string $file, array $cmd_params): bool
	{
		$cmd = ShellCommandModule::getFileExistsCommand($file);

		// credentials
		$user = $cmd_params['user'] ?? '';
		$password = $cmd_params['password'] ?? '';

		// sudo setting
		$use_sudo = $cmd_params['use_sudo'] ?? false;

		$su = $this->getModule('su');
		$params = [
			'command' => implode(' ', $cmd),
			'use_sudo' => $use_sudo
		];
		$ret = $su->execCommand(
			$user,
			$password,
			$params
		);
		$state = ($ret['exitcode'] == 0);
		return $state;
	}

	/**
	 * Execute shell command.
	 * It supports executing command as given user and with or without sudo.
	 *
	 * @param array $cmd command to execute
	 * @param array $cmd_params command parameters
	 * @return array command result details
	 */
	protected function execCommand(array $cmd, array $cmd_params): array
	{
		$user = $cmd_params['user'] ?? '';
		$password = $cmd_params['password'] ?? '';
		$use_sudo = $cmd_params['use_sudo'] ?? false;

		$params = [
			'command' => implode(' ', $cmd),
			'use_sudo' => $use_sudo
		];
		$su = $this->getModule('su');
		$result = $su->execCommand(
			$user,
			$password,
			$params
		);
		return $result;
	}

	protected static function getOutput(array $out): string
	{
		$output = [];
		for ($i = 0; $i < count($out); $i++) {
			$line = trim($out[$i]);
			if (preg_match('/^(spawn\s|password:|\[sudo\]\s|writing\sRSA\skey)/i', $line) === 1) {
				continue;
			}
			if (empty($line)) {
				break;
			}
			$output[] = $line;
		}
		return implode(PHP_EOL, $output);
	}
}
