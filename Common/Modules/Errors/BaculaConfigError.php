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
 * Bacula config error class.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Errors
 * @package Baculum Common
 */

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
