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
 * OAuth2 error class.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Errors
 */
class OAuth2Error extends GenericError
{
	public const ERROR_OAUTH2_CLIENT_DOES_NOT_EXIST = 120;
	public const ERROR_OAUTH2_CLIENT_ALREADY_EXISTS = 121;
	public const ERROR_OAUTH2_CLIENT_INVALID_CLIENT_ID = 122;
	public const ERROR_OAUTH2_CLIENT_INVALID_CLIENT_SECRET = 123;
	public const ERROR_OAUTH2_CLIENT_INVALID_REDIRECT_URI = 124;
	public const ERROR_OAUTH2_CLIENT_INVALID_SCOPE = 125;
	public const ERROR_OAUTH2_CLIENT_INVALID_BCONSOLE_CFG_PATH = 126;
	public const ERROR_OAUTH2_CLIENT_INVALID_NAME = 127;
	public const ERROR_OAUTH2_CLIENT_INVALID_CONSOLE = 128;
	public const ERROR_OAUTH2_CLIENT_INVALID_DIRECTOR = 129;

	public const MSG_ERROR_OAUTH2_CLIENT_DOES_NOT_EXIST = 'OAuth2 client does not exist.';
	public const MSG_ERROR_OAUTH2_CLIENT_ALREADY_EXISTS = 'OAuth2 client already exists.';
	public const MSG_ERROR_OAUTH2_CLIENT_INVALID_CLIENT_ID = 'Invalid OAuth2 client ID.';
	public const MSG_ERROR_OAUTH2_CLIENT_INVALID_CLIENT_SECRET = 'Invalid OAuth2 client secret.';
	public const MSG_ERROR_OAUTH2_CLIENT_INVALID_REDIRECT_URI = 'Invalid OAuth2 redirect URI.';
	public const MSG_ERROR_OAUTH2_CLIENT_INVALID_SCOPE = 'Invalid OAuth2 scope.';
	public const MSG_ERROR_OAUTH2_CLIENT_INVALID_BCONSOLE_CFG_PATH = 'Invalid Bconsole config path.';
	public const MSG_ERROR_OAUTH2_CLIENT_INVALID_NAME = 'Invalid OAuth2 client name.';
	public const MSG_ERROR_OAUTH2_CLIENT_INVALID_CONSOLE = 'Invalid Console name.';
	public const MSG_ERROR_OAUTH2_CLIENT_INVALID_DIRECTOR = 'Invalid Director name.';
}
