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

namespace Bacularis\Common\Modules;

/**
 * Systemd commands.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class Systemd extends ShellCommandModule
{
	/**
	 * Get systemd systemctl command.
	 *
	 * @param array $params systemctl parameters
	 * @param array $cmd_params command options
	 * @return array systemctl command or empty string if OS does not use systemd
	 */
	public static function getSystemCtlCommand(array $params, array $cmd_params = []): array
	{
		$ret = [];
		if (static::binaryExists('systemctl', $cmd_params)) {
			$ret = ['systemctl'];
			$ret = array_merge($ret, $params);
			static::setCommandParameters($ret, $cmd_params);
		}
		return $ret;
	}

	/**
	 * Get systemd unit option.
	 *
	 * @param string $unit unit name, ex: 'php-fpm.service'
	 * @param string $option option name, ex: 'ProtectSystem'
	 * @param array $cmd_params command options
	 * @return string option value or empty string if:web value not available
	 */
	public static function getUnitOption(string $unit, string $option, array $cmd_params): string
	{
		$params = [
			'--no-pager',
			'show',
			$unit,
			'-p',
			$option,
			'--value'
		];
		$cmd = self::getSystemCtlCommand($params, $cmd_params);
		$ret = '';
		$result = static::execCommand($cmd, $cmd_params);
		if ($result['error'] == 0 && count($result['output']) > 2) {
			$out = static::stripOutput($result['output']);
			$ret = array_shift($out);
		}
		return $ret;
	}
}
