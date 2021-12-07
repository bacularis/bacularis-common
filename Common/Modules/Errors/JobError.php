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
 * Job error class.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Errors
 * @package Baculum Common
 */

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
