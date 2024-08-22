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

/**
 * Chmod command module.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Common
 */
class Chmod extends CommonModule
{
	/**
	 * Base command to run.
	 */
	private const CMD = 'chmod';

	/**
	 * Sudo command.
	 */
	private const SUDO = 'sudo -S';

	/**
	 * Pattern types used to prepare command.
	 */
	public const PTYPE_REG_CMD = 0;

	/**
	 * Foreground cp command.
	 *
	 * Format: %cmd %options %src_file %dst_ile
	 */
	private const CHOWN_COMMAND_PATTERN = "%s %s \"%s\"";

	/**
	 * Execute command.
	 *
	 * @param string $file file path to set permissions
	 * @param string $perm permissions to set
	 * @param array $params command parameters
	 * @param int $ptype command pattern type
	 * @return array command output
	 */
	public function execCommand(string $file, string $perm, array $params = [], int $ptype = self::PTYPE_REG_CMD): array
	{
		$cmd = $this->prepareCommand(
			$file,
			$perm,
			$params,
			$ptype
		);

		$user = $password = '';
		if (key_exists('user', $params) && !empty($params['user'])) {
			$user = $params['user'];
		}
		if (key_exists('password', $params) && !empty($params['password'])) {
			$password = $params['password'];
		}
		$use_sudo = false;
		if (key_exists('use_sudo', $params) && $params['use_sudo'] === true) {
			$use_sudo = true;
		}
		if ($use_sudo) {
			$cmd = sprintf('LANG=C %s %s', self::SUDO, $cmd);
		}

		if (!empty($user) && !empty($password)) {
			$su = $this->getModule('su');
			$result = $su->execCommand(
				$user,
				$password,
				[
					'command' => $cmd,
					'use_sudo' => $use_sudo
				]
			);
		} else {
			exec($cmd, $output, $exitcode);
			$result = [
				'output' => $output,
				'exitcode' => $exitcode
			];
		}

		if ($result['exitcode'] != 0) {
			$out = implode('', $result['output']);
			$emsg = "Error while setting permissions to '{$file}': ExitCode: {$result['exitcode']}, Output: '{$out}'";
			Logging::log(
				Logging::CATEGORY_EXECUTE,
				$emsg
			);
		}
		return $result;
	}

	/**
	 * Prepare command to execution.
	 *
	 * @param string $file file path to set permissions
	 * @param string $perm permissions to set
	 * @param array $params command parameters
	 * @param int $ptype command pattern type
	 * @return string full command string
	 */
	private function prepareCommand(string $file, string $perm, array $params, int $ptype)
	{
		$opts = [];
		if (key_exists('recursive', $params) && $params['recursive'] === true) {
			$opts[] = '-R';
		}

		$options = implode(' ', $opts);
		$options .= ' ' . $perm;

		$chmod_cmd = $this->getCmdPattern($ptype);
		$cmd = sprintf(
			$chmod_cmd,
			self::CMD,
			$options,
			$file
		);
		return $cmd;
	}

	/**
	 * Get command pattern by pattern type.
	 * So far support is only foreground command regular pattern.
	 *
	 * @param int $ptype command pattern type
	 * @return string command pattern
	 */
	private function getCmdPattern(int $ptype): string
	{
		$pattern = null;
		switch ($ptype) {
			case self::PTYPE_REG_CMD: $pattern = self::CHOWN_COMMAND_PATTERN;
				break;
			default: $pattern = self::CHOWN_COMMAND_PATTERN;
				break;
		}
		return $pattern;
	}
}
