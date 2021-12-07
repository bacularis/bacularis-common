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
 * JSON tools error class.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Errors
 * @package Baculum Common
 */

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
