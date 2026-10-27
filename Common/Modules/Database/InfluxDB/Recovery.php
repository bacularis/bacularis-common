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

namespace Bacularis\Common\Modules\Database\InfluxDB;

use Bacularis\Common\Modules\Miscellaneous;

/**
 * InfluxDB 2.x offline recovery listing commands for reference metadata.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class Recovery extends Version2
{
	/**
	 * Build an offline organization listing command without executing it.
	 *
	 * @param string $influxd_path InfluxDB server binary path
	 * @param string $bolt_path existing source BoltDB file
	 * @return array literal arguments, or empty array for invalid values
	 */
	public function getOrganizationListCommand(string $influxd_path, string $bolt_path): array
	{
		return $this->getListCommand($influxd_path, $bolt_path, 'org');
	}

	/**
	 * Build an offline user listing command without executing it.
	 *
	 * @param string $influxd_path InfluxDB server binary path
	 * @param string $bolt_path existing source BoltDB file
	 * @return array literal arguments, or empty array for invalid values
	 */
	public function getUserListCommand(string $influxd_path, string $bolt_path): array
	{
		return $this->getListCommand($influxd_path, $bolt_path, 'user');
	}

	/**
	 * Build an offline authorization listing command; its output may be sensitive.
	 *
	 * @param string $influxd_path InfluxDB server binary path
	 * @param string $bolt_path existing source BoltDB file
	 * @return array literal arguments, or empty array for invalid values
	 */
	public function getAuthorizationListCommand(string $influxd_path, string $bolt_path): array
	{
		return $this->getListCommand($influxd_path, $bolt_path, 'auth');
	}

	/**
	 * Validate the source file without opening, parsing or modifying BoltDB.
	 *
	 * @param string $bolt_path source BoltDB path
	 * @return bool true for a readable regular file that is not a symlink
	 */
	public function isValidBoltPath(string $bolt_path): bool
	{
		$is_control_char = Miscellaneous::isASCIControlChar($bolt_path);
		return trim($bolt_path) !== '' && !$is_control_char && !is_link($bolt_path) && is_file($bolt_path) && is_readable($bolt_path);
	}

	/**
	 * Prepare a supported recovery listing for stdout streaming by the caller.
	 *
	 * @param string $influxd_path InfluxDB server binary path or program resolved through PATH
	 * @param string $bolt_path existing source BoltDB file
	 * @param string $resource supported recovery resource
	 * @return array literal arguments, or empty array for invalid values
	 */
	private function getListCommand(string $influxd_path, string $bolt_path, string $resource): array
	{
		$is_valid = $this->isValidBoltPath($bolt_path);
		$is_control_char = Miscellaneous::isASCIControlChar($influxd_path);
		if (!$is_valid || trim($influxd_path) === '' || $is_control_char) {
			return [];
		}
		return [$influxd_path, 'recovery', $resource, 'list', '--bolt-path', $bolt_path];
	}
}
