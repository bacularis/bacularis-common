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

namespace Bacularis\Common\Plugins;

use Prado\Prado;
use Bacularis\Common\Modules\IBaculaBackupFileDaemonPlugin;
use Bacularis\Common\Modules\BacularisCommonPluginBase;
use Bacularis\Common\Modules\Plugins;

/**
 * The MariaDB backup plugin module.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Plugin
 */
class MariaDBBackup extends BacularisCommonPluginBase implements IBaculaBackupFileDaemonPlugin
{
	/**
	 * SQL dump backup tools.
	 */
	private const SQL_DUMP_PROGRAM = 'mariadb-dump';
	private const SQL_CLI_PROGRAM = 'mariadb';
	private const SQL_BINLOG_PROGRAM = 'mariadb-binlog';

	/**
	 * Binary physical backup tools.
	 */
	private const BIN_BACKUP_PROGRAM = 'mariabackup';
	private const BIN_STREAM_PROGRAM = 'mbstream';

	/**
	 * File backup tools.
	 */
	private const FILE_BACKUP_COMMAND = 'tar -cf /dev/stdout "%s"';
	private const FILE_RESTORE_COMMAND = 'tar -C "%s" -xvf -';

	/**
	 * Binary log backup tools.
	 */
	private const BINLOG_BACKUP_COMMAND = 'tar -cf /dev/stdout -T "%s"';
	private const BINLOG_RESTORE_COMMAND = 'tar -C "%s" -xvf -';

	/**
	 * Supported backup methods.
	 */
	private const BACKUP_METHOD_DUMP = 'dump';
	private const BACKUP_METHOD_BINARY = 'binary';
	private const BACKUP_METHOD_FILE = 'file';
	private const BACKUP_METHOD_BINLOG = 'binlog';

	/**
	 * Plugin parameter categories
	 */
	private const PARAM_CAT_GENERAL = 'General';
	private const PARAM_CAT_DUMP_BACKUP = 'Dump backup';
	private const PARAM_CAT_DUMP_RESTORE = 'Dump restore';
	private const PARAM_CAT_BINARY_BACKUP = 'Binary backup';
	private const PARAM_CAT_BINARY_RESTORE = 'Binary restore';
	private const PARAM_CAT_FILE_BACKUP = 'File backup';
	private const PARAM_CAT_FILE_RESTORE = 'File restore';
	private const PARAM_CAT_BINLOG_BACKUP = 'Binary log backup';
	private const PARAM_CAT_BINLOG_RESTORE = 'Binary log restore';

	/**
	 * Common parameters for the SQL dump program.
	 */
	private const SQL_DUMP_PROGRAM_COMMON_OPTS = [
		'single-transaction' => true,
		'flush-logs' => true,
		'master-data' => 2
	];

	/**
	 * Common parameters for the binary log program.
	 */
	private const SQL_BINLOG_PROGRAM_COMMON_OPTS = [
		'read-from-remote-server' => true,
		'to-last-log' => true,
		'skip-gtid-strict-mode' => true
	];

	/**
	 * Databases that are ignored in all databases backup.
	 */
	private const IGNORE_SYSTEM_TABLES = [
		'information_schema',
		'performance_schema',
		'sys'
	];

	/**
	 * System SQL data directory name.
	 */
	private const SYSTEM_DATA_DIR = '.SYSTEM';

	/**
	 * Default job level if not provided.
	 */
	private const DEFAULT_JOB_LEVEL = 'Full';

	/**
	 * Backup all databases modes.
	 */
	private const ALL_DATABASES = 'all-databases';
	private const ALL_DATABASES_LIST = 'all-databases-list';
	private const ALL_DATABASES_ONE_DUMP = 'all-databases-one-dump';

	/**
	 * Backup actions.
	 */
	private const ACTION_SYSTEM = 'system';
	private const ACTION_SQL_ALL_DBS = 'sql-all-databases';
	private const ACTION_SQL_DATA = 'sql-data';
	private const ACTION_BINARY_DATA = 'binary-data';
	private const ACTION_SCHEMA = 'schema';
	private const ACTION_FILE = 'file';
	private const ACTION_DIR = 'dir';
	private const ACTION_BINLOG = 'binlog';

	/**
	 * Debug mode variable.
	 * Debug modes:
	 *  0 - no debug
	 *  1 - debug to stdout
	 *  2 - debug to file
	 */
	private $_debug = 0;

	/**
	 * Get plugin name displayed in web interface.
	 *
	 * @return string plugin name
	 */
	public static function getName(): string
	{
		return 'MariaDB database backup';
	}

	/**
	 * Get plugin version.
	 *
	 * @return string plugin version
	 */
	public static function getVersion(): string
	{
		return '1.0.0';
	}

	/**
	 * Get plugin type.
	 *
	 * @return string plugin type
	 */
	public static function getType(): string
	{
		return 'backup';
	}

	/**
	 * Get plugin configuration parameters.
	 *
	 * return array plugin parameters
	 */
	public static function getParameters(): array
	{
		return [
			[
				'name' => 'server-name',
				'type' => 'string',
				'default' => 'main',
				'label' => 'User defined MariaDB server name (any string)',
				'category' => [self::PARAM_CAT_GENERAL]
			],
			[
				'name' => 'user',
				'type' => 'string',
				'default' => 'root',
				'label' => 'Database admin username',
				'category' => [self::PARAM_CAT_GENERAL]
			],
			[
				'name' => 'password',
				'type' => 'password',
				'default' => '',
				'label' => 'Database admin password',
				'category' => [self::PARAM_CAT_GENERAL]
			],
			[
				'name' => 'defaults-extra-file',
				'type' => 'string',
				'default' => '',
				'label' => 'Extra file path',
				'category' => [self::PARAM_CAT_GENERAL],
				'first' => true
			],
			[
				'name' => 'binary-path',
				'type' => 'string',
				'default' => '',
				'label' => 'MariaDB binaries path',
				'category' => [self::PARAM_CAT_GENERAL]
			],
			[
				'name' => 'debug',
				'type' => 'integer',
				'default' => 0,
				'label' => 'Debug mode',
				'category' => [self::PARAM_CAT_GENERAL]
			],
			[
				'name' => 'dump-method',
				'type' => 'boolean',
				'default' => false,
				'label' => 'Enable logical dump backup method',
				'category' => [self::PARAM_CAT_DUMP_BACKUP]
			],
			[
				'name' => 'all-databases-list',
				'type' => 'boolean',
				'default' => false,
				'label' => 'Dump all databases separately',
				'category' => [self::PARAM_CAT_DUMP_BACKUP]
			],
			[
				'name' => 'all-databases-one-dump',
				'type' => 'boolean',
				'default' => false,
				'label' => 'Dump all databases in one dump',
				'category' => [self::PARAM_CAT_DUMP_BACKUP]
			],
			[
				'name' => 'databases',
				'type' => 'string',
				'default' => '',
				'label' => 'Databases to backup (comma separated)',
				'category' => [self::PARAM_CAT_DUMP_BACKUP]
			],
			[
				'name' => 'add-drop-table',
				'type' => 'boolean',
				'default' => false,
				'label' => 'Add DROP TABLE before each CREATE TABLE statement',
				'category' => [self::PARAM_CAT_DUMP_BACKUP]
			],
			[
				'name' => 'dump-option',
				'type' => 'string',
				'default' => '',
				'label' => 'Additional dump program option',
				'category' => [self::PARAM_CAT_DUMP_BACKUP]
			],
			[
				'name' => 'binary-method',
				'type' => 'boolean',
				'default' => false,
				'label' => 'Enable physical binary backup method',
				'category' => [self::PARAM_CAT_BINARY_BACKUP]
			],
			[
				'name' => 'binary-backup-path',
				'type' => 'string',
				'default' => '',
				'label' => 'Physical binary backup base path',
				'category' => [self::PARAM_CAT_BINARY_BACKUP]
			],
			[
				'name' => 'prepare-backup',
				'type' => 'boolean',
				'default' => false,
				'label' => 'Run prepare action on backup',
				'category' => [self::PARAM_CAT_BINARY_BACKUP]
			],
			[
				'name' => 'file-method',
				'type' => 'boolean',
				'default' => false,
				'label' => 'Enable file backup method',
				'category' => [self::PARAM_CAT_FILE_BACKUP]
			],
			[
				'name' => 'include-path',
				'type' => 'string',
				'default' => '',
				'label' => 'File paths to include in backup (comma separated)',
				'category' => [self::PARAM_CAT_FILE_BACKUP]
			],
			[
				'name' => 'binlog-method',
				'type' => 'boolean',
				'default' => false,
				'label' => 'Enable binary log backup method',
				'category' => [self::PARAM_CAT_BINLOG_BACKUP]
			],
			[
				'name' => 'binlog-path',
				'type' => 'string',
				'default' => '',
				'label' => 'Binary log path to backup',
				'category' => [self::PARAM_CAT_BINLOG_BACKUP]
			],
			[
				'name' => 'database',
				'type' => 'string',
				'default' => '',
				'label' => 'New database name',
				'category' => [self::PARAM_CAT_DUMP_RESTORE]
			]
		];
	}

	/**
	 * Initialize pseudo-constructor.
	 *
	 * @param array $args plugin options
	 */
	public function initialize(array $args): void
	{
		$this->_debug = $args['debug'] ?? Plugins::LOG_DEST_DISABLED;
	}

	/**
	 * Common method to get dump backup commands.
	 * It is for all supported backup levels.
	 *
	 * @param array $args plugin options
	 * @param string $level backup level (ex. 'Full', 'Incremental' or 'Differential')
	 * @return array backup commands
	 */
	private function getDumpBackupLevelCommand(array $args, string $level): array
	{
		$cmds = [];
		if ($level == 'Full') {
			if (key_exists(self::ALL_DATABASES_ONE_DUMP, $args) || key_exists(self::ALL_DATABASES_LIST, $args)) {
				$sys_cmds = ['users', 'plugins', 'udfs', 'servers', 'stats', 'timezones'];
				for ($i = 0; $i < count($sys_cmds); $i++) {
					$cmds[] = $this->getSinglePluginCommand($args, self::ACTION_SYSTEM, $sys_cmds[$i]);
				}
			}
		}

		$dbs = [];
		if (key_exists(self::ALL_DATABASES_ONE_DUMP, $args)) {
			$cmds[] = $this->getSinglePluginCommand($args, self::ACTION_SQL_ALL_DBS, self::ALL_DATABASES);
		}
		if (key_exists(self::ALL_DATABASES_LIST, $args)) {
			$dbs = $this->getDatabases($args);
		} elseif (key_exists('databases', $args)) {
			$dbs = explode(',', $args['databases']);
			$dbs = array_map('trim', $dbs);
		}
		for ($i = 0; $i < count($dbs); $i++) {
			$args['databases'] = $dbs[$i];
			if ($level == 'Full') {
				$cmds[] = $this->getSinglePluginCommand($args, self::ACTION_SCHEMA, $dbs[$i]);
			}
			$cmds[] = $this->getSinglePluginCommand($args, self::ACTION_SQL_DATA, $dbs[$i]);
		}
		return $cmds;
	}

	/**
	 * Get full dump backup commands.
	 *
	 * @param array $args plugin options
	 * @return array backup commands
	 */
	private function getFullDumpBackupPluginCommands(array $args): array
	{
		return $this->getDumpBackupLevelCommand($args, 'Full');
	}

	/**
	 * Get incremental dump backup commands.
	 *
	 * @param array $args plugin options
	 * @return array backup commands
	 */
	private function getIncrementalDumpBackupPluginCommands(array $args): array
	{
		return $this->getDumpBackupLevelCommand($args, 'Incremental');
	}

	/**
	 * Get differential dump backup commands.
	 *
	 * @param array $args plugin options
	 * @return array backup commands
	 */
	private function getDifferentialDumpBackupPluginCommands(array $args): array
	{
		return $this->getDumpBackupLevelCommand($args, 'Differential');
	}

	/**
	 * Get full binary backup commands.
	 *
	 * @param array $args plugin options
	 * @return array backup commands
	 */
	private function getFullBinaryBackupPluginCommands(array $args): array
	{
		$cmds = [];
		$cmds[] = $this->getSinglePluginCommand($args, self::ACTION_BINARY_DATA, '');
		return $cmds;
	}

	/**
	 * Get incremental binary backup commands.
	 *
	 * @param array $args plugin options
	 * @return array backup commands
	 */
	private function getIncrementalBinaryBackupPluginCommands(array $args): array
	{
		$cmds = [];
		$cmds[] = $this->getSinglePluginCommand($args, self::ACTION_BINARY_DATA, '');
		return $cmds;
	}

	/**
	 * Get full file backup commands.
	 *
	 * @param array $args plugin options
	 * @return array backup commands
	 */
	private function getFullFileBackupPluginCommands(array $args): array
	{
		$cmds = [];
		$include_paths = [];
		if (key_exists('include-path', $args)) {
			$include_paths = explode(',', $args['include-path']);
		}
		for ($i = 0; $i < count($include_paths); $i++) {
			$args['include-path'] = $include_paths[$i];
			$cmds[] = $this->getSinglePluginCommand($args, self::ACTION_FILE, 'include-path');
		}
		return $cmds;
	}

	/**
	 * Get full binary log backup commands.
	 *
	 * @param array $args plugin options
	 * @return array backup commands
	 */
	private function getFullBinLogBackupPluginCommands(array $args): array
	{
		$cmds = [];
		$cmds[] = $this->getSinglePluginCommand($args, self::ACTION_BINLOG, 'binlog-path');
		return $cmds;
	}

	/**
	 * Get incremental binary log backup commands.
	 *
	 * @param array $args plugin options
	 * @return array backup commands
	 */
	private function getIncrementalBinLogBackupPluginCommands(array $args): array
	{
		$cmds = [];
		$cmds[] = $this->getSinglePluginCommand($args, self::ACTION_BINLOG, 'binlog-path');
		return $cmds;
	}

	/**
	 * Get single plugin command line.
	 *
	 * @param array $args plugin options
	 * @param string $action backup action
	 * @param string $item backup item
	 * @return array single backup command
	 */
	private function getSinglePluginCommand(array $args, string $action, string $item)
	{
		$bpipe = $this->getModule('bpipe');
		$backup_path = $backup_cmd = $restore_cmd = '';
		switch ($action) {
			case self::ACTION_SYSTEM: {
				$backup_cmd = $this->getBackupSystemCommand($args, $item);
				$restore_cmd = $this->getRestoreSQLCommand($args, $action, $item);
				$backup_path = $this->getBackupSQLPath($args, self::SYSTEM_DATA_DIR, $item);
				break;
			}
			case self::ACTION_SQL_ALL_DBS: {
				$backup_cmd = $this->getBackupSQLAllDbsCommand($args);
				$restore_cmd = $this->getRestoreSQLCommand($args, $action, $item);
				$action_fm = $this->getFormattedFile($action, $args['job-starttime'], $args['job-id'], $args['job-level']);
				$backup_path = $this->getBackupSQLPath($args, $item, $action_fm);
				break;
			}
			case self::ACTION_SQL_DATA: {
				$backup_cmd = $this->getBackupSQLDataCommand($args);
				$restore_cmd = $this->getRestoreSQLCommand($args, $action, $item);
				$action_fm = $this->getFormattedFile($action, $args['job-starttime'], $args['job-id'], $args['job-level']);
				$backup_path = $this->getBackupSQLPath($args, $item, $action_fm);
				break;
			}
			case self::ACTION_SCHEMA: {
				$backup_cmd = $this->getBackupSchemaCommand($args);
				$restore_cmd = $this->getRestoreSQLCommand($args, $action, $item);
				$backup_path = $this->getBackupSQLPath($args, $item, $action);
				break;
			}
			case self::ACTION_FILE:
			case self::ACTION_DIR: {
				$backup_cmd = $this->getBackupFileCommand($args);
				$restore_cmd = $this->getRestoreFileCommand($args, $action, $item);
				$backup_path = $this->getBackupFilePath($args, $item);
				break;
			}
			case self::ACTION_BINARY_DATA: {
				$backup_cmd = $this->getBackupBinaryDataCommand($args);
				$restore_cmd = $this->getRestoreBinaryCommand($args, $action);
				$action_fm = $this->getFormattedFile($action, $args['job-starttime'], $args['job-id'], $args['job-level']);
				$backup_path = $this->getBackupBinaryPath($args, $action_fm);
				break;
			}
			case self::ACTION_BINLOG: {
				$backup_cmd = $this->getBackupBinLogCommand($args);
				$restore_cmd = $this->getRestoreFileCommand($args, $action, $item);
				$backup_path = $this->getBackupFilePath($args, $item);
				break;
			}
		}
		return $bpipe->getPluginCommand(
			$backup_path,
			$backup_cmd,
			$restore_cmd
		);
	}

	/**
	 * Get system data backup plugin command.
	 *
	 * @param array $args plugin options
	 * @param string $system_cmd system command (ex. 'users', 'plugins'...etc.)
	 * @return string backup command
	 */
	private function getBackupSystemCommand(array $args, string $system_cmd): string
	{
		$action = 'command/backup';
		$backup_args = $this->filterParametersByCategory($args, [
			self::PARAM_CAT_GENERAL
		]);
		$backup_args['dump-method'] = true;
		$backup_args['system'] = $system_cmd;
		$cmd = $this->getPluginCommand($action, $backup_args);
		return implode(' ', $cmd);
	}

	/**
	 * Get table schema backup plugin command.
	 *
	 * @param array $args plugin options
	 * @return string backup command
	 */
	private function getBackupSchemaCommand(array $args): string
	{
		$action = 'command/backup';
		$schema_args = $this->filterParametersByCategory($args, [
			self::PARAM_CAT_GENERAL,
			self::PARAM_CAT_DUMP_BACKUP
		]);
		$schema_args['no-data'] = true;
		$backup_args['no-create-db'] = true;
		$schema_args['events'] = true;
		$schema_args['triggers'] = true;
		$schema_args['routines'] = true;
		$cmd = $this->getPluginCommand($action, $schema_args);
		return implode(' ', $cmd);
	}

	/**
	 * Get SQL data backup plugin command.
	 *
	 * @param array $args plugin options
	 * @return string backup command
	 */
	private function getBackupSQLDataCommand(array $args): string
	{
		$action = 'command/backup';
		$backup_args = $this->filterParametersByCategory($args, [
			self::PARAM_CAT_GENERAL,
			self::PARAM_CAT_DUMP_BACKUP
		]);
		$backup_args['no-create-info'] = true;
		$backup_args['no-create-db'] = true;
		$backup_args['job-level'] = $args['job-level'];
		$backup_args['job-name'] = $args['job-name'];
		$cmd = $this->getPluginCommand($action, $backup_args);
		return implode(' ', $cmd);
	}

	/**
	 * Get all databases backup plugin command.
	 *
	 * @param array $args plugin options
	 * @return string backup command
	 */
	private function getBackupSQLAllDbsCommand(array $args): string
	{
		$action = 'command/backup';
		$backup_args = $this->filterParametersByCategory($args, [
			self::PARAM_CAT_GENERAL,
			self::PARAM_CAT_DUMP_BACKUP
		]);
		$backup_args['all-databases'] = true;
		$backup_args['databases'] = self::ALL_DATABASES;
		$backup_args['job-level'] = $args['job-level'];
		$backup_args['job-name'] = $args['job-name'];
		$cmd = $this->getPluginCommand($action, $backup_args);
		return implode(' ', $cmd);
	}

	/**
	 * Get binary backup plugin command.
	 *
	 * @param array $args plugin options
	 * @return string backup command
	 */
	private function getBackupBinaryDataCommand(array $args): string
	{
		$action = 'command/backup';
		$backup_args = $this->filterParametersByCategory($args, [
			self::PARAM_CAT_GENERAL,
			self::PARAM_CAT_BINARY_BACKUP
		]);
		$backup_args['job-id'] = $args['job-id'];
		$backup_args['job-name'] = $args['job-name'];
		$backup_args['job-level'] = $args['job-level'];
		if (key_exists('prepare-backup', $args)) {
			$backup_args['prepare-backup'] = $args['prepare-backup'];
		}
		$cmd = $this->getPluginCommand($action, $backup_args);
		return implode(' ', $cmd);
	}

	/**
	 * Get file backup plugin command.
	 *
	 * @param array $args plugin options
	 * @return string backup command
	 */
	private function getBackupFileCommand(array $args): string
	{
		$action = 'command/backup';
		$backup_args = $this->filterParametersByCategory($args, [
			self::PARAM_CAT_FILE_BACKUP
		]);
		$backup_args['file-method'] = true;
		$cmd = $this->getPluginCommand($action, $backup_args);
		return implode(' ', $cmd);
	}

	/**
	 * Get binary log backup plugin command.
	 *
	 * @param array $args plugin options
	 * @return string backup command
	 */
	private function getBackupBinLogCommand(array $args): string
	{
		$action = 'command/backup';
		$backup_args = $this->filterParametersByCategory($args, [
			self::PARAM_CAT_GENERAL,
			self::PARAM_CAT_BINLOG_BACKUP
		]);
		$backup_args['job-name'] = $args['job-name'];
		$backup_args['job-level'] = $args['job-level'];
		$backup_args['binlog-method'] = true;
		$cmd = $this->getPluginCommand($action, $backup_args);
		return implode(' ', $cmd);
	}

	/**
	 * Get SQL dump restore plugin command.
	 *
	 * @param array $args plugin options
	 * @param string $raction restore action
	 * @param string $item restore item
	 * @return string restore command
	 */
	private function getRestoreSQLCommand(array $args, string $raction, string $item): string
	{
		$action = 'command/restore';
		$restore_args = $this->filterParametersByCategory($args, [
			self::PARAM_CAT_GENERAL,
			self::PARAM_CAT_DUMP_RESTORE
		]);
		$restore_args['plugin-config'] = $args['plugin-config'];
		$restore_args['job-starttime'] = $args['job-starttime'];
		$restore_args['job-id'] = $args['job-id'];
		$restore_args['job-level'] = $args['job-level'];
		$restore_args['restore-item'] = $item;
		$restore_args['restore-action'] = $raction;
		$restore_args['where'] = '%w';
		$cmd = $this->getPluginCommand($action, $restore_args);
		return implode(' ', $cmd);
	}

	/**
	 * Get binary restore plugin command.
	 *
	 * @param array $args plugin options
	 * @param string $raction restore action
	 * @return string restore command
	 */
	private function getRestoreBinaryCommand(array $args, string $raction): string
	{
		$action = 'command/restore';
		$restore_args = $this->filterParametersByCategory($args, [
			self::PARAM_CAT_GENERAL,
			self::PARAM_CAT_BINARY_RESTORE
		]);
		$restore_args['plugin-config'] = $args['plugin-config'];
		$restore_args['job-starttime'] = $args['job-starttime'];
		$restore_args['job-id'] = $args['job-id'];
		$restore_args['job-level'] = $args['job-level'];
		$restore_args['restore-action'] = $raction;
		$restore_args['where'] = '%w';
		$cmd = $this->getPluginCommand($action, $restore_args);
		return implode(' ', $cmd);
	}

	/**
	 * Get file restore plugin command.
	 *
	 * @param array $args plugin options
	 * @param string $raction restore action
	 * @return string restore command
	 */
	private function getRestoreFileCommand(array $args, string $raction): string
	{
		$action = 'command/restore';
		$restore_args = $this->filterParametersByCategory($args, [
			self::PARAM_CAT_GENERAL,
			self::PARAM_CAT_FILE_RESTORE
		]);
		$restore_args['plugin-config'] = $args['plugin-config'];
		$restore_args['restore-action'] = $raction;
		$restore_args['job-id'] = $args['job-id'];
		$restore_args['job-level'] = $args['job-level'];
		$restore_args['where'] = '%w';
		$cmd = $this->getPluginCommand($action, $restore_args);
		return implode(' ', $cmd);
	}

	/**
	 * Get SQL dump path.
	 *
	 * @param array $args plugin options
	 * @param string $dir SQL dump directory name
	 * @param string $file SQL dump file name (without extension)
	 * @return string path
	 */
	private function getBackupSQLPath(array $args, string $dir, string $file): string
	{
		$path_args = $this->filterParametersByCategory($args, [
			self::PARAM_CAT_GENERAL
		]);
		$pname = $this->getPluginName();
		$path = sprintf(
			'/#%s/%s/%s/%s/%s.sql',
			$pname,
			$args['plugin-config'] ?? '',
			$path_args['server-name'],
			$dir,
			$file
		);
		return $path;
	}

	/**
	 * Get binary backup path.
	 *
	 * @param array $args plugin options
	 * @param string $file binary backup file name (without extension)
	 * @return string path
	 */
	private function getBackupBinaryPath(array $args, string $file): string
	{
		$pname = $this->getPluginName();
		$path = sprintf(
			'/#%s/%s/%s.mb',
			$pname,
			$args['plugin-config'] ?? '',
			$file
		);
		return $path;
	}

	/**
	 * Get file backup path.
	 *
	 * @param array $args plugin options
	 * @param string $item file backup item
	 * @return string path
	 */
	private function getBackupFilePath(array $args, $item): string
	{
		$path = $args[$item] ?? 'config';
		$path = $this->getFormattedFile($path, $args['job-starttime'], $args['job-id'], $args['job-level']);
		return $path;
	}

	/**
	 * Get all plugin commands.
	 *
	 * @param array $args plugin options
	 * @return array plugin commands
	 */
	public function getPluginCommands(array $args): array
	{
		$this->debug($args, Plugins::LOG_DEST_FILE);
		if (!$this->checkRequiredArgs($args)) {
			return [];
		}
		$cmds = [];
		if (key_exists('dump-method', $args)) {
			$cmds = array_merge($cmds, $this->getDumpPluginCommands($args));
		}
		if (key_exists('binary-method', $args)) {
			$cmds = array_merge($cmds, $this->getBinaryPluginCommands($args));
		}
		if (key_exists('file-method', $args)) {
			$cmds = array_merge($cmds, $this->getFilePluginCommands($args));
		}
		if (key_exists('binlog-method', $args)) {
			$cmds = array_merge($cmds, $this->getBinLogPluginCommands($args));
		}
		$this->debug($cmds, Plugins::LOG_DEST_FILE);
		return $cmds;
	}

	/**
	 * Check required plugin options.
	 *
	 * @param array $args plugin options
	 * @return bool true on validation success, false otherwise
	 */
	private function checkRequiredArgs(array $args): bool
	{
		$valid = true;
		$required = ['plugin-name', 'plugin-config', 'job-id'];
		for ($i = 0; $i < count($required); $i++) {
			if (!key_exists($required[$i], $args)) {
				$valid = false;
				Plugins::log(Plugins::LOG_ERROR, "Missing required parameter '{$required[$i]}'.");
				break;
			}
		}
		return $valid;
	}

	/**
	 * Get SQL dump backup plugin commands.
	 *
	 * @param array $args plugin options
	 * @return array plugin commands
	 */
	private function getDumpPluginCommands(array $args): array
	{
		$cmds = [];
		$level = $args['job-level'] ?? self::DEFAULT_JOB_LEVEL;
		$this->preparePluginCommandArgs($args, self::BACKUP_METHOD_DUMP);
		switch ($level) {
			case 'Full': {
				$cmds = $this->getFullDumpBackupPluginCommands($args);
				break;
			}
			case 'Incremental': {
				$cmds = $this->getIncrementalDumpBackupPluginCommands($args);
				break;
			}
			case 'Differential': {
				$cmds = $this->getDifferentialDumpBackupPluginCommands($args);
				break;
			}
		}
		return $cmds;
	}

	/**
	 * Get binary backup plugin commands.
	 *
	 * @param array $args plugin options
	 * @return array plugin commands
	 */
	private function getBinaryPluginCommands(array $args): array
	{
		$cmds = [];
		$level = $args['job-level'] ?? self::DEFAULT_JOB_LEVEL;
		$this->preparePluginCommandArgs($args, self::BACKUP_METHOD_BINARY);
		switch ($level) {
			case 'Full': {
				$cmds = $this->getFullBinaryBackupPluginCommands($args);
				break;
			}
			case 'Incremental': {
				$cmds = $this->getIncrementalBinaryBackupPluginCommands($args);
				break;
			}
			case 'Differential': {
				// Differential method is not supported in binary method
				break;
			}
		}
		return $cmds;
	}

	/**
	 * Get file backup plugin commands.
	 *
	 * @param array $args plugin options
	 * @return array plugin commands
	 */
	private function getFilePluginCommands(array $args): array
	{
		$cmds = [];
		$level = $args['job-level'] ?? self::DEFAULT_JOB_LEVEL;
		switch ($level) {
			case 'Full': {
				$cmds = $this->getFullFileBackupPluginCommands($args);
				break;
			}
			case 'Incremental': {
				// Incremental level is not supported in file method
				break;
			}
			case 'Differential': {
				// Differential level is not supported in file method
				break;
			}
		}
		return $cmds;
	}

	/**
	 * Get binary log backup plugin commands.
	 *
	 * @param array $args plugin options
	 * @return array plugin commands
	 */
	private function getBinLogPluginCommands(array $args): array
	{
		$cmds = [];
		$level = $args['job-level'] ?? self::DEFAULT_JOB_LEVEL;
		switch ($level) {
			case 'Full': {
				$cmds = $this->getFullBinLogBackupPluginCommands($args);
				break;
			}
			case 'Incremental': {
				$cmds = $this->getIncrementalBinLogBackupPluginCommands($args);
				// Incremental level is not supported in binlog method
				break;
			}
			case 'Differential': {
				// Differential level is not supported in binlog method
				break;
			}
		}
		return $cmds;
	}

	/**
	 * Common method to prepare plugin command parameters.
	 *
	 * @param array $args plugin options
	 * @param string $method backup method (ex. 'file', 'dump', 'binary' or 'binlog')
	 */
	private function preparePluginCommandArgs(array &$args, string $method): void
	{
		/*
		 * Binary method can't be passed in dump method because it can cause
		 * running the same binary backup multiple times. The same is for using
		 * dump method in binary method.
		 */
		$methods = [
			self::BACKUP_METHOD_DUMP,
			self::BACKUP_METHOD_BINARY,
			self::BACKUP_METHOD_FILE,
			self::BACKUP_METHOD_BINLOG
		];
		for ($i = 0; $i < count($methods); $i++) {
			if ($methods[$i] == $method) {
				continue;
			}
			$key = "{$methods[$i]}-method";
			if (key_exists($key, $args)) {
				unset($args[$key]);
			}
		}
	}

	/**
	 * Main method to do backup.
	 *
	 * @param array $args plugin options
	 * @return bool backup status - true on success, false otherwise
	 */
	public function doBackup(array $args): bool
	{
		$this->debug($args);
		$st_dump = $st_bin = $st_file = $st_binlog = true;
		if (key_exists('dump-method', $args)) {
			$st_dump = $this->doDumpBackup($args);
		}
		if (key_exists('binary-method', $args)) {
			$st_bin = $this->doBinaryBackup($args);
		}
		if (key_exists('file-method', $args)) {
			$st_file = $this->doFileBackup($args);
		}
		if (key_exists('binlog-method', $args)) {
			$st_binlog = $this->doBinLogBackup($args);
		}
		$this->debug(['dump' => $st_dump, 'bin' => $st_bin, 'file' => $st_file, 'binlog' => $st_binlog]);
		return ($st_dump && $st_bin && $st_file);
	}

	/**
	 * Run SQL dump backup.
	 *
	 * @param array $args plugin options
	 * @return bool backup status - true on success, false otherwise
	 */
	private function doDumpBackup(array $args): bool
	{
		$result = false;
		$level = $args['job-level'] ?? self::DEFAULT_JOB_LEVEL;
		switch ($level) {
			case 'Full': {
				$result = $this->doFullDumpBackup($args);
				break;
			}
			case 'Incremental': {
				$result = $this->doIncrementalDumpBackup($args);
				break;
			}
			case 'Differential': {
				$result = $this->doDifferentialDumpBackup($args);
				break;
			}
		}
		return $result;
	}

	/**
	 * Run full SQL dump backup.
	 *
	 * @param array $args plugin options
	 * @return bool backup status - true on success, false otherwise
	 */
	private function doFullDumpBackup(array $args): bool
	{
		$is_data = false;
		if (key_exists('databases', $args)) {
			if (key_exists('no-data', $args)) {
				Plugins::log(Plugins::LOG_INFO, "Doing '{$args['databases']}' database schema backup.");
			} else {
				$is_data = true;
				Plugins::log(Plugins::LOG_INFO, "Doing '{$args['databases']}' database data backup.");
			}
		} elseif (key_exists('system', $args)) {
			Plugins::log(Plugins::LOG_INFO, "Doing '{$args['system']}' system data backup.");
		}

		// Prepare general parameters
		$cmd_params = $this->getGeneralParams($args);

		// Prepare backup specific arguments
		$db_param = '';
		if (key_exists('all-databases', $args)) {
			$cmd_params['all-databases'] = true;
		}
		if (key_exists('events', $args)) {
			$cmd_params['events'] = true;
		}
		if (key_exists('routines', $args)) {
			$cmd_params['routines'] = true;
		}
		if (key_exists('triggers', $args)) {
			$cmd_params['triggers'] = true;
		}
		if (key_exists('system', $args)) {
			$cmd_params['system'] = $args['system'];
		}
		if (key_exists('no-data', $args)) {
			$cmd_params['no-data'] = $args['no-data'];
		}
		if (key_exists('no-create-info', $args)) {
			$cmd_params['no-create-info'] = $args['no-create-info'];
		}
		if (!key_exists('add-drop-table', $args) || !$args['add-drop-table']) {
			$cmd_params['skip-add-drop-table'] = true;
		}
		if (key_exists('dump-option', $args)) {
			$dump_option = $args['dump-option'];
			if (!is_array($dump_option)) {
				$dump_option = [$dump_option];
			}
			for ($i = 0; $i < count($dump_option); $i++) {
				$param = $this->parseCommandParameter($dump_option[$i]);
				$cmd_params = array_merge($cmd_params, $param);
			}
		}
		if (key_exists('databases', $args) && $args['databases'] != self::ALL_DATABASES) {
			$db_param = $args['databases'];
		}
		$params = array_merge($cmd_params, self::SQL_DUMP_PROGRAM_COMMON_OPTS);
		$cmd = $this->prepareCommandParameters($params);
		if ($db_param) {
			$cmd[] = $db_param;
		}
		$bin = $this->getBinPath($args, self::SQL_DUMP_PROGRAM);
		array_unshift($cmd, $bin);
		if ($is_data) {
			[$tmpdir, $tmpprefix] = $this->getFileParams(self::BACKUP_METHOD_DUMP);
			$tmpfname = tempnam($tmpdir, $tmpprefix);
			$cmd[] = sprintf(
				'| { head -c%d 1> %s; cat %s -; }',
				4096,
				$tmpfname,
				$tmpfname
			);
		}
		$result = $this->execCommand($cmd, true);
		if ($is_data) {
			$cors = $this->parseFullDumpBackupCors($tmpfname);
			$this->setDumpBackupCors($cors, $args['job-name'], $args['databases'], $args['job-level']);
		}
		return ($result['exitcode'] === 0);
	}

	/**
	 * Run incremental dump backup.
	 *
	 * @param array $args plugin options
	 * @return bool backup status - true on success, false otherwise
	 */
	private function doIncrementalDumpBackup(array $args): bool
	{
		$imsg = "Start incremental '{$args['databases']}' database backup.'";
		Plugins::log(Plugins::LOG_INFO, $imsg);

		$cors = $this->getDumpBackupCors($args['job-name'], $args['databases'], $args['job-level']);
		if ($cors['position'] == -1 && $args['databases'] == self::ALL_DATABASES) {
			/**
			 * For single dbs backup, missing coordinates can be caused by
			 * empty binary log for given db in given time. In this case it is not an error.
			 */
			$emsg = "There is not possible to do incremental backup because previous backup coordinates are missing. Please run full backup first.";
			Plugins::log(Plugins::LOG_ERROR, $emsg);
			return false;
		}

		// Prepare parameters
		$cmd_params = $this->getGeneralParams($args);
		$params = array_merge($cmd_params, self::SQL_BINLOG_PROGRAM_COMMON_OPTS);
		if ($args['databases'] != self::ALL_DATABASES) {
			$params['database'] = $args['databases'];
		}
		$params['start-position'] = $cors['position'];
		$cmd = $this->prepareCommandParameters($params);
		$cmd[] = $cors['logfile'];


		// Create temporary file for determining backup coordinates in binary log
		[$tmpdir, $tmpprefix] = $this->getFileParams(self::BACKUP_METHOD_DUMP);
		$tmpfname = tempnam($tmpdir, $tmpprefix);
		$fd = $this->getFreeFD();
		$cmd[] = sprintf(
			'| { exec %d<&1; tee /dev/fd/%d | grep -iE -B8 \'(End of log file|Rotate to .+ pos:[[:space:]]+[[:digit:]]+)\' > %s; exec %d<&-; }',
			$fd,
			$fd,
			$tmpfname,
			$fd,
		);
		$bin = $this->getBinPath($args, self::SQL_BINLOG_PROGRAM);
		array_unshift($cmd, $bin);

		// Before doing anything, flush binary logs
		$this->flushBinLogs($args);

		// Run incremental backup
		$result = $this->execCommand($cmd, true);

		// Get backup coordinates
		$cors = $this->parseIncrementalDumpBackupCors($tmpfname);
		$this->setDumpBackupCors($cors, $args['job-name'], $args['databases'], $args['job-level']);

		$success = ($result['exitcode'] === 0);
		if (!$success) {
			$emsg = "Error while running incremental backup. ExitCode: '{$result['exitcode']}'.";
			Plugins::log(Plugins::LOG_ERROR, $emsg);
		}
		return $success;
	}

	/**
	 * Run differential SQL dump backup.
	 *
	 * @param array $args plugin options
	 * @return bool backup status - true on success, false otherwise
	 */
	private function doDifferentialDumpBackup(array $args): bool
	{
		$imsg = "Start differential '{$args['databases']}' database backup.'";
		Plugins::log(Plugins::LOG_INFO, $imsg);

		$cors = $this->getDumpBackupCors($args['job-name'], $args['databases'], $args['job-level']);
		if ($cors['position'] === -1 && $args['databases'] == self::ALL_DATABASES) {
			$emsg = "There is not possible to do differential backup because previous backup coordinates are missing. Please run full backup first.";
			Plugins::log(Plugins::LOG_ERROR, $emsg);
			return false;
		}

		// Prepare parameters
		$cmd_params = $this->getGeneralParams($args);
		$params = array_merge($cmd_params, self::SQL_BINLOG_PROGRAM_COMMON_OPTS);
		if ($args['databases'] != self::ALL_DATABASES) {
			$params['database'] = $args['databases'];
		}
		$params['start-position'] = $cors['position'];
		$cmd = $this->prepareCommandParameters($params);
		$cmd[] = $cors['logfile'];

		// Create temporary file for determining backup coordinates in binary log
		[$tmpdir, $tmpprefix] = $this->getFileParams(self::BACKUP_METHOD_DUMP);
		$tmpfname = tempnam($tmpdir, $tmpprefix);
		$fd = $this->getFreeFD();
		$cmd[] = sprintf(
			'| { exec %d<&1; tee /dev/fd/%d | grep -iE -B8 \'(End of log file|Rotate to .+ pos:[[:space:]]+[[:digit:]]+)\' > %s; exec %d<&-; }',
			$fd,
			$fd,
			$tmpfname,
			$fd,
		);
		$bin = $this->getBinPath($args, self::SQL_BINLOG_PROGRAM);
		array_unshift($cmd, $bin);

		// Before doing anything, flush binary logs
		$this->flushBinLogs($args);

		// Run differential backup
		$result = $this->execCommand($cmd, true);

		// Get backup coordinates
		$cors = $this->parseDifferentialDumpBackupCors($tmpfname);
		$this->setDumpBackupCors($cors, $args['job-name'], $args['databases'], $args['job-level']);

		$success = ($result['exitcode'] === 0);
		if (!$success) {
			$emsg = "Error while running differential backup. ExitCode: '{$result['exitcode']}'.";
			Plugins::log(Plugins::LOG_ERROR, $emsg);
		}
		return $success;
	}

	/**
	 * Run binary backup.
	 *
	 * @param array $args plugin options
	 * @return bool backup status - true on success, false otherwise
	 */
	private function doBinaryBackup(array $args): bool
	{
		$result = false;
		$level = $args['job-level'] ?? self::DEFAULT_JOB_LEVEL;
		switch ($level) {
			case 'Full': {
				$result = $this->doFullBinaryBackup($args);
				break;
			}
			case 'Incremental': {
				$result = $this->doIncrementalBinaryBackup($args);
				break;
			}
			case 'Differential': {
				// Differential is not supported for binary method
				$result = $this->doDifferentialBinaryBackup($args);
				break;
			}
		}
		return $result;
	}

	/**
	 * Run full binary backup.
	 *
	 * @param array $args plugin options
	 * @return bool backup status - true on success, false otherwise
	 */
	private function doFullBinaryBackup(array $args): bool
	{
		$imsg = 'Start full binary backup.';
		Plugins::log(Plugins::LOG_INFO, $imsg);

		// Prepare general parameters
		$cmd_params = $this->getGeneralParams($args);
		$cmd_params['backup'] = true;

		// Prepare directory for a new backup (target-dir)
		$dir = $this->getFormattedDir(
			$args['job-name'],
			$args['job-id'],
			$args['job-level']
		);
		$target_dir = implode(DIRECTORY_SEPARATOR, [$args['binary-backup-path'], $dir]);
		if (!file_exists($target_dir)) {
			if (!mkdir($target_dir, 750, true)) {
				Plugins::log(Plugins::LOG_ERROR, "Unable to create full backup target-dir '{$target_dir}'.");
				return false;
			}
		}
		$cmd_params['target-dir'] = $target_dir;

		// Set parameters
		$cmd = $this->prepareCommandParameters($cmd_params);
		$cmd[] = '2>&1'; // it is because the backup program output is printed on stderr
		$bin = $this->getBinPath($args, self::BIN_BACKUP_PROGRAM);
		array_unshift($cmd, $bin);

		// Run full backup
		$result = $this->execCommand($cmd);
		$out = implode(PHP_EOL, $result['output']);
		Plugins::log(Plugins::LOG_INFO, "Raw backup output: '{$out}'.");
		$success = ($result['exitcode'] === 0);

		if ($success && key_exists('prepare-backup', $args)) {
			$imsg = 'Start binary backup prepare action.';
			Plugins::log(Plugins::LOG_INFO, $imsg);
			$success = $this->prepareBinaryBackup($args, $target_dir);
		}
		if ($success) {
			// Do binary data streaming to FD
			$state = $this->streamBinaryBackup($args, $target_dir);
			if ($state) {
				// Everything fine. Set backup coordinates
				$cors = $this->parseFullBinaryBackupCors($result['output']);
				$this->setBinaryBackupCors($cors, $args['job-name']);
			} else {
				Plugins::log(Plugins::LOG_ERROR, "Error while streaming full backup binaries from target-dir '{$target_dir}'.");
			}
		} else {
			Plugins::log(Plugins::LOG_ERROR, "Error while running full binary backup. ExitCode: '{$result['exitcode']}'.");
		}
		return $success;
	}

	/**
	 * Run incremental binary backup.
	 *
	 * @param array $args plugin options
	 * @return bool backup status - true on success, false otherwise
	 */
	private function doIncrementalBinaryBackup(array $args): bool
	{
		$imsg = 'Start incremental binary backup.';
		Plugins::log(Plugins::LOG_INFO, $imsg);

		$cors = $this->getBinaryBackupCors($args['job-name']);

		// Prepare general parameters
		$cmd_params = $this->getGeneralParams($args);
		$cmd_params['backup'] = true;
		$cmd_params['incremental-lsn'] = $cors['position'];

		// Prepare destination backup data directory (target-dir)
		$dir = $this->getFormattedDir(
			$args['job-name'],
			$args['job-id'],
			$args['job-level']
		);
		$target_dir = implode(DIRECTORY_SEPARATOR, [$args['binary-backup-path'], $dir]);
		if (!file_exists($target_dir)) {
			if (!mkdir($target_dir, 750, true)) {
				Plugins::log(Plugins::LOG_ERROR, "Unable to create incremental backup target-dir '{$target_dir}'.");
				return false;
			}
		}
		$cmd_params['target-dir'] = $target_dir;

		// Set parameters
		$cmd = $this->prepareCommandParameters($cmd_params);
		$cmd[] = '2>&1'; // it is because the backup program output is printed on stderr
		$bin = $this->getBinPath($args, self::BIN_BACKUP_PROGRAM);
		array_unshift($cmd, $bin);

		// Run incremental backup
		$result = $this->execCommand($cmd);
		$out = implode(PHP_EOL, $result['output']);
		Plugins::log(Plugins::LOG_INFO, "Raw backup output: '{$out}'.");
		$success = ($result['exitcode'] === 0);
		if ($success) {
			$state = $this->streamBinaryBackup($args, $target_dir);
			if ($state) {
				$cors = $this->parseFullBinaryBackupCors($result['output']);
				$this->setBinaryBackupCors($cors, $args['job-name']);
			} else {
				Plugins::log(Plugins::LOG_ERROR, "Error while streaming incremental backup binaries from target-dir '{$target_dir}'.");
			}
		} else {
			Plugins::log(Plugins::LOG_ERROR, "Error while running incremental backup. ExitCode: '{$result['exitcode']}'.");
		}
		return $success;
	}

	/**
	 * Run differential binary backup.
	 *
	 * @param array $args plugin options
	 * @return bool backup status - true on success, false otherwise
	 */
	private function doDifferentialBinaryBackup(array $args): bool
	{
		Plugins::log(
			Plugins::LOG_WARNING,
			'Differential backup level is not supported in the binary method backups.'
		);
		return true;
	}

	/**
	 * Run file backup.
	 *
	 * @param array $args plugin options
	 * @return bool backup status - true on success, false otherwise
	 */
	private function doFileBackup(array $args): bool
	{
		$result = false;
		$level = $args['job-level'] ?? self::DEFAULT_JOB_LEVEL;
		switch ($level) {
			case 'Full': {
				$result = $this->doFullFileBackup($args);
				break;
			}
			case 'Incremental': {
				// not supported
				break;
			}
			case 'Differential': {
				// not supported
				break;
			}
		}
		return $result;
	}

	/**
	 * Run full file backup.
	 *
	 * @param array $args plugin options
	 * @return bool backup status - true on success, false otherwise
	 */
	private function doFullFileBackup(array $args): bool
	{
		if (!key_exists('include-path', $args)) {
			$emsg = 'There is not provided any path to backup.';
			Plugins::log(Plugins::LOG_WARNING, $emsg);
			return false;
		}

		$imsg = "Start file backup '{$args['include-path']}'.";
		Plugins::log(Plugins::LOG_INFO, $imsg);

		$cmd = [];
		$cmd[] = sprintf(
			self::FILE_BACKUP_COMMAND,
			$args['include-path']
		);
		$result = $this->execCommand($cmd, true);
		$success = ($result['exitcode'] === 0);
		if (!$success) {
			$emsg = "Error while doing full file backup. Path: '{$args['include-path']}' ExitCode: '{$result['exitcode']}'.";
			Plugins::log(Plugins::LOG_ERROR, $emsg);
		}
		return $success;
	}

	/**
	 * Run binary log backup.
	 *
	 * @param array $args plugin options
	 * @return bool backup status - true on success, false otherwise
	 */
	private function doBinLogBackup(array $args): bool
	{
		$result = false;
		$level = $args['job-level'] ?? self::DEFAULT_JOB_LEVEL;
		switch ($level) {
			case 'Full': {
				$result = $this->doFullBinLogBackup($args);
				break;
			}
			case 'Incremental': {
				$result = $this->doIncrementalBinLogBackup($args);
				break;
			}
			case 'Differential': {
				// not supported
				break;
			}
		}
		return $result;
	}

	/**
	 * Run full binary log backup.
	 *
	 * @param array $args plugin options
	 * @return bool backup status - true on success, false otherwise
	 */
	private function doFullBinLogBackup(array $args): bool
	{
		if (!key_exists('binlog-path', $args)) {
			$emsg = 'There is not provided binlog path to backup.';
			Plugins::log(Plugins::LOG_WARNING, $emsg);
			return false;
		}

		$imsg = "Start full binary log backup '{$args['binlog-path']}'.";
		Plugins::log(Plugins::LOG_INFO, $imsg);

		// Prepare latest binary log
		$bin_logs = $this->getBinLogs($args);
		$last_binlog = array_pop($bin_logs);

		// Do flush logs before doing backup
		$this->flushBinLogs($args);

		// Run full binary log backup
		$cmd = [];
		$cmd[] = sprintf(
			self::FILE_BACKUP_COMMAND,
			$args['binlog-path']
		);
		$result = $this->execCommand($cmd, true);
		$success = ($result['exitcode'] === 0);
		if ($success) {
			$this->setBinLogBackupCors(['logfile' => $last_binlog], $args['job-name']);
		} else {
			$emsg = "Error while doing full binlog backup. Path: '{$args['binlog-path']}' ExitCode: '{$result['exitcode']}'.";
			Plugins::log(Plugins::LOG_ERROR, $emsg);
		}

		return $success;
	}

	/**
	 * Run incremental binary log backup.
	 *
	 * @param array $args plugin options
	 * @return bool backup status - true on success, false otherwise
	 */
	private function doIncrementalBinLogBackup(array $args): bool
	{
		if (!key_exists('binlog-path', $args)) {
			$emsg = 'There is not provided binlog path to backup.';
			Plugins::log(Plugins::LOG_WARNING, $emsg);
			return false;
		}

		$imsg = "Start incremental binlog backup '{$args['binlog-path']}'.";
		Plugins::log(Plugins::LOG_INFO, $imsg);

		$last_binlog = $this->getBinLogBackupCors($args['job-name']);
		$bin_logs = $this->getBinLogs($args, $last_binlog['logfile']);

		// Do flush logs before doing backup
		$this->flushBinLogs($args);

		// Save binary log file list to backup
		[$tmpdir, $tmpprefix] = $this->getFileParams(self::BACKUP_METHOD_BINLOG);
		$tmpfname = tempnam($tmpdir, $tmpprefix);
		$cb = fn ($item) => implode(DIRECTORY_SEPARATOR, [$args['binlog-path'], $item]);
		$file_list = implode(PHP_EOL, array_map($cb, $bin_logs));
		if (file_put_contents($tmpfname, $file_list) === false) {
			Plugins::log(Plugins::LOG_ERROR, "Unable to write to '{$tmpfname}' file.");
			return false;
		}

		// Run incremental binary log backup
		$cmd = [];
		$cmd[] = sprintf(
			self::BINLOG_BACKUP_COMMAND,
			$tmpfname
		);
		$cmd[] = implode(DIRECTORY_SEPARATOR, [$args['binlog-path'], '*.index']);
		$result = $this->execCommand($cmd, true);
		$success = ($result['exitcode'] === 0);
		if (!$success) {
			$emsg = "Error while doing incremental binlog backup. Path: '{$args['binlog-path']}' ExitCode: '{$result['exitcode']}'.";
			Plugins::log(Plugins::LOG_ERROR, $emsg);
		}

		// Clean up
		unlink($tmpfname);

		return $success;
	}

	/**
	 * Main restore command.
	 *
	 * @param array $args plugin options
	 * @return bool restore status - true on success, false otherwise
	 */
	public function doRestore(array $args): bool
	{
		$this->debug($args);
		$result = false;
		if (in_array($args['restore-action'], [self::ACTION_SQL_ALL_DBS, self::ACTION_SQL_DATA, self::ACTION_SYSTEM, self::ACTION_SCHEMA])) {
			// SQL dump restore
			$result = $this->doSQLRestore($args);
		} elseif (in_array($args['restore-action'], [self::ACTION_BINARY_DATA])) {
			// Binary data restore
			$result = $this->doLocalFileRestore($args);
		} elseif (in_array($args['restore-action'], [self::ACTION_FILE, self::ACTION_DIR, self::ACTION_BINLOG])) {
			// File restore
			$result = $this->doFileRestore($args);
		}
		$this->debug(['result' => $result]);
		return $result;
	}

	/**
	 * Run SQL dump restore command.
	 *
	 * @param array $args plugin options
	 * @return bool restore status - true on success, false otherwise
	 */
	private function doSQLRestore(array $args): bool
	{
		if (key_exists('where', $args) && $args['where'] != '/') {
			// do restore to local FD file system
			return $this->doLocalFileRestore($args);
		}

		// Prepare general parameters
		$cmd_params = $this->getGeneralParams($args);

		// Prepare restore specific arguments
		if (in_array($args['restore-action'], [self::ACTION_SCHEMA, self::ACTION_SQL_DATA])) {
			if (key_exists('database', $args) && $args['database']) {
				// restore to typed new database
				$cmd_params['database'] = $args['database'];
				Plugins::log(Plugins::LOG_INFO, "Doing restore to new '{$args['database']}' database.");
			} else {
				// restore to original database from backup
				$cmd_params['database'] = $args['database'] = $args['restore-item'];
				Plugins::log(Plugins::LOG_INFO, "Doing restore'{$args['database']}' database.");
			}
		}

		if ($args['restore-action'] == self::ACTION_SCHEMA && !$this->createDatabase($args)) { // create database if needed
			return false;
		}

		// Set parameters
		$cmd = $this->prepareCommandParameters($cmd_params);
		$bin = $this->getBinPath($args, self::SQL_CLI_PROGRAM);
		array_unshift($cmd, $bin);

		// Run command
		$result = $this->execCommand($cmd);

		$success = ($result['exitcode'] === 0);
		if (!$success) {
			$emsg = "Error while doing restore data. ExitCode: '{$result['exitcode']}'.";
			Plugins::log(Plugins::LOG_ERROR, $emsg);
		}
		return $success;
	}

	/**
	 * Run file restore command.
	 *
	 * @param array $args plugin options
	 * @return bool restore status - true on success, false otherwise
	 */
	private function doFileRestore(array $args): bool
	{
		$where = '/';
		if (key_exists('where', $args) && $args['where'] != '/') {
			$where = rtrim($args['where'], '/');
			if (!file_exists($where) && !mkdir($where, 0750, true)) {
				Plugins::log(Plugins::LOG_ERROR, "Error while creating restore file path {$where}.");
			}
		}

		$imsg = "Start file restore to '{$args['where']}'.";
		Plugins::log(Plugins::LOG_INFO, $imsg);

		$cmd = [];
		$cmd[] = sprintf(
			self::FILE_RESTORE_COMMAND,
			$where
		);
		// Run command
		$result = $this->execCommand($cmd);

		$success = ($result['exitcode'] === 0);
		if (!$success) {
			$output = implode(PHP_EOL, $result['output']);
			$emsg = "Error while doing restore data to path '{$where}'. ExitCode: '{$result['exitcode']}', Output: '{$output}'.";
			Plugins::log(Plugins::LOG_ERROR, $emsg);
		}
		return $success;
	}

	/**
	 * Get all database list.
	 *
	 * @param array $args plugin options
	 * @return array get database command output and exit code
	 */
	private function getDatabases(array $args): array
	{
		// Prepare general parameters
		$cmd_params = $this->getGeneralParams($args);

		// Prepare getting database arguments
		$cmd_params['batch'] = true;
		$cmd_params['disable-column-names'] = true;
		$cmd = $this->prepareCommandParameters($cmd_params);
		$cmd[] = '--execute';
		$cmd[] = '"SHOW DATABASES"';
		$bin = $this->getBinPath($args, self::SQL_CLI_PROGRAM);
		array_unshift($cmd, $bin);

		$dbs = [];
		// Run command
		$result = $this->execCommand($cmd);
		if ($result['exitcode'] == 0) {
			$dbs = $result['output'];
			$dbs = array_filter($dbs, fn ($db) => !in_array($db, self::IGNORE_SYSTEM_TABLES));
			$dbs = array_values($dbs);
		}
		return $dbs;
	}

	/**
	 * Create database.
	 *
	 * @param array $args plugin options
	 * @return bool create database status - true on success, otherwise false
	 */
	private function createDatabase(array $args): bool
	{
		// Prepare general parameters
		$cmd_params = $this->getGeneralParams($args);

		// Prepare creating database parameters
		$cmd = $this->prepareCommandParameters($cmd_params);
		$bin = $this->getBinPath($args, self::SQL_CLI_PROGRAM);
		array_unshift($cmd, $bin);
		$cmd[] = '--execute';
		$cmd[] = "'CREATE DATABASE IF NOT EXISTS `{$args['database']}`'";

		// Run command
		$result = $this->execCommand($cmd);
		$ret = ($result['exitcode'] === 0);
		if (!$ret) {
			$output = implode(PHP_EOL, $result['output']);
			$emsg = "Error while creating new database '{$args['database']}'. ExitCode: {$result['exitcode']}, Output: {$output}.";
			Plugins::log(Plugins::LOG_ERROR, $emsg);
		}
		return $ret;
	}

	/**
	 * Get binary log file list.
	 *
	 * @param array $args plugin options
	 * @param string $start_with starting log file (used for incremental backup)
	 * @return array binary log list
	 */
	private function getBinLogs(array $args, string $start_with = ''): array
	{
		// Prepare general parameters
		$cmd_params = $this->getGeneralParams($args);

		// Prepare getting database arguments
		$cmd_params['batch'] = true;
		$cmd_params['disable-column-names'] = true;
		$cmd = $this->prepareCommandParameters($cmd_params);
		$cmd[] = '--execute';
		$cmd[] = '"SHOW MASTER LOGS"';
		$bin = $this->getBinPath($args, self::SQL_CLI_PROGRAM);
		array_unshift($cmd, $bin);

		// Run command
		$bin_logs = [];
		$result = $this->execCommand($cmd);
		if ($result['exitcode'] == 0) {
			$bin_logs = $this->parseBinLogList($result['output']);
			$bin_logs_len = count($bin_logs);
			if ($start_with && $bin_logs_len > 0) {
				$index = array_search($start_with, $bin_logs);
				$bin_logs = array_splice($bin_logs, $index, $bin_logs_len - $index);
			}
		} else {
			$output = implode(PHP_EOL, $result['output']);
			$emsg = "Error while getting binary log list. ExitCode: {$result['exitcode']}, Output: {$output}.";
			Plugins::log(Plugins::LOG_ERROR, $emsg);
		}
		return $bin_logs;
	}

	/**
	 * Parse binary log list.
	 *
	 * @param array $output binary log list command output
	 * @return array parsed binary log list
	 */
	private function parseBinLogList(array $output): array
	{
		$log_files = [];
		for ($i = 0; $i < count($output); $i++) {
			if (preg_match('/^(?P<logfile>\S+)\s+(?P<size>\d+)/', $output[$i], $match) === 1) {
				$log_files[] = $match['logfile'];
			}
		}
		return $log_files;
	}

	/**
	 * Flush binary logs.
	 *
	 * @param array $args plugin options
	 * @return bool true on success, otherwise false
	 */
	private function flushBinLogs(array $args): bool
	{
		// Prepare general parameters
		$cmd_params = $this->getGeneralParams($args);

		// Prepare getting database arguments
		$cmd = $this->prepareCommandParameters($cmd_params);
		$cmd[] = '--execute';
		$cmd[] = '"FLUSH BINARY LOGS"';
		$bin = $this->getBinPath($args, self::SQL_CLI_PROGRAM);
		array_unshift($cmd, $bin);

		// Run command
		$ret = [];
		$result = $this->execCommand($cmd);
		$ret = ($result['exitcode'] == 0);
		if (!$ret) {
			$output = implode(PHP_EOL, $result['output']);
			$emsg = "Error while flushing binary log list. ExitCode: {$result['exitcode']}, Output: {$output}.";
			Plugins::log(Plugins::LOG_ERROR, $emsg);
		}
		return $ret;
	}

	/**
	 * Run streaming binary backup.
	 *
	 * @param array $args plugin options
	 * @param string $backup_dir backup directory to stream
	 * @return bool true on success, otherwise false
	 */
	private function streamBinaryBackup(array $args, string $backup_dir): bool
	{
		// Prepare parameters
		$bin = $this->getBinPath($args, self::BIN_STREAM_PROGRAM);
		$cmd = ["cd \"{$backup_dir}\"", '&&', 'find', '.', '-type', 'f', '-exec', $bin, '-c', '{}', '+'];

		// Run command
		$result = $this->execCommand($cmd, true);
		$success = ($result['exitcode'] == 0);
		if (!$success) {
			$emsg = "Error while running binary backup. ExitCode: {$result['exitcode']}.";
			Plugins::log(Plugins::LOG_ERROR, $emsg);
		}
		return $success;
	}

	/**
	 * Run binary backup prepare action.
	 *
	 * @param array $args plugin options
	 * @param string $backup_dir backup directory to stream
	 * @return bool true on success, otherwise false
	 */
	private function prepareBinaryBackup(array $args, string $backup_dir): bool
	{
		// Prepare parameters
		$cmd_params = [];
		$cmd_params['prepare'] = true;
		$cmd_params['target-dir'] = $backup_dir;
		$cmd = $this->prepareCommandParameters($cmd_params);
		$bin = $this->getBinPath($args, self::BIN_BACKUP_PROGRAM);
		array_unshift($cmd, $bin);

		// Run command
		$result = $this->execCommand($cmd);
		$success = ($result['exitcode'] == 0);
		if (!$success) {
			$output = implode(PHP_EOL, $result['output']);
			$emsg = "Error while running binary backup prepare. Output: '{$output}' ExitCode: '{$result['exitcode']}'.";
			Plugins::log(Plugins::LOG_ERROR, $emsg);
		}
		return $success;
	}

	/**
	 * Get general backup tool parameters.
	 *
	 * @param array $args plugin options
	 * @return array backup tool parameters
	 */
	private function getGeneralParams(array $args): array
	{
		$params = [];
		if (key_exists('defaults-extra-file', $args)) {
			$params['defaults-extra-file'] = $args['defaults-extra-file'];
		}
		if (key_exists('user', $args)) {
			$params['user'] = $args['user'];
		}
		if (key_exists('password', $args)) {
			$params['password'] = $args['password'];
		}
		return $params;
	}

	/**
	 * Run local file restore.
	 *
	 * @param array $args plugin options
	 * @return bool true on success, false otherwise
	 */
	private function doLocalFileRestore(array $args): bool
	{
		$dir = $args['restore-item'] ?? '';
		$file = $args['restore-action'];
		$restore_path = '';
		if ($args['restore-action'] == self::ACTION_SYSTEM) {
			$dir = self::SYSTEM_DATA_DIR;
			$file = $args['restore-item'];
			$restore_path = $this->getBackupSQLPath($args, $dir, $file);
		} elseif (in_array($args['restore-action'], [self::ACTION_SQL_ALL_DBS, self::ACTION_SQL_DATA])) {
			$file = $this->getFormattedFile($file, $args['job-starttime'], $args['job-id'], $args['job-level']);
			$restore_path = $this->getBackupSQLPath($args, $dir, $file);
		} elseif ($args['restore-action'] == self::ACTION_SCHEMA) {
			$file = self::ACTION_SCHEMA;
			$restore_path = $this->getBackupSQLPath($args, $dir, $file);
		} elseif ($args['restore-action'] == self::ACTION_BINARY_DATA) {
			$file = $this->getFormattedFile($file, $args['job-starttime'], $args['job-id'], $args['job-level']);
			$restore_path = $this->getBackupBinaryPath($args, $file);
		}
		$filename = basename($restore_path);
		$restore_dir = implode(DIRECTORY_SEPARATOR, [
			$args['where'],
			dirname($restore_path)
		]);
		if (!file_exists($restore_dir)) {
			if (!mkdir($restore_dir, 0750, true)) {
				Plugins::log(Plugins::LOG_ERROR, "Error while creating restore path {$restore_dir}.");
				return false;
			}
		}
		$restore_path = implode(DIRECTORY_SEPARATOR, [
			$restore_dir,
			$filename
		]);
		$cmd = ['tee', $restore_path, '>/dev/null'];

		$result = $this->execCommand($cmd);
		$success = ($result['exitcode'] === 0);
		if (!$success) {
			$output = implode(PHP_EOL, $result['output']);
			$emsg = "Error while running binary restore. Output: '{$output}' ExitCode: '{$result['exitcode']}'.";
			Plugins::log(Plugins::LOG_ERROR, $emsg);
		}
		return $success;
	}

	/**
	 * Parse full SQL dump backup position coordinates.
	 *
	 * @param string $tmpfile temporary file with the SQL dump coordinates output.
	 * @return array backup position coordinates
	 */
	private function parseFullDumpBackupCors(string $tmpfile): array
	{
		$content = file_get_contents($tmpfile);
		$cor = ['logfile' => '', 'position' => 0];
		if (preg_match("/MASTER_LOG_FILE='(?P<logfile>[^']+)', MASTER_LOG_POS=(?P<position>\d+)/i", $content, $match) == 1) {
			$cor['logfile'] = $match['logfile'];
			$cor['position'] = $match['position'];
		}
		unlink($tmpfile);
		return $cor;
	}

	/**
	 * Parse incremental SQL dump backup position coordinates.
	 *
	 * @param string $tmpfile temporary file with the SQL dump coordinates output.
	 * @return array backup position coordinates
	 */
	private function parseIncrementalDumpBackupCors(string $tmpfile): array
	{
		$content = file($tmpfile);
		$cor = ['logfile' => '', 'position' => -1];
		for ($i = 0; $i < count($content); $i++) {
			/**
			 * Example output line:
			 * #700101  1:00:00 server id 1  end_log_pos 0 CRC32 0x24cf178b   Rotate to mysqld-bin.000099  pos: 4
			 */
			if (preg_match("/\s+end_log_pos\s+(?P<position>\d+)\s+\S+\s+\S+\s+(?P<event>.+)$/i", $content[$i], $match) === 1) {
				if (preg_match('/Rotate\s+to\s+(?P<logfile>.+)\s{2,}pos:\s+\d+$/i', $match['event'], $mres) == 1) {
					$cor['logfile'] = $mres['logfile'];
				}
				$cor['position'] = ((int) $match['position']);
			}
		}
		unlink($tmpfile);
		return $cor;
	}

	/**
	 * Parse differential SQL dump backup position coordinates.
	 *
	 * @param string $tmpfile temporary file with the SQL dump coordinates output.
	 * @return array backup position coordinates
	 */
	private function parseDifferentialDumpBackupCors(string $tmpfile): array
	{
		// so far it is the same as in case incremental
		return $this->parseIncrementalDumpBackupCors($tmpfile);
	}

	/**
	 * Parse full binary backup position coordinates.
	 *
	 * @param array $output binary backup coordinates output.
	 * @return array backup position coordinates
	 */
	private function parseFullBinaryBackupCors(array $output): array
	{
		$cor = ['position' => 0];
		for ($i = (count($output) - 1); $i >= 0 ; $i--) {
			if (preg_match("/\s+The\s+latest\s+check\s+point.+:\s+'?(?P<position>\d+)'?/i", $output[$i], $match) == 1) {
				$cor['position'] = $match['position'];
				break;
			}
		}
		return $cor;
	}

	/**
	 * Set SQL dump backup position coordinates.
	 *
	 * @param array $cors backup coordinates
	 * @param string $job_name backup job name
	 * @param string $db_name backup database name
	 * @param string $level backup level (ex. 'Full', 'Incremental', 'Differential')
	 * @return array true on success, false otherwise
	 */
	private function setDumpBackupCors(array $cors, string $job_name, string $db_name, string $level): bool
	{
		if ($cors['position'] === -1) {
			Plugins::log(
				Plugins::LOG_INFO,
				sprintf('No statement in binary log for database \'%s\'.', $db_name)
			);
			return true;
		}
		$body = $this->getJobBackupCors($job_name, self::BACKUP_METHOD_DUMP);
		if (!key_exists($db_name, $body)) {
			$body[$db_name] = [];
		}
		$body[$db_name][$level] = $cors;
		if ($level == 'Full') {
			// It is full backup, so delete incremental and differential cors
			if (key_exists('Incremental', $body[$db_name])) {
				unset($body[$db_name]['Incremental']);
			}
			if (key_exists('Differential', $body[$db_name])) {
				unset($body[$db_name]['Differential']);
			}
		} elseif ($level == 'Differential') {
			// It is full backup, so delete incremental and differential cors
			if (key_exists('Incremental', $body[$db_name])) {
				unset($body[$db_name]['Incremental']);
			}
		}
		$value = json_encode($body);
		$statepath = $this->getJobCorsPath($job_name, self::BACKUP_METHOD_DUMP);
		$result = (file_put_contents($statepath, $value, LOCK_EX) !== false);
		return $result;
	}

	/**
	 * Get SQL dump backup position coordinates.
	 *
	 * @param string $job_name backup job name
	 * @param string $db_name backup database name
	 * @param string $level backup level (ex. 'Full', 'Incremental', 'Differential')
	 * @return array backup position coordinates
	 */
	private function getDumpBackupCors(string $job_name, string $db_name, string $level): array
	{
		$cors = $this->getJobBackupCors($job_name, self::BACKUP_METHOD_DUMP);
		$result = ['logfile' => '', 'position' => -1];
		if (key_exists($db_name, $cors)) {
			if ($level == 'Incremental') {
				if (key_exists('Incremental', $cors[$db_name])) {
					$result = $cors[$db_name]['Incremental'];
				} elseif (key_exists('Differential', $cors[$db_name])) {
					$result = $cors[$db_name]['Differential'];
				} elseif (key_exists('Full', $cors[$db_name])) {
					$result = $cors[$db_name]['Full'];
				}
			} elseif ($level == 'Differential') {
				if (key_exists('Full', $cors[$db_name])) {
					$result = $cors[$db_name]['Full'];
				}
			}
		}
		return $result;
	}

	/**
	 * Set binary backup position coordinates.
	 *
	 * @param array $cors backup coordinates
	 * @param string $job_name backup job name
	 * @return array true on success, false otherwise
	 */
	private function setBinaryBackupCors(array $cors, string $job_name): bool
	{
		$body = $this->getJobBackupCors($job_name, self::BACKUP_METHOD_BINARY);
		$body['Incremental'] = $cors;
		$value = json_encode($body);
		$statepath = $this->getJobCorsPath($job_name, self::BACKUP_METHOD_BINARY);
		$result = (file_put_contents($statepath, $value, LOCK_EX) !== false);
		return $result;
	}

	/**
	 * Get binary backup position coordinates.
	 *
	 * @param string $job_name backup job name
	 * @return array backup position coordinates
	 */
	private function getBinaryBackupCors(string $job_name): array
	{
		$cors = $this->getJobBackupCors($job_name, self::BACKUP_METHOD_BINARY);
		$result = ['position' => 0];
		if (key_exists('Incremental', $cors)) {
			$result = $cors['Incremental'];
		}
		return $result;
	}

	/**
	 * Set binary log backup position coordinates.
	 *
	 * @param array $cors backup coordinates
	 * @param string $job_name backup job name
	 * @return array true on success, false otherwise
	 */
	private function setBinLogBackupCors(array $cors, string $job_name): bool
	{
		$body = $this->getJobBackupCors($job_name, self::BACKUP_METHOD_BINLOG);
		$body['Incremental'] = $cors;
		$value = json_encode($body);
		$statepath = $this->getJobCorsPath($job_name, self::BACKUP_METHOD_BINLOG);
		return (file_put_contents($statepath, $value, LOCK_EX) !== false);
	}

	/**
	 * Get binary log backup position coordinates.
	 *
	 * @param string $job_name backup job name
	 * @return array backup position coordinates
	 */
	private function getBinLogBackupCors(string $job_name): array
	{
		$cors = $this->getJobBackupCors($job_name, self::BACKUP_METHOD_BINLOG);
		$result = ['logfile' => ''];
		if (key_exists('Incremental', $cors)) {
			$result = $cors['Incremental'];
		}
		return $result;
	}

	/**
	 * Get backup position coordinates.
	 *
	 * @param string $job_name backup job name
	 * @param string $method backup method ('dump', 'binary', 'binlog')
	 * @return array backup position coordinates or empty array if no coordinates to get
	 */
	private function getJobBackupCors(string $job_name, string $method): array
	{
		$result = [];
		$statepath = $this->getJobCorsPath($job_name, $method);
		if (file_exists($statepath)) {
			$content = file_get_contents($statepath);
			$result = json_decode($content, JSON_OBJECT_AS_ARRAY) ?: [];
		}
		return $result;
	}

	/**
	 * Get backup position coordinates file path.
	 *
	 * @param string $job_name backup job name
	 * @param string $method backup method ('dump', 'binary', 'binlog')
	 * @return string backup coordinates file path
	 */
	private function getJobCorsPath(string $job_name, string $method): string
	{
		$path = $this->getFileParams($method);
		$stfile = implode(DIRECTORY_SEPARATOR, $path) . $job_name;
		return $stfile;
	}

	/**
	 * Get plugin name.
	 *
	 * @return string plugin name
	 */
	private function getPluginName(): string
	{
		$rc = new \ReflectionClass($this);
		$cls = $rc->getShortName();
		return $cls;
	}

	/**
	 * Get formatted backup file name.
	 *
	 * @param string $file file name
	 * @param string $starttime backup job start time
	 * @param string $jobid backup job identifier
	 * @param string $level backup job level
	 * @return string formatted backup file name
	 */
	private function getFormattedFile(string $file, string $starttime, string $jobid, string $level): string
	{
		return sprintf(
			'%s-%d-%s-%s',
			$file,
			$jobid,
			$starttime,
			$level
		);
	}

	/**
	 * Get formatted backup directory name.
	 *
	 * @param string $dir directory name
	 * @param string $jobid backup job identifier
	 * @param string $level backup job level
	 * @return string formatted backup directory name
	 */
	private function getFormattedDir(string $dir, string $jobid, string $level): string
	{
		return sprintf(
			'%s-%d-%s',
			$dir,
			$jobid,
			$level
		);
	}

	/**
	 * Get state file parameters.
	 *
	 * @param string $suffix file name suffix
	 * @return array with directory and file prefix
	 */
	private function getFileParams(string $suffix): array
	{
		$dir = Prado::getPathOfNamespace('Bacularis.Common.Working');
		$plugin_name = $this->getPluginName();
		$prefix = sprintf('%s-%s.', $plugin_name, $suffix);
		return [$dir, $prefix];
	}

	/**
	 * Write debug log.
	 *
	 * @param mixed $msg debug message
	 * @param null|int $force_dest force log destination
	 */
	private function debug($msg, ?int $force_dest = null): void
	{
		if ($this->_debug) {
			$debug_dest = (int) $this->_debug;
			if (is_null($force_dest) || $force_dest === $debug_dest) {
				Plugins::debug($msg, $debug_dest);
			}
		}
	}

	/**
	 * Get first free file descriptor.
	 *
	 * @return int free file descriptor
	 */
	private function getFreeFD(): int
	{
		$fd = -1;
		for ($i = 0; $i < 1000; $i++) {
			if (!file_exists("/dev/fd/$i")) {
				$fd = $i;
				break;
			}
		}
		if ($fd == -1) {
			Plugins::log(
				Plugins::LOG_ERROR,
				'Unable to find free file descriptor'
			);
		}
		return $fd;
	}

	/**
	 * Get full database tool path.
	 *
	 * @param array $args plugin options
	 * @param string $bin tool binary file name
	 * @return string full database tool path
	 */
	private function getBinPath(array $args, string $bin): string
	{
		$path = $bin;
		if (key_exists('binary-path', $args) && $args['binary-path']) {
			$path = implode(DIRECTORY_SEPARATOR, [
				rtrim($args['binary-path'], '/'),
				$bin
			]);
		}
		return $path;
	}

	/**
	 * Get plugin backup parameter categories.
	 * It should return all parameter categories that are used in backup.
	 *
	 * @return array plugin parameter categories
	 */
	public static function getBackupParameterCategories(): array
	{
		return [
			self::PARAM_CAT_GENERAL,
			self::PARAM_CAT_DUMP_BACKUP,
			self::PARAM_CAT_BINARY_BACKUP,
			self::PARAM_CAT_FILE_BACKUP,
			self::PARAM_CAT_BINLOG_BACKUP
		];
	}

	/**
	 * Get plugin restore parameter categories.
	 * It should return all parameter categories that are used in restore.
	 *
	 * @return array plugin parameter categories
	 */
	public static function getRestoreParameterCategories(): array
	{
		return [
			self::PARAM_CAT_DUMP_RESTORE,
			self::PARAM_CAT_BINARY_RESTORE,
			self::PARAM_CAT_FILE_RESTORE,
			self::PARAM_CAT_BINLOG_RESTORE
		];
	}
}
