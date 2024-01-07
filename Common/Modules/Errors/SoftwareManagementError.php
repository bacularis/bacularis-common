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

namespace Bacularis\Common\Modules\Errors;

/**
 * Sotware management error class.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Errors
 */
class SoftwareManagementError extends GenericError
{
	public const ERROR_SOFTWARE_MANAGEMENT_COMMAND_DOES_NOT_EXIST = 150;
	public const ERROR_SOFTWARE_MANAGEMENT_DISABLED = 151;
	public const ERROR_SOFTWARE_MANAGEMENT_WRONG_EXITCODE = 152;
	public const ERROR_SOFTWARE_MANAGEMENT_COMMAND_NOT_CONFIGURED = 153;

	public const MSG_ERROR_SOFTWARE_MANAGEMENT_COMMAND_DOES_NOT_EXIST = 'Software management command does not exist.';
	public const MSG_ERROR_SOFTWARE_MANAGEMENT_DISABLED = 'Software managment is disabled.';
	public const MSG_ERROR_SOFTWARE_MANAGEMENT_WRONG_EXITCODE = 'Non-zero exitcode returned by software management command.';
	public const MSG_ERROR_SOFTWARE_MANAGEMENT_COMMAND_NOT_CONFIGURED = 'Software managment command is not configured.';
}
