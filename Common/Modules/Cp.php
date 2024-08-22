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
 * CP command module.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Common
 */
class Cp extends CommonModule
{
	/**
	 * Base command to run.
	 */
	private const CMD = 'cp';

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
	private const CP_COMMAND_PATTERN = "%s %s \"%s\" \"%s\"";

	/**
	 * Execute command.
	 *
	 * @param string $src_file file path to copy to destination host
	 * @param string $dest_path destination path on remote host
	 * @param array $params command parameters
	 * @param int $ptype command pattern type
	 * @return array command output
	 */
	public function execCommand(string $src_file, string $dest_path, array $params, int $ptype = self::PTYPE_REG_CMD): array
	{
		$cmd = $this->prepareCommand(
			$src_file,
			$dest_path,
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
			$emsg = "Error while copying '{$src_file}' to '{$dest_path}': ExitCode: {$result['exitcode']}, Output: '{$out}'";
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
	 * @param string $src_file file path to copy to destination host
	 * @param string $dest_path destination path on remote host
	 * @param array $params command parameters
	 * @param int $ptype command pattern type
	 * @return string full command string
	 */
	private function prepareCommand(string $src_file, string $dest_path, array $params, int $ptype)
	{
		$opts = [];
		if (key_exists('recursive', $params) && $params['recursive'] === true) {
			$opts[] = '-r';
		}

		$options = implode(' ', $opts);

		$cp_cmd = $this->getCmdPattern($ptype);
		$cmd = sprintf(
			$cp_cmd,
			self::CMD,
			$options,
			$src_file,
			$dest_path
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
			case self::PTYPE_REG_CMD: $pattern = self::CP_COMMAND_PATTERN;
				break;
			default: $pattern = self::CP_COMMAND_PATTERN;
				break;
		}
		return $pattern;
	}
}
