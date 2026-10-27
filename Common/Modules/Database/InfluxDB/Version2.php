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

/**
 * InfluxDB 2.x binary path helpers.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class Version2 extends Client
{
	/**
	 * Get the InfluxDB CLI binary path.
	 *
	 * @param string $binary_path directory containing the binaries
	 * @return string binary path
	 */
	public function getInfluxPath(string $binary_path): string
	{
		return $this->getBinaryPath($binary_path, 'influx');
	}

	/**
	 * Get the InfluxDB server binary path.
	 *
	 * @param string $binary_path directory containing the binaries
	 * @return string binary path
	 */
	public function getInfluxdPath(string $binary_path): string
	{
		return $this->getBinaryPath($binary_path, 'influxd');
	}
}
