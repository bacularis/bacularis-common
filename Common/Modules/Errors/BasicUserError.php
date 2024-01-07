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
 * Basic user error class.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Errors
 */
class BasicUserError extends GenericError
{
	public const ERROR_BASIC_USER_DOES_NOT_EXIST = 140;
	public const ERROR_BASIC_USER_ALREADY_EXISTS = 141;
	public const ERROR_BASIC_USER_INVALID_USERNAME = 142;
	public const ERROR_BASIC_USER_INVALID_BCONSOLE_CFG_PATH = 143;
	public const ERROR_BASIC_USER_INVALID_CONSOLE = 144;
	public const ERROR_BASIC_USER_INVALID_DIRECTOR = 145;
	public const ERROR_BASIC_USER_INVALID_PASSWORD = 146;

	public const MSG_ERROR_BASIC_USER_DOES_NOT_EXIST = 'Basic user does not exist.';
	public const MSG_ERROR_BASIC_USER_ALREADY_EXISTS = 'Basic user already exists.';
	public const MSG_ERROR_BASIC_USER_INVALID_USERNAME = 'Invalid basic user username';
	public const MSG_ERROR_BASIC_USER_INVALID_BCONSOLE_CFG_PATH = 'Invalid bconsole config path.';
	public const MSG_ERROR_BASIC_USER_INVALID_CONSOLE = 'Invalid Console name.';
	public const MSG_ERROR_BASIC_USER_INVALID_DIRECTOR = 'Invalid Director name.';
	public const MSG_ERROR_BASIC_USER_INVALID_PASSWORD = 'Invalid password.';
}
