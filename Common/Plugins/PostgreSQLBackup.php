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

namespace Bacularis\Common\Plugins;

use ArrayIterator;
use Bacularis\Common\Modules\IBaculaBackupFileDaemonPlugin;
use Bacularis\Common\Modules\BacularisCommonPluginBase;
use Bacularis\Common\Modules\Plugins;
use FilesystemIterator;
use Prado\Prado;
use SplFileInfo;

/**
 * The PostgreSQL backup plugin module.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Plugin
 */
class PostgreSQLBackup extends BacularisCommonPluginBase implements IBaculaBackupFileDaemonPlugin
{
	/**
	 * SQL dump backup tools.
	 */
	private const SQL_DUMP_PROGRAM = 'pg_dump';
	private const SQL_DUMPALL_PROGRAM = 'pg_dumpall';
	private const SQL_CLI_PROGRAM = 'psql';
	private const SQL_RESTORE_PROGRAM = 'pg_restore';

	/**
	 * SQL dump backup methods.
	 */
	private const SQL_DUMP_METHOD_PLAIN = 'plain';
	private const SQL_DUMP_METHOD_CUSTOM = 'custom';
	private const SQL_DUMP_METHOD_DIRECTORY = 'directory'; // not supported
	private const SQL_DUMP_METHOD_TAR = 'tar';

	/**
	 * Physical binary data backup methods.
	 */
	private const SQL_BINARY_DATA_METHOD_PLAIN = 'plain';
	private const SQL_BINARY_DATA_METHOD_TAR = 'tar';
	private const SQL_BINARY_DATA_METHOD_TAR_STREAM = 'tar-stream';

	/**
	 * Physical binary WAL backup methods.
	 */
	private const SQL_BINARY_WAL_METHOD_NONE = 'none';
	private const SQL_BINARY_WAL_METHOD_FETCH = 'fetch';
	private const SQL_BINARY_WAL_METHOD_STREAM = 'stream';

	/**
	 * Binary physical backup tools.
	 */
	private const BINARY_BACKUP_PROGRAM = 'pg_basebackup';

	/**
	 * Supported backup methods.
	 */
	private const BACKUP_METHOD_DUMP = 'dump';
	private const BACKUP_METHOD_BINARY = 'binary';
	private const BACKUP_METHOD_FILE = 'file';
	private const BACKUP_METHOD_WAL = 'wal';

	/**
	 * Binary backup tools.
	 */
	private const BINARY_BACKUP_PLAIN_FORMAT_COMMAND = 'tar -cf /dev/stdout "%s"';
	private const BINARY_RESTORE_PLAIN_FORMAT_COMMAND = 'tar -C "%s" -xvf -';

	/**
	 * WAL backup tools.
	 */
	private const WAL_BACKUP_COMMAND = 'tar -cf /dev/stdout -T "%s"';
	private const WAL_RESTORE_COMMAND = 'tar -C "%s" -xvf -';

	/**
	 * File backup tools.
	 */
	private const FILE_BACKUP_COMMAND = 'tar -cf /dev/stdout "%s"';
	private const FILE_RESTORE_COMMAND = 'tar -C "%s" -xvf -';

	/**
	 * Binary backup manifest file.
	 */
	private const BINARY_BACKUP_MANIFEST_FILE = 'backup_manifest';

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
	private const PARAM_CAT_WAL_BACKUP = 'WAL archive directory backup';
	private const PARAM_CAT_WAL_RESTORE = 'WAL archive directory restore';

	/**
	 * Common parameters for the SQL dump program.
	 */
	private const SQL_DUMP_PROGRAM_COMMON_OPTS = [
	];

	/**
	 * Databases that are ignored in all databases backup.
	 */
	private const IGNORE_SYSTEM_TABLES = [
		'template0'
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
	private const ACTION_SQL_DATA = 'sql-data';
	private const ACTION_SCHEMA = 'schema';
	private const ACTION_SQL_ALL_DBS = 'sql-all-databases';
	private const ACTION_BINARY_DATA = 'binary-data';
	private const ACTION_FILE = 'file';
	private const ACTION_DIR = 'dir';
	private const ACTION_WAL = 'wal';

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
		return 'PostgreSQL database backup';
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
				'name' => 'cluster-name',
				'type' => 'string',
				'default' => 'main',
				'label' => 'User defined PostgreSQL cluster name (any string)',
				'category' => [self::PARAM_CAT_GENERAL]
			],
			[
				'name' => 'user',
				'type' => 'string',
				'default' => 'postgres',
				'label' => 'Database admin username',
				'category' => [self::PARAM_CAT_GENERAL]
			],
			[
				'name' => 'system-user',
				'type' => 'string',
				'default' => '',
				'label' => 'System username to execute PostgreSQL commands',
				'category' => [self::PARAM_CAT_GENERAL]
			],
			[
				'name' => 'binary-path',
				'type' => 'string',
				'default' => '',
				'label' => 'PostgreSQL binaries path',
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
				'name' => 'dump-format',
				'type' => 'array',
				'default' => self::SQL_DUMP_METHOD_PLAIN,
				'label' => 'Data output format',
				'data' => [self::SQL_DUMP_METHOD_PLAIN, self::SQL_DUMP_METHOD_CUSTOM, self::SQL_DUMP_METHOD_TAR],
				'category' => [self::PARAM_CAT_DUMP_BACKUP, self::PARAM_CAT_DUMP_RESTORE]
			],
			[
				'name' => 'dump-compress',
				'type' => 'array',
				'default' => '0',
				'label' => 'Compression (0 - no compression)',
				'data' => [
					// NO COMPRESSION
					'0',
					// GZIP DEFAULT (PostgreSQL < 16)
					'1', '2', '3', '4', '5', '6', '7', '8',	'9',

					// GZIP (PostgreSQL >= 16)
					'gzip:level=1', 'gzip:level=2', 'gzip:level=3', 'gzip:level=4', 'gzip:level=5', 'gzip:level=6', 'gzip:level=7', 'gzip:level=8', 'gzip:level=9',

					// ZSTD (PostgreSQL >= 16)
					'zstd:level=-7', 'zstd:level=-6', 'zstd:level=-5', 'zstd:level=-4', 'zstd:level=-3', 'zstd:level=-2', 'zstd:level=-1', 'zstd:level=1', 'zstd:level=2', 'zstd:level=3', 'zstd:level=4', 'zstd:level=5', 'zstd:level=6', 'zstd:level=7', 'zstd:level=8', 'zstd:level=9', 'zstd:level=10', 'zstd:level=11', 'zstd:level=12', 'zstd:level=13', 'zstd:level=14', 'zstd:level=15', 'zstd:level=16', 'zstd:level=17', 'zstd:level=18', 'zstd:level=19', 'zstd:level=20', 'zstd:level=21', 'zstd:level=22',

					// ZSTD LONG=1 (PostgreSQL >= 16)
					'zstd:level=-7,long=1', 'zstd:level=-6,long=1', 'zstd:level=-5,long=1', 'zstd:level=-4,long=1', 'zstd:level=-3,long=1', 'zstd:level=-2,long=1', 'zstd:level=-1,long=1', 'zstd:level=1,long=1', 'zstd:level=2,long=1', 'zstd:level=3,long=1', 'zstd:level=4,long=1', 'zstd:level=5,long=1', 'zstd:level=6,long=1', 'zstd:level=7,long=1', 'zstd:level=8,long=1', 'zstd:level=9,long=1', 'zstd:level=10,long=1', 'zstd:level=11,long=1', 'zstd:level=12,long=1', 'zstd:level=13,long=1', 'zstd:level=14,long=1', 'zstd:level=15,long=1', 'zstd:level=16,long=1', 'zstd:level=17,long=1', 'zstd:level=18,long=1', 'zstd:level=19,long=1', 'zstd:level=20,long=1', 'zstd:level=21,long=1', 'zstd:level=22,long=1',

					// LZ4 (PostgreSQL >= 16)
					'lz4:level=1', 'lz4:level=2', 'lz4:level=3', 'lz4:level=4', 'lz4:level=5', 'lz4:level=6', 'lz4:level=7', 'lz4:level=8', 'lz4:level=9', 'lz4:level=10', 'lz4:level=11', 'lz4:level=12'
				],
				'category' => [self::PARAM_CAT_DUMP_BACKUP]
			],
			[
				'name' => 'add-drop',
				'type' => 'boolean',
				'default' => false,
				'label' => 'Add DROP (DATABASE|TABLE|ROLE) before each CREATE (DATABASE|TABLE|ROLE) statement',
				'category' => [self::PARAM_CAT_DUMP_BACKUP]
			],
			[
				'name' => 'dump-option',
				'type' => 'string',
				'default' => '',
				'label' => 'Additional dump program options (comma separated)',
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
				'label' => 'Directory path to store backup data',
				'category' => [self::PARAM_CAT_BINARY_BACKUP]
			],
			[
				'name' => 'binary-format',
				'type' => 'array',
				'default' => self::SQL_BINARY_DATA_METHOD_PLAIN,
				'label' => 'Data output format',
				'data' => [self::SQL_BINARY_DATA_METHOD_PLAIN, self::SQL_BINARY_DATA_METHOD_TAR, self::SQL_BINARY_DATA_METHOD_TAR_STREAM],
				'category' => [self::PARAM_CAT_BINARY_BACKUP]
			],
			[
				'name' => 'binary-compress',
				'type' => 'array',
				'default' => '0',
				'label' => 'Compression (0 - no compression)',
				'data' => [
					// NO COMPRESSION
					'0',
					// GZIP DEFAULT (PostgreSQL < 16)
					'1', '2', '3', '4', '5', '6', '7', '8',	'9',

					// GZIP (PostgreSQL >= 16)
					'gzip:level=1', 'gzip:level=2', 'gzip:level=3', 'gzip:level=4', 'gzip:level=5', 'gzip:level=6', 'gzip:level=7', 'gzip:level=8', 'gzip:level=9',

					// ZSTD (PostgreSQL >= 16)
					'zstd:level=-7', 'zstd:level=-6', 'zstd:level=-5', 'zstd:level=-4', 'zstd:level=-3', 'zstd:level=-2', 'zstd:level=-1', 'zstd:level=1', 'zstd:level=2', 'zstd:level=3', 'zstd:level=4', 'zstd:level=5', 'zstd:level=6', 'zstd:level=7', 'zstd:level=8', 'zstd:level=9', 'zstd:level=10', 'zstd:level=11', 'zstd:level=12', 'zstd:level=13', 'zstd:level=14', 'zstd:level=15', 'zstd:level=16', 'zstd:level=17', 'zstd:level=18', 'zstd:level=19', 'zstd:level=20', 'zstd:level=21', 'zstd:level=22',

					// ZSTD LONG=1 (PostgreSQL >= 16)
					'zstd:level=-7,long=1', 'zstd:level=-6,long=1', 'zstd:level=-5,long=1', 'zstd:level=-4,long=1', 'zstd:level=-3,long=1', 'zstd:level=-2,long=1', 'zstd:level=-1,long=1', 'zstd:level=1,long=1', 'zstd:level=2,long=1', 'zstd:level=3,long=1', 'zstd:level=4,long=1', 'zstd:level=5,long=1', 'zstd:level=6,long=1', 'zstd:level=7,long=1', 'zstd:level=8,long=1', 'zstd:level=9,long=1', 'zstd:level=10,long=1', 'zstd:level=11,long=1', 'zstd:level=12,long=1', 'zstd:level=13,long=1', 'zstd:level=14,long=1', 'zstd:level=15,long=1', 'zstd:level=16,long=1', 'zstd:level=17,long=1', 'zstd:level=18,long=1', 'zstd:level=19,long=1', 'zstd:level=20,long=1', 'zstd:level=21,long=1', 'zstd:level=22,long=1',

					// LZ4 (PostgreSQL >= 16)
					'lz4:level=1', 'lz4:level=2', 'lz4:level=3', 'lz4:level=4', 'lz4:level=5', 'lz4:level=6', 'lz4:level=7', 'lz4:level=8', 'lz4:level=9', 'lz4:level=10', 'lz4:level=11', 'lz4:level=12'
				],
				'category' => [self::PARAM_CAT_BINARY_BACKUP]
			],
			[
				'name' => 'binary-incremental',
				'type' => 'boolean',
				'default' => false,
				'label' => 'Enable incremental backups (from PostgreSQL 17)',
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
				'name' => 'wal-method',
				'type' => 'boolean',
				'default' => false,
				'label' => 'Enable WAL backup method',
				'category' => [self::PARAM_CAT_WAL_BACKUP]
			],
			[
				'name' => 'wal-path',
				'type' => 'string',
				'default' => '',
				'label' => 'WAL archiving directory path to backup',
				'category' => [self::PARAM_CAT_WAL_BACKUP]
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
				$sys_cmds = ['roles', 'tablespaces'];
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
			$db_list_args = $this->filterParametersByCategory($args, [
				self::PARAM_CAT_GENERAL
			]);
			$dbs = $this->getDatabases($db_list_args);
		} elseif (key_exists('databases', $args)) {
			$dbs = explode(',', $args['databases']);
			$dbs = array_map('trim', $dbs);
		}
		for ($i = 0; $i < count($dbs); $i++) {
			$args['databases'] = $dbs[$i];
			$cmds[] = $this->getSinglePluginCommand($args, self::ACTION_SCHEMA, $dbs[$i]);
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
	 * Get full WAL backup commands.
	 *
	 * @param array $args plugin options
	 * @return array backup commands
	 */
	private function getFullWALBackupPluginCommands(array $args): array
	{
		$cmds = [];
		$cmds[] = $this->getSinglePluginCommand($args, self::ACTION_WAL, 'wal-path');
		return $cmds;
	}

	/**
	 * Get incremental WAL backup commands.
	 *
	 * @param array $args plugin options
	 * @return array backup commands
	 */
	private function getIncrementalWALBackupPluginCommands(array $args): array
	{
		$cmds = [];
		$cmds[] = $this->getSinglePluginCommand($args, self::ACTION_WAL, 'wal-path');
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
			case self::ACTION_WAL: {
				$backup_cmd = $this->getBackupWALCommand($args);
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
	 * @param string $system_cmd system command (ex. 'users', 'roles'...etc.)
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
		$schema_args['schema'] = true;
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
		$backup_args['data'] = true;
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
	 * Get WAL backup plugin command.
	 *
	 * @param array $args plugin options
	 * @return string backup command
	 */
	private function getBackupWALCommand(array $args): string
	{
		$action = 'command/backup';
		$backup_args = $this->filterParametersByCategory($args, [
			self::PARAM_CAT_GENERAL,
			self::PARAM_CAT_WAL_BACKUP
		]);
		$backup_args['job-name'] = $args['job-name'];
		$backup_args['job-level'] = $args['job-level'];
		$backup_args['wal-method'] = true;
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
			self::PARAM_CAT_GENERAL,
			self::PARAM_CAT_DUMP_BACKUP
		]);
		$pname = $this->getPluginName();
		$format = $path_args['dump-format'];
		if ($dir == self::SYSTEM_DATA_DIR || $dir == self::ALL_DATABASES) {
			$format = self::SQL_DUMP_METHOD_PLAIN;
		}
		$ext = '';
		switch ($format) {
			case self::SQL_DUMP_METHOD_PLAIN: $ext = 'sql';
				break;
			case self::SQL_DUMP_METHOD_CUSTOM: $ext = 'dump';
				break;
			case self::SQL_DUMP_METHOD_TAR: $ext = 'tar';
				break;
			default: $ext = 'sql';
				break;
		}
		$path = sprintf(
			'/#%s/%s/%s/%s/%s.%s',
			$pname,
			$args['plugin-config'] ?? '',
			$path_args['cluster-name'],
			$dir,
			$file,
			$ext
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
			'/#%s/%s/%s.tar',
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
		if (key_exists('wal-method', $args)) {
			$cmds = array_merge($cmds, $this->getWALPluginCommands($args));
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
				// not supported
				break;
			}
			case 'Differential': {
				// not supported
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
	 * Get WAL backup plugin commands.
	 *
	 * @param array $args plugin options
	 * @return array plugin commands
	 */
	private function getWALPluginCommands(array $args): array
	{
		$cmds = [];
		$level = $args['job-level'] ?? self::DEFAULT_JOB_LEVEL;
		switch ($level) {
			case 'Full': {
				$cmds = $this->getFullWALBackupPluginCommands($args);
				break;
			}
			case 'Incremental': {
				$cmds = $this->getIncrementalWALBackupPluginCommands($args);
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
	 * @param string $method backup method (ex. 'file', 'dump', 'binary' or 'wal')
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
			self::BACKUP_METHOD_WAL
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
		$st_dump = $st_bin = $st_file = $st_wal = true;
		if (key_exists('dump-method', $args)) {
			$st_dump = $this->doDumpBackup($args);
		}
		if (key_exists('binary-method', $args)) {
			$st_bin = $this->doBinaryBackup($args);
		}
		if (key_exists('file-method', $args)) {
			$st_file = $this->doFileBackup($args);
		}
		if (key_exists('wal-method', $args)) {
			$st_wal = $this->doWALBackup($args);
		}
		$this->debug(['dump' => $st_dump, 'bin' => $st_bin, 'file' => $st_file, 'wal' => $st_wal]);
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
				// not supported
				$result = $this->doIncrementalDumpBackup($args);
				break;
			}
			case 'Differential': {
				// not supported
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
		$is_schema = false;
		if (key_exists('databases', $args)) {
			if (key_exists('schema', $args)) {
				$is_schema = true;
				Plugins::log(Plugins::LOG_INFO, "Doing '{$args['databases']}' database schema backup.");
			} elseif (key_exists('data', $args)) {
				Plugins::log(Plugins::LOG_INFO, "Doing '{$args['databases']}' database data backup.");
			}
		} elseif (key_exists('system', $args)) {
			$is_schema = true;
			Plugins::log(Plugins::LOG_INFO, "Doing '{$args['system']}' system data backup.");
		}

		// Program used to perform dump
		$prog = '';
		if (key_exists('all-databases', $args) || key_exists('system', $args)) {
			$is_schema = true;
			$prog = self::SQL_DUMPALL_PROGRAM;
		} else {
			$prog = self::SQL_DUMP_PROGRAM;
		}

		// Prepare general parameters
		$cmd_params = $this->getGeneralParams($args);

		// Prepare backup specific arguments
		$db_param = '';
		if (key_exists('system', $args)) {
			if ($args['system'] == 'roles') {
				$cmd_params['roles-only'] = true;
			} elseif ($args['system'] == 'tablespaces') {
				$cmd_params['tablespaces-only'] = true;
			} elseif ($args['system'] == 'globals') {
				$cmd_params['globals-only'] = true;
			}
		}
		if ($prog == self::SQL_DUMP_PROGRAM) {
			if (key_exists('dump-format', $args)) {
				$cmd_params['format'] = $args['dump-format'];
			}
			if (key_exists('dump-compress', $args)) {
				$cmd_params['compress'] = $args['dump-compress'];
			}
		}
		if (key_exists('data', $args)) {
			$cmd_params['data-only'] = true;
		}
		if (key_exists('schema', $args)) {
			$cmd_params['schema-only'] = true;
		}
		if (key_exists('add-drop', $args) && $is_schema) {
			$cmd_params['clean'] = true;
			$cmd_params['if-exists'] = true;
		}
		if (key_exists('dump-option', $args)) {
			$dump_option = $args['dump-option'];
			if (!is_array($dump_option)) {
				$dump_option = explode(',', $dump_option);
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
		$bin = $this->getBinPath($args, $prog);
		array_unshift($cmd, $bin);
		$result = $this->execCommand($cmd, true);
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
		Plugins::log(
			Plugins::LOG_WARNING,
			'Incremental backup level is not supported in the dump method backups.'
		);
		return true;
	}

	/**
	 * Run differential dump backup.
	 *
	 * @param array $args plugin options
	 * @return bool backup status - true on success, false otherwise
	 */
	private function doDifferentialDumpBackup(array $args): bool
	{
		Plugins::log(
			Plugins::LOG_WARNING,
			'Differential backup level is not supported in the dump method backups.'
		);
		return true;
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

		// Prepare rest parameters
		$cmd_params['format'] = $args['binary-format'];
		if (key_exists('binary-compress', $args)) {
			$cmd_params['compress'] = $args['binary-compress'];
		}

		$target_dir = $wal_method = '';
		$pr = false;
		if ($args['binary-format'] == self::SQL_BINARY_DATA_METHOD_PLAIN || $args['binary-format'] == self::SQL_BINARY_DATA_METHOD_TAR) {
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
			$wal_method = self::SQL_BINARY_WAL_METHOD_STREAM;
		} elseif ($args['binary-format'] == self::SQL_BINARY_DATA_METHOD_TAR_STREAM) {
			$pr = true;
			$target_dir = '-'; // write to stdout
			$wal_method = self::SQL_BINARY_WAL_METHOD_FETCH;
			// Revert to tar method before executing
			$cmd_params['format'] = self::SQL_BINARY_DATA_METHOD_TAR;
		}
		$cmd_params['pgdata'] = $target_dir;
		$cmd_params['wal-method'] = $wal_method;

		// Set parameters
		$cmd = $this->prepareCommandParameters($cmd_params);
		$bin = $this->getBinPath($args, self::BINARY_BACKUP_PROGRAM);
		array_unshift($cmd, $bin);

		// Run full backup
		$result = $this->execCommand($cmd, $pr);
		$out = implode(PHP_EOL, $result['output']);
		Plugins::log(Plugins::LOG_INFO, "Raw backup output: '{$out}'.");
		$success = ($result['exitcode'] === 0);

		if ($success) {
			if ($cmd_params['format'] == self::SQL_BINARY_DATA_METHOD_PLAIN || $cmd_params['format'] == self::SQL_BINARY_DATA_METHOD_TAR) {
				// Do binary data streaming to FD
				$state = $this->streamBinaryBackup($args, $target_dir);
				if ($state) {
					// Everything fine. Set backup info
					$info = [
						'prev_file_inc' => implode(DIRECTORY_SEPARATOR, [$target_dir, self::BINARY_BACKUP_MANIFEST_FILE])
					];
					$this->setBinaryBackupInfo($info, $args['job-name']);
				} else {
					Plugins::log(Plugins::LOG_ERROR, "Error while streaming full backup binaries from target-dir '{$target_dir}'.");
				}
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
		if (!key_exists('binary-incremental', $args) || !$args['binary-incremental']) {
			$emsg = 'Incremental backup function is disabled.';
			$emsg .= ' If you use PostgreSQL version >= 17, you can enable incremental backup support in the plugin settings.';
			Plugins::log(Plugins::LOG_WARNING, $emsg);
			return false;
		}

		$imsg = 'Start incremental binary backup.';
		Plugins::log(Plugins::LOG_INFO, $imsg);

		// Prepare general parameters
		$cmd_params = $this->getGeneralParams($args);

		// Prepare rest parameters
		$cmd_params['format'] = $args['binary-format'];
		if (key_exists('binary-compress', $args)) {
			$cmd_params['compress'] = $args['binary-compress'];
		}

		if ($cmd_params['format'] == self::SQL_BINARY_DATA_METHOD_TAR_STREAM) {
			$emsg = 'Incremental backup function is not supported with tar-stream output format.';
			$emsg .= ' If you want to use incremental backups, please use different data output format (plain or tar).';
			Plugins::log(Plugins::LOG_WARNING, $emsg);
			return false;
		}

		// Get previous backup manifest file
		$info = $this->getBinaryBackupInfo($args['job-name']);

		if (key_exists('prev_file_inc', $info)) {
			$cmd_params['incremental'] = $info['prev_file_inc'];
			$cmd_params['verbose'] = true;
		}

		$target_dir = $wal_method = '';
		if ($args['binary-format'] == self::SQL_BINARY_DATA_METHOD_PLAIN || $args['binary-format'] == self::SQL_BINARY_DATA_METHOD_TAR) {
			// Prepare directory for a new backup (target-dir)
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
			$wal_method = self::SQL_BINARY_WAL_METHOD_STREAM;
		} elseif ($args['binary-format'] == self::SQL_BINARY_DATA_METHOD_TAR_STREAM) {
			$target_dir = '-'; // write to stdout
			$wal_method = self::SQL_BINARY_WAL_METHOD_FETCH;
			// Revert to tar method before executing
			$cmd_params['format'] = self::SQL_BINARY_DATA_METHOD_TAR;
		}
		$cmd_params['pgdata'] = $target_dir;
		$cmd_params['wal-method'] = $wal_method;

		// Set parameters
		$cmd = $this->prepareCommandParameters($cmd_params);
		$bin = $this->getBinPath($args, self::BINARY_BACKUP_PROGRAM);
		array_unshift($cmd, $bin);

		// Run incremental backup
		$result = $this->execCommand($cmd);
		$out = implode(PHP_EOL, $result['output']);
		Plugins::log(Plugins::LOG_INFO, "Raw backup output: '{$out}'.");
		$success = ($result['exitcode'] === 0);
		if ($success) {
			if ($cmd_params['format'] == self::SQL_BINARY_DATA_METHOD_PLAIN || $cmd_params['format'] == self::SQL_BINARY_DATA_METHOD_TAR) {
				// Do binary data streaming to FD
				$state = $this->streamBinaryBackup($args, $target_dir);
				if ($state) {
					// Everything fine. Set backup info
					$info = [
						'prev_file_inc' => implode(DIRECTORY_SEPARATOR, [$target_dir, self::BINARY_BACKUP_MANIFEST_FILE])
					];
					$this->setBinaryBackupInfo($info, $args['job-name']);
				} else {
					Plugins::log(Plugins::LOG_ERROR, "Error while streaming incremental backup binaries from target-dir '{$target_dir}'.");
				}
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
	 * Switch WAL segment.
	 * This is to ensure that a just-finished transaction is archived as soon as possible
	 *
	 * @param array $args plugin options
	 * @param bool $wait_on_switch wait until latest WAL file appear in 'wal-path'
	 * @return bool true on success, otherwise false
	 */
	private function switchWAL(array $args, bool $wait_on_switch = false): bool
	{
		// Prepare general parameters
		$cmd_params = $this->getGeneralParams($args);
		$cmd_params['tuples-only'] = true;
		$cmd_params['command'] = 'SELECT pg_walfile_name(pg_switch_wal());';

		// Prepare getting database arguments
		$cmd = $this->prepareCommandParameters($cmd_params);
		$bin = $this->getBinPath($args, self::SQL_CLI_PROGRAM);
		array_unshift($cmd, $bin);

		// Run command
		$ret = [];
		$result = $this->execCommand($cmd);
		$ret = ($result['exitcode'] == 0);
		if ($ret) {
			if ($wait_on_switch) {
				$walname = trim($result['output'][0]);
				$walpath = implode(DIRECTORY_SEPARATOR, [$args['wal-path'], $walname]);
				$found = $this->waitOnFile($walpath);
				if (!$found) {
					$emsg = "Latest switched WAL file was not archived before backup. It may not be included in this backup.";
					Plugins::log(Plugins::LOG_ERROR, $emsg);
				}
			}
		} else {
			$output = implode(PHP_EOL, $result['output']);
			$emsg = "Error while switching WAL. ExitCode: {$result['exitcode']}, Output: {$output}.";
			Plugins::log(Plugins::LOG_ERROR, $emsg);
		}
		return $ret;
	}

	/**
	 * Wait on a file.
	 *
	 * @param string $filepath file name to wait on
	 * @return bool true if file appeared successfully, otherwise false
	 */
	private function waitOnFile(string $filepath): bool
	{
		$wait_timeout = 10; // 10 seconds
		$found = false;
		for ($i = 0; $i < $wait_timeout; $i++) {
			if (file_exists($filepath)) {
				$found = true;
				break;
			}
			sleep(1);
		}
		sleep(1); // one more second
		if (!$found) {
			$this->debug('File did not appear. Timeout occured.', Plugins::LOG_DEST_FILE);
		}
		return $found;
	}

	/**
	 * Run TAR streaming binary backup.
	 *
	 * @param array $args plugin options
	 * @param string $backup_dir backup directory to stream
	 * @return bool true on success, otherwise false
	 */
	private function streamBinaryBackup(array $args, string $backup_dir): bool
	{
		$cmd = [];
		$cmd[] = sprintf(
			self::BINARY_BACKUP_PLAIN_FORMAT_COMMAND,
			$backup_dir
		);
		$result = $this->execCommand($cmd, true);
		$success = ($result['exitcode'] === 0);
		if (!$success) {
			$emsg = "Error while streaming tar backup data. Path: '{$backup_dir}' ExitCode: '{$result['exitcode']}'.";
			Plugins::log(Plugins::LOG_ERROR, $emsg);
		}
		return $success;
	}

	/**
	 * Run WAL backup.
	 *
	 * @param array $args plugin options
	 * @return bool backup status - true on success, false otherwise
	 */
	private function doWALBackup(array $args): bool
	{
		$result = false;
		$level = $args['job-level'] ?? self::DEFAULT_JOB_LEVEL;
		switch ($level) {
			case 'Full': {
				$result = $this->doFullWALBackup($args);
				break;
			}
			case 'Incremental': {
				$result = $this->doIncrementalWALBackup($args);
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
	 * Run full WAL backup.
	 *
	 * @param array $args plugin options
	 * @return bool backup status - true on success, false otherwise
	 */
	private function doFullWALBackup(array $args): bool
	{
		if (!key_exists('wal-path', $args)) {
			$emsg = 'There is not provided WAL path to backup.';
			Plugins::log(Plugins::LOG_WARNING, $emsg);
			return false;
		}

		$imsg = "Start full WAL backup '{$args['wal-path']}'.";
		Plugins::log(Plugins::LOG_INFO, $imsg);

		// Do switch WAL before doing backup
		$this->switchWAL($args, true);

		// Prepare file list to backup
		$wal_list = $this->getWALs($args);
		$last_wal_file = end($wal_list);

		// Save WAL file list to backup
		[$tmpdir, $tmpprefix] = $this->getFileParams(self::BACKUP_METHOD_WAL);
		$tmpfname = tempnam($tmpdir, $tmpprefix);
		$file_list = implode(PHP_EOL, $wal_list);
		if (file_put_contents($tmpfname, $file_list) === false) {
			Plugins::log(Plugins::LOG_ERROR, "Unable to write to '{$tmpfname}' file.");
			return false;
		}

		// Run full WAL backup
		$cmd = [];
		$cmd[] = sprintf(
			self::WAL_BACKUP_COMMAND,
			$tmpfname
		);
		$result = $this->execCommand($cmd, true);
		$success = ($result['exitcode'] === 0);
		if ($success) {
			$this->setWALBackupInfo(['wal_file' => $last_wal_file], $args['job-name']);
		} else {
			$emsg = "Error while doing full WAL backup. Path: '{$args['wal-path']}' ExitCode: '{$result['exitcode']}'.";
			Plugins::log(Plugins::LOG_ERROR, $emsg);
		}

		// Clean up
		unlink($tmpfname);

		return $success;
	}

	/**
	 * Run incremental WAL backup.
	 *
	 * @param array $args plugin options
	 * @return bool backup status - true on success, false otherwise
	 */
	private function doIncrementalWALBackup(array $args): bool
	{
		if (!key_exists('wal-path', $args)) {
			$emsg = 'There is not provided WAL path to backup.';
			Plugins::log(Plugins::LOG_WARNING, $emsg);
			return false;
		}

		$imsg = "Start incremental WAL backup '{$args['wal-path']}'.";
		Plugins::log(Plugins::LOG_INFO, $imsg);

		// Do flush logs before doing backup
		$this->switchWAL($args, true);

		// Prepare file list to backup
		$info = $this->getWALBackupInfo($args['job-name']);
		$wal_list = $this->getWALs($args, $info['wal_file']);
		$last_wal_file = end($wal_list);

		// Save WAL file list to backup
		[$tmpdir, $tmpprefix] = $this->getFileParams(self::BACKUP_METHOD_WAL);
		$tmpfname = tempnam($tmpdir, $tmpprefix);
		$file_list = implode(PHP_EOL, $wal_list);
		if (file_put_contents($tmpfname, $file_list) === false) {
			Plugins::log(Plugins::LOG_ERROR, "Unable to write to '{$tmpfname}' file.");
			return false;
		}

		// Run incremental WAL backup
		$cmd = [];
		$cmd[] = sprintf(
			self::WAL_BACKUP_COMMAND,
			$tmpfname
		);
		$result = $this->execCommand($cmd, true);
		$success = ($result['exitcode'] === 0);
		if ($success) {
			$this->setWALBackupInfo(['wal_file' => $last_wal_file], $args['job-name']);
		} else {
			$emsg = "Error while doing incremental wal backup. Path: '{$args['wal-path']}' ExitCode: '{$result['exitcode']}'.";
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
		} elseif (in_array($args['restore-action'], [self::ACTION_FILE, self::ACTION_DIR, self::ACTION_WAL])) {
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
				$cmd_params['dbname'] = $args['database'];
				Plugins::log(Plugins::LOG_INFO, "Doing restore to new '{$args['database']}' database.");
			} else {
				// restore to original database from backup
				$cmd_params['dbname'] = $args['database'] = $args['restore-item'];
				Plugins::log(Plugins::LOG_INFO, "Doing restore'{$args['database']}' database.");
			}
		}

		if (in_array($args['restore-action'], [self::ACTION_SYSTEM, self::ACTION_SQL_ALL_DBS])) {
			// Force plain format for selected actions that must be plain
			$args['dump-format'] = self::SQL_DUMP_METHOD_PLAIN;
		}

		$prog = '';
		if ($args['dump-format'] == self::SQL_DUMP_METHOD_PLAIN) {
			$prog = self::SQL_CLI_PROGRAM;
		} elseif (in_array($args['dump-format'], [self::SQL_DUMP_METHOD_CUSTOM, self::SQL_DUMP_METHOD_TAR])) {
			$prog = self::SQL_RESTORE_PROGRAM;
		}

		if ($args['restore-action'] == self::ACTION_SCHEMA && !$this->createDatabase($args)) { // create database if needed
			return false;
		}

		// Set parameters
		$cmd = $this->prepareCommandParameters($cmd_params);
		$bin = $this->getBinPath($args, $prog);
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
		$cmd_params['tuples-only'] = true;
		$cmd_params['command'] = 'SELECT datname FROM pg_database';
		$cmd = $this->prepareCommandParameters($cmd_params);
		$bin = $this->getBinPath($args, self::SQL_CLI_PROGRAM);
		array_unshift($cmd, $bin);

		$dbs = [];
		// Run command
		$result = $this->execCommand($cmd);
		if ($result['exitcode'] == 0) {
			$dbs = $result['output'];
			$dbs = array_map('trim', $dbs);
			$dbs = array_filter($dbs, fn ($db) => ($db != '' && !in_array($db, self::IGNORE_SYSTEM_TABLES)));
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

		// Prepare create db command
		$cmd_params['command'] = "CREATE DATABASE \\\"{$args['database']}\\\"";

		// Prepare creating database parameters
		$cmd = $this->prepareCommandParameters($cmd_params);
		$bin = $this->getBinPath($args, self::SQL_CLI_PROGRAM);
		array_unshift($cmd, $bin);

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
	 * Get WAL file list.
	 *
	 * @param array $args plugin options
	 * @param string $start_with starting log file (used for incremental backup)
	 * @return array WAL list
	 */
	private function getWALs(array $args, string $start_with = ''): array
	{
		$files_iterator = new \FilesystemIterator($args['wal-path']);
		$files_array = iterator_to_array($files_iterator);
		$files = new ArrayIterator($files_array);
		$files->uasort(
			fn (SplFileInfo $a, SplFileInfo $b) => strnatcmp($a->getMTime(), $b->getMTime())
		);
		$wal_files = array_keys(iterator_to_array($files));
		$wals_len = count($wal_files);
		if ($start_with && $wals_len > 0) {
			$index = array_search($start_with, $wal_files) + 1;
			if ($index < $wals_len) {
				$wal_files = array_splice($wal_files, $index, $wals_len - $index);
			}
		}
		return $wal_files;
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
		$cmd = ['tee', '"' . $restore_path . '"', '>/dev/null'];

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
	 * Set binary backup information.
	 *
	 * @param array $info backup job information
	 * @param string $job_name backup job name
	 * @return array true on success, false otherwise
	 */
	private function setBinaryBackupInfo(array $info, string $job_name): bool
	{
		$body = $this->getJobBackupInfo($job_name, self::BACKUP_METHOD_BINARY);
		$body['prev_file_inc'] = $info['prev_file_inc'];
		$value = json_encode($body);
		$statepath = $this->getJobInfoPath($job_name, self::BACKUP_METHOD_BINARY);
		$result = (file_put_contents($statepath, $value, LOCK_EX) !== false);
		return $result;
	}

	/**
	 * Get binary backup information.
	 *
	 * @param string $job_name backup job name
	 * @return array backup information
	 */
	private function getBinaryBackupInfo(string $job_name): array
	{
		$info = $this->getJobBackupInfo($job_name, self::BACKUP_METHOD_BINARY);
		$result = ['prev_file_inc' => ''];
		if (key_exists('prev_file_inc', $info)) {
			$result = $info;
		}
		return $result;
	}

	/**
	 * Set WAL backup information.
	 *
	 * @param array $info backup information
	 * @param string $job_name backup job name
	 * @return array true on success, false otherwise
	 */
	private function setWALBackupInfo(array $info, string $job_name): bool
	{
		$body = $this->getJobBackupInfo($job_name, self::BACKUP_METHOD_WAL);
		$body['Incremental'] = $info;
		$value = json_encode($body);
		$statepath = $this->getJobInfoPath($job_name, self::BACKUP_METHOD_WAL);
		return (file_put_contents($statepath, $value, LOCK_EX) !== false);
	}

	/**
	 * Get WAL backup information.
	 *
	 * @param string $job_name backup job name
	 * @return array backup information
	 */
	private function getWALBackupInfo(string $job_name): array
	{
		$info = $this->getJobBackupInfo($job_name, self::BACKUP_METHOD_WAL);
		$result = ['wal_file' => ''];
		if (key_exists('Incremental', $info)) {
			$result = $info['Incremental'];
		}
		return $result;
	}

	/**
	 * Get backup job information.
	 *
	 * @param string $job_name backup job name
	 * @param string $method backup method ('dump', 'binary', 'wal')
	 * @return array backup information or empty array if no backup job info
	 */
	private function getJobBackupInfo(string $job_name, string $method): array
	{
		$result = [];
		$statepath = $this->getJobInfoPath($job_name, $method);
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
	 * @param string $method backup method ('dump', 'binary', 'wal')
	 * @return string backup coordinates file path
	 */
	private function getJobInfoPath(string $job_name, string $method): string
	{
		$path = $this->getFileParams($method);
		$stfile = implode(DIRECTORY_SEPARATOR, $path) . $job_name;
		return $stfile;
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
		if (key_exists('user', $args) && !key_exists('system-user', $args)) {
			$params['username'] = $args['user'];
		}
		return $params;
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
		$this->addSystemUser($args, $path);
		return $path;
	}

	/**
	 * Add executing command as given system user.
	 *
	 * @param string $args command parameters
	 * @param string $cmd command
	 */
	private function addSystemUser(array $args, string &$cmd): void
	{
		if (key_exists('system-user', $args) && !empty($args['system-user']) && $args['system-user'] != 'root') {
			$cmd = "sudo -u '{$args['system-user']}' $cmd";
		}
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
			self::PARAM_CAT_WAL_RESTORE
		];
	}
}
