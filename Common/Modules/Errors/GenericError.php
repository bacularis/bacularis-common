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
 * Generic error class.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Errors
 */
class GenericError
{
	public const ERROR_NO_ERRORS = 0;
	public const ERROR_INVALID_COMMAND = 1;
	public const ERROR_INTERNAL_ERROR = 1000;
	public const ERROR_INVALID_PATH = 8;
	public const ERROR_WRONG_EXITCODE = 9;

	public const MSG_ERROR_NO_ERRORS = '';
	public const MSG_ERROR_INVALID_COMMAND = 'Invalid command.';
	public const MSG_ERROR_INTERNAL_ERROR = 'Internal error.';
	public const MSG_ERROR_INVALID_PATH = 'Invalid path.';
	public const MSG_ERROR_WRONG_EXITCODE = 'Wrong exitcode.';
}
