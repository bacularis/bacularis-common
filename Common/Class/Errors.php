<?php
/*
 * Bacula(R) - The Network Backup Solution
 * Baculum   - Bacula web interface
 *
 * Copyright (C) 2013-2021 Kern Sibbald
 *
 * The main author of Baculum is Marcin Haba.
 * The original author of Bacula is Kern Sibbald, with contributions
 * from many others, a complete list can be found in the file AUTHORS.
 *
 * You may use this file and others of this release according to the
 * license defined in the LICENSE file, which includes the Affero General
 * Public License, v3.0 ("AGPLv3") and some additional permissions and
 * terms pursuant to its AGPLv3 Section 7.
 *
 * This notice must be preserved when any source code is
 * conveyed and/or propagated.
 *
 * Bacula(R) is a registered trademark of Kern Sibbald.
 */
 
/**
 * Error classes.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Errors
 * @package Baculum Common
 */
class GenericError {
	const ERROR_NO_ERRORS = 0;
	const ERROR_INVALID_COMMAND = 1;
	const ERROR_INTERNAL_ERROR = 1000;
	const ERROR_INVALID_PATH = 8;
	const ERROR_WRONG_EXITCODE = 9;

	const MSG_ERROR_NO_ERRORS = '';
	const MSG_ERROR_INVALID_COMMAND = 'Invalid command.';
	const MSG_ERROR_INTERNAL_ERROR = 'Internal error.';
	const MSG_ERROR_INVALID_PATH = 'Invalid path.';
	const MSG_ERROR_WRONG_EXITCODE = 'Wrong exitcode.';
}

class DatabaseError extends GenericError {
	const ERROR_DB_CONNECTION_PROBLEM = 2;
	const ERROR_DATABASE_ACCESS_NOT_SUPPORTED = 3;
	
	const MSG_ERROR_DB_CONNECTION_PROBLEM = 'Problem with connection to database.';
	const MSG_ERROR_DATABASE_ACCESS_NOT_SUPPORTED = 'Database access is not supported by this API instance.';
}

class BconsoleError extends GenericError {

	const ERROR_BCONSOLE_CONNECTION_PROBLEM = 4;
	const ERROR_INVALID_DIRECTOR = 5;
	const ERROR_BCONSOLE_DISABLED = 11;

	const MSG_ERROR_BCONSOLE_CONNECTION_PROBLEM = 'Problem with connection to bconsole.';
	const MSG_ERROR_INVALID_DIRECTOR = 'Invalid director.';
	const MSG_ERROR_BCONSOLE_DISABLED = 'Bconsole support is disabled.';
}

class AuthenticationError extends GenericError {

	const ERROR_AUTHENTICATION_TO_API_PROBLEM = 6;

	const MSG_ERROR_AUTHENTICATION_TO_API_PROBLEM = 'Problem with authentication to Baculum API.';
}

class AuthorizationError extends GenericError {

	const ERROR_ACCESS_ATTEMPT_TO_NOT_ALLOWED_RESOURCE = 7;

	const MSG_ERROR_ACCESS_ATTEMPT_TO_NOT_ALLOWED_RESOURCE = 'Access attempt to not allowed resource. Permission denied.';
}

class ClientError extends GenericError {
	const ERROR_CLIENT_DOES_NOT_EXISTS = 10;

	const MSG_ERROR_CLIENT_DOES_NOT_EXISTS = 'Client does not exist.';
}

class StorageError extends GenericError {
	const ERROR_STORAGE_DOES_NOT_EXISTS = 20;

	const MSG_ERROR_STORAGE_DOES_NOT_EXISTS = 'Storage does not exist.';
}

class VolumeError extends GenericError {
	const ERROR_VOLUME_DOES_NOT_EXISTS = 30;
	const ERROR_INVALID_VOLUME = 31;
	const ERROR_INVALID_SLOT = 32;
	const ERROR_VOLUME_ALREADY_EXISTS = 33;

	const MSG_ERROR_VOLUME_DOES_NOT_EXISTS = 'Volume does not exist.';
	const MSG_ERROR_INVALID_VOLUME = 'Invalid volume.';
	const MSG_ERROR_INVALID_SLOT = 'Invalid slot.';
	const MSG_ERROR_VOLUME_ALREADY_EXISTS = 'Volume already exists.';
}

class PoolError extends GenericError {
	const ERROR_POOL_DOES_NOT_EXISTS = 40;
	const ERROR_NO_VOLUMES_IN_POOL_TO_UPDATE = 41;

	const MSG_ERROR_POOL_DOES_NOT_EXISTS = 'Pool does not exist.';
	const MSG_ERROR_NO_VOLUMES_IN_POOL_TO_UPDATE= 'Pool with inputted poolid does not contain any volume to update.';
}

class JobError extends GenericError {
	const ERROR_JOB_DOES_NOT_EXISTS = 50;
	const ERROR_INVALID_JOBLEVEL = 51;
	const ERROR_FILESET_DOES_NOT_EXISTS = 52;
	const ERROR_CLIENT_DOES_NOT_EXISTS = 53;
	const ERROR_STORAGE_DOES_NOT_EXISTS = 54;
	const ERROR_POOL_DOES_NOT_EXISTS = 55;
	const ERROR_INVALID_RPATH = 56;
	const ERROR_INVALID_WHERE_OPTION = 57;
	const ERROR_INVALID_REPLACE_OPTION = 58;
	const ERROR_INVALID_FILENAME = 59;

	const MSG_ERROR_JOB_DOES_NOT_EXISTS = 'Job does not exist.';
	const MSG_ERROR_INVALID_JOBLEVEL = 'Inputted job level is invalid.';
	const MSG_ERROR_FILESET_DOES_NOT_EXISTS = 'FileSet resource does not exist.';
	const MSG_ERROR_CLIENT_DOES_NOT_EXISTS = 'Client does not exist.';
	const MSG_ERROR_STORAGE_DOES_NOT_EXISTS = 'Storage does not exist.';
	const MSG_ERROR_POOL_DOES_NOT_EXISTS = 'Pool does not exist.';
	const MSG_ERROR_INVALID_RPATH = 'Inputted rpath for restore is invalid. Proper format is b2[0-9]+.';
	const MSG_ERROR_INVALID_WHERE_OPTION = 'Inputted "where" option is invalid.';
	const MSG_ERROR_INVALID_REPLACE_OPTION = 'Inputted "replace" option is invalid.';
	const MSG_ERROR_INVALID_FILENAME = 'Inputted "filename" option is invalid.';
}

class FileSetError extends GenericError {
	const ERROR_FILESET_DOES_NOT_EXISTS = 60;

	const MSG_ERROR_FILESET_DOES_NOT_EXISTS = 'FileSet does not exist.';
}

class BVFSError extends GenericError {
	const ERROR_INVALID_RPATH = 71;
	const ERROR_INVALID_RESTORE_PATH = 72;
	const ERROR_INVALID_JOBID_LIST = 73;
	const ERROR_INVALID_FILEID_LIST = 74;
	const ERROR_INVALID_FILEINDEX_LIST = 75;
	const ERROR_INVALID_DIRID_LIST = 76;
	const ERROR_INVALID_CLIENT = 77;
	const ERROR_INVALID_JOBID = 78;

	const MSG_ERROR_INVALID_RPATH = 'Inputted path for restore is invalid. Proper format is b2[0-9]+.';
	const MSG_ERROR_INVALID_RESTORE_PATH = 'Inputted BVFS path param is invalid.';
	const MSG_ERROR_INVALID_JOBID_LIST = 'Invalid jobid list.';
	const MSG_ERROR_INVALID_FILEID_LIST = 'Invalid fileid list.';
	const MSG_ERROR_INVALID_FILEINDEX_LIST = 'Invalid file index list.';
	const MSG_ERROR_INVALID_DIRID_LIST = 'Invalid dirid list.';
	const MSG_ERROR_INVALID_CLIENT = 'Invalid client name.';
	const MSG_ERROR_INVALID_JOBID = 'Invalid jobid.';
}

class JSONToolsError extends GenericError {

	const ERROR_JSON_TOOLS_DISABLED = 80;
	const ERROR_JSON_TOOLS_CONNECTION_PROBLEM = 81;
	const ERROR_JSON_TOOLS_WRONG_EXITCODE = 82;
	const ERROR_JSON_TOOLS_UNABLE_TO_PARSE_OUTPUT = 83;
	const ERROR_JSON_TOOL_NOT_CONFIGURED = 84;

	const MSG_ERROR_JSON_TOOLS_DISABLED = 'JSON tools support is disabled.';
	const MSG_ERROR_JSON_TOOLS_CONNECTION_PROBLEM = 'Problem with connection to a JSON tool.';
	const MSG_ERROR_JSON_TOOLS_WRONG_EXITCODE = 'JSON tool returned wrong exitcode.';
	const MSG_ERROR_JSON_TOOLS_UNABLE_TO_PARSE_OUTPUT = 'JSON tool output was unable to parse.';
	const MSG_ERROR_JSON_TOOL_NOT_CONFIGURED = 'JSON tool not configured.';
}

class BaculaConfigError extends GenericError {

	const ERROR_CONFIG_DIR_NOT_WRITABLE = 90;
	const ERROR_UNEXPECTED_BACULA_CONFIG_VALUE = 91;
	const ERROR_CONFIG_NO_JSONTOOL_READY = 92;
	const ERROR_WRITE_TO_CONFIG_ERROR = 93;
	const ERROR_CONFIG_VALIDATION_ERROR = 94;

	const MSG_ERROR_CONFIG_DIR_NOT_WRITABLE = 'Config directory is not writable.';
	const MSG_ERROR_UNEXPECTED_BACULA_CONFIG_VALUE = 'Unexpected Bacula config value.';
	const MSG_ERROR_CONFIG_NO_JSONTOOL_READY = 'No JSON tool ready.';
	const MSG_ERROR_WRITE_TO_CONFIG_ERROR = 'Write to config file error.';
	const MSG_ERROR_CONFIG_VALIDATION_ERROR = 'Config validation error.';
}

class ConnectionError extends GenericError {

	const ERROR_CONNECTION_TO_HOST_PROBLEM = 100;

	const MSG_ERROR_CONNECTION_TO_HOST_PROBLEM = 'Problem with connection to remote host.';
}

class ActionsError extends GenericError {

	const ERROR_ACTIONS_ACTION_DOES_NOT_EXIST = 110;
	const ERROR_ACTIONS_DISABLED = 111;
	const ERROR_ACTIONS_WRONG_EXITCODE = 112;
	const ERROR_ACTIONS_NOT_CONFIGURED = 113;

	const MSG_ERROR_ACTIONS_ACTION_DOES_NOT_EXIST = 'Action does not exist.';
	const MSG_ERROR_ACTIONS_DISABLED = 'Actions support is disabled.';
	const MSG_ERROR_ACTIONS_WRONG_EXITCODE = 'Action command returned wrong exitcode.';
	const MSG_ERROR_ACTIONS_NOT_CONFIGURED = 'Action is not configured.';
}

class OAuth2Error extends GenericError {

	const ERROR_OAUTH2_CLIENT_DOES_NOT_EXIST = 120;
	const ERROR_OAUTH2_CLIENT_ALREADY_EXISTS = 121;
	const ERROR_OAUTH2_CLIENT_INVALID_CLIENT_ID = 122;
	const ERROR_OAUTH2_CLIENT_INVALID_CLIENT_SECRET = 123;
	const ERROR_OAUTH2_CLIENT_INVALID_REDIRECT_URI = 124;
	const ERROR_OAUTH2_CLIENT_INVALID_SCOPE = 125;
	const ERROR_OAUTH2_CLIENT_INVALID_BCONSOLE_CFG_PATH = 126;
	const ERROR_OAUTH2_CLIENT_INVALID_NAME = 127;
	const ERROR_OAUTH2_CLIENT_INVALID_CONSOLE = 128;
	const ERROR_OAUTH2_CLIENT_INVALID_DIRECTOR = 129;

	const MSG_ERROR_OAUTH2_CLIENT_DOES_NOT_EXIST = 'OAuth2 client does not exist.';
	const MSG_ERROR_OAUTH2_CLIENT_ALREADY_EXISTS = 'OAuth2 client already exists.';
	const MSG_ERROR_OAUTH2_CLIENT_INVALID_CLIENT_ID = 'Invalid OAuth2 client ID.';
	const MSG_ERROR_OAUTH2_CLIENT_INVALID_CLIENT_SECRET = 'Invalid OAuth2 client secret.';
	const MSG_ERROR_OAUTH2_CLIENT_INVALID_REDIRECT_URI = 'Invalid OAuth2 redirect URI.';
	const MSG_ERROR_OAUTH2_CLIENT_INVALID_SCOPE = 'Invalid OAuth2 scope.';
	const MSG_ERROR_OAUTH2_CLIENT_INVALID_BCONSOLE_CFG_PATH = 'Invalid Bconsole config path.';
	const MSG_ERROR_OAUTH2_CLIENT_INVALID_NAME = 'Invalid OAuth2 client name.';
	const MSG_ERROR_OAUTH2_CLIENT_INVALID_CONSOLE = 'Invalid Console name.';
	const MSG_ERROR_OAUTH2_CLIENT_INVALID_DIRECTOR = 'Invalid Director name.';
}
class DeviceError extends GenericError {

	const ERROR_DEVICE_DEVICE_CONFIG_DOES_NOT_EXIST = 130;
	const ERROR_DEVICE_INVALID_COMMAND = 131;
	const ERROR_DEVICE_AUTOCHANGER_DOES_NOT_EXIST = 132;
	const ERROR_DEVICE_AUTOCHANGER_DRIVE_DOES_NOT_EXIST = 133;
	const ERROR_DEVICE_WRONG_SLOT_NUMBER = 134;
	const ERROR_DEVICE_DRIVE_DOES_NOT_BELONG_TO_AUTOCHANGER = 135;

	const MSG_ERROR_DEVICE_DEVICE_CONFIG_DOES_NOT_EXIST = 'Device config does not exist.';
	const MSG_ERROR_DEVICE_INVALID_COMMAND = 'Invalid changer command.';
	const MSG_ERROR_DEVICE_AUTOCHANGER_DOES_NOT_EXIST = 'Autochanger does not exist.';
	const MSG_ERROR_DEVICE_AUTOCHANGER_DRIVE_DOES_NOT_EXIST = 'Autochanger drive does not exist.';
	const MSG_ERROR_DEVICE_WRONG_SLOT_NUMBER = 'Wrong slot number.';
	const MSG_ERROR_DEVICE_DRIVE_DOES_NOT_BELONG_TO_AUTOCHANGER = 'Drive does not belong to selected autochanger.';
}

class BasicUserError extends GenericError {

	const ERROR_BASIC_USER_DOES_NOT_EXIST = 140;
	const ERROR_BASIC_USER_ALREADY_EXISTS = 141;
	const ERROR_BASIC_USER_INVALID_USERNAME = 142;
	const ERROR_BASIC_USER_INVALID_BCONSOLE_CFG_PATH = 143;
	const ERROR_BASIC_USER_INVALID_CONSOLE = 144;
	const ERROR_BASIC_USER_INVALID_DIRECTOR = 145;
	const ERROR_BASIC_USER_INVALID_PASSWORD= 146;

	const MSG_ERROR_BASIC_USER_DOES_NOT_EXIST = 'Basic user does not exist.';
	const MSG_ERROR_BASIC_USER_ALREADY_EXISTS = 'Basic user already exists.';
	const MSG_ERROR_BASIC_USER_INVALID_USERNAME = 'Invalid basic user username';
	const MSG_ERROR_BASIC_USER_INVALID_BCONSOLE_CFG_PATH = 'Invalid bconsole config path.';
	const MSG_ERROR_BASIC_USER_INVALID_CONSOLE = 'Invalid Console name.';
	const MSG_ERROR_BASIC_USER_INVALID_DIRECTOR = 'Invalid Director name.';
	const MSG_ERROR_BASIC_USER_INVALID_PASSWORD = 'Invalid password.';
}
?>
