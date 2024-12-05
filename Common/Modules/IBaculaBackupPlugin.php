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
 * Interface for backup plugin type.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Interface
 */
interface IBaculaBackupPlugin extends IBacularisPlugin
{
	/**
	 * Plugin module initialize method.
	 *
	 * @param array $args plugin arguments
	 */
	public function initialize(array $args): void;

	/**
	 * Backup command.
	 *
	 * @param array $args backup command arguments
	 * @return bool true on success, false otherwise
	 */
	public function doBackup(array $args): bool;

	/**
	 * Restore command.
	 *
	 * @param array $args restore command arguments
	 * @return bool true on success, false otherwise
	 */
	public function doRestore(array $args): bool;

	/**
	 * Get plugin command to put in fileset.
	 *
	 * @param string $action plugin command action
	 * @param array $params plugin command parameters
	 * @return array plugin command
	 */
	public function getPluginCommand(string $action, array $params): array;

	/**
	 * Get plugin command list returned by main fileset plugin command.
	 * (@see IBaculaBackupPlugin::getPluginCommand);
	 *
	 * @param array $args plugin command parameters
	 * @return array plugin commands
	 */
	public function getPluginCommands(array $args): array;
}
