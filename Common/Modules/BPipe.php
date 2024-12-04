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
 * Bacula bpipe plugin module.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class BPipe extends CommonModule
{
	/**
	 * Bpipe plugin command definition pattern.
	 */
	private const BPIPE_PLUGIN_DEF = 'bpipe:%s:%s:%s';

	/**
	 * Get bpipe plugin command line.
	 *
	 * @param string $path path to save the output
	 * @param string $backup_cmd backup command
	 * @param string $restore_cmd restore command
	 * @return string bpipe plugin command line
	 */
	public function getPluginCommand(string $path, string $backup_cmd, string $restore_cmd): string
	{
		$cmd = sprintf(
			self::BPIPE_PLUGIN_DEF,
			$path,
			$backup_cmd,
			$restore_cmd
		);
		return $cmd;
	}
}
