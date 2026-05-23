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

namespace Bacularis\Common\Modules\Errors;

/**
 * Amazon error class.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Errors
 */
class CloudAmazonError extends GenericError
{
	public const ERROR_INVALID_ACCOUNT = 160;
	public const ERROR_ACCOUNT_DOES_NOT_EXIST = 161;
	public const ERROR_ACCOUNT_ALREADY_EXISTS = 162;
	public const ERROR_AWS_CLI_INVALID_OUTPUT = 163;

	public const MSG_ERROR_INVALID_ACCOUNT = 'Invalid account.';
	public const MSG_ERROR_ACCOUNT_DOES_NOT_EXIST = 'Account does not exist.';
	public const MSG_ERROR_ACCOUNT_ALREADY_EXISTS = 'Account already exists.';
	public const MSG_ERROR_AWS_CLI_INVALID_OUTPUT = 'Invalid output from AWS CLI.';
}
