<?php
/*
 * Bacularis - Bacula web interface
 *
 * Copyright (C) 2021-2025 Marcin Haba
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
 * Volume error class.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Errors
 */
class VolumeError extends GenericError
{
	public const ERROR_VOLUME_DOES_NOT_EXISTS = 30;
	public const ERROR_INVALID_VOLUME = 31;
	public const ERROR_INVALID_SLOT = 32;
	public const ERROR_VOLUME_ALREADY_EXISTS = 33;

	public const MSG_ERROR_VOLUME_DOES_NOT_EXISTS = 'Volume does not exist.';
	public const MSG_ERROR_INVALID_VOLUME = 'Invalid volume.';
	public const MSG_ERROR_INVALID_SLOT = 'Invalid slot.';
	public const MSG_ERROR_VOLUME_ALREADY_EXISTS = 'Volume already exists.';
}
