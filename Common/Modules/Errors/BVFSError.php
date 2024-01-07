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
 * BVFS error class.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Errors
 */
class BVFSError extends GenericError
{
	public const ERROR_INVALID_RPATH = 71;
	public const ERROR_INVALID_RESTORE_PATH = 72;
	public const ERROR_INVALID_JOBID_LIST = 73;
	public const ERROR_INVALID_FILEID_LIST = 74;
	public const ERROR_INVALID_FILEINDEX_LIST = 75;
	public const ERROR_INVALID_DIRID_LIST = 76;
	public const ERROR_INVALID_CLIENT = 77;
	public const ERROR_INVALID_JOBID = 78;

	public const MSG_ERROR_INVALID_RPATH = 'Inputted path for restore is invalid. Proper format is b2[0-9]+.';
	public const MSG_ERROR_INVALID_RESTORE_PATH = 'Inputted BVFS path param is invalid.';
	public const MSG_ERROR_INVALID_JOBID_LIST = 'Invalid jobid list.';
	public const MSG_ERROR_INVALID_FILEID_LIST = 'Invalid fileid list.';
	public const MSG_ERROR_INVALID_FILEINDEX_LIST = 'Invalid file index list.';
	public const MSG_ERROR_INVALID_DIRID_LIST = 'Invalid dirid list.';
	public const MSG_ERROR_INVALID_CLIENT = 'Invalid client name.';
	public const MSG_ERROR_INVALID_JOBID = 'Invalid jobid.';
}
