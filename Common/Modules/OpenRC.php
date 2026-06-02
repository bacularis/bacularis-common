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
 * OpenRC commands.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class OpenRC extends ShellCommandModule
{
	/**
	 * Get OpenRC rc-service command.
	 *
	 * @param array $params rc-service parameters
	 * @param array $cmd_params command options
	 * @return array rc-service command or empty string if OS does not use OpenRC
	 */
	public static function getRCServiceCommand(array $params, array $cmd_params = []): array
	{
		$ret = [];
		if (static::binaryExists('rc-service', $cmd_params)) {
			$ret = ['rc-service'];
			$ret = array_merge($ret, $params);
			static::setCommandParameters($ret, $cmd_params);
		}
		return $ret;
	}
}
