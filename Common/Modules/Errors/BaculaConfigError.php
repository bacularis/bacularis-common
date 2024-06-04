<?php
/*
 * Bacularis - Bacula web interface
 *
 * Copyright (C) 2021-2024 Marcin Haba
 *
 * The main author of Bacularis is Marcin Haba, with contributors, whose
 * full list can be found in the AUTHORS file.
 *
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

namespace Bacularis\Common\Modules\Errors;

/**
 * Bacula config error class.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Errors
 */
class BaculaConfigError extends GenericError
{
	public const ERROR_CONFIG_DIR_NOT_WRITABLE = 90;
	public const ERROR_UNEXPECTED_BACULA_CONFIG_VALUE = 91;
	public const ERROR_CONFIG_NO_JSONTOOL_READY = 92;
	public const ERROR_WRITE_TO_CONFIG_ERROR = 93;
	public const ERROR_CONFIG_VALIDATION_ERROR = 94;
	public const ERROR_USER_NOT_ALLOWED_TO_READ_RESOURCE_CONFIG = 95;
	public const ERROR_USER_NOT_ALLOWED_TO_WRITE_RESOURCE_CONFIG = 96;
	public const ERROR_CONFIG_DOES_NOT_EXIST = 97;
	public const ERROR_CONFIG_ALREADY_EXISTS = 98;
	public const ERROR_CONFIG_DEPENDENCY_ERROR = 99;

	public const MSG_ERROR_CONFIG_DIR_NOT_WRITABLE = 'Config directory is not writable.';
	public const MSG_ERROR_UNEXPECTED_BACULA_CONFIG_VALUE = 'Unexpected Bacula config value.';
	public const MSG_ERROR_CONFIG_NO_JSONTOOL_READY = 'No JSON tool ready.';
	public const MSG_ERROR_WRITE_TO_CONFIG_ERROR = 'Write to config file error.';
	public const MSG_ERROR_CONFIG_VALIDATION_ERROR = 'Config validation error.';
	public const MSG_ERROR_USER_NOT_ALLOWED_TO_READ_RESOURCE_CONFIG = 'User is not allowed to read resource config.';
	public const MSG_ERROR_USER_NOT_ALLOWED_TO_WRITE_RESOURCE_CONFIG = 'User is not allowed to write resource config.';
	public const MSG_ERROR_CONFIG_DOES_NOT_EXIST = 'Config does not exist.';
	public const MSG_ERROR_CONFIG_ALREADY_EXISTS = 'Config already exists.';
	public const MSG_ERROR_CONFIG_DEPENDENCY_ERROR = 'Config dependency error.';
}
