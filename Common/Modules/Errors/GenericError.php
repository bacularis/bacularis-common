<?php
/*
 * Bacularis - Bacula web interface
 *
 * Copyright (C) 2021 Marcin Haba
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
