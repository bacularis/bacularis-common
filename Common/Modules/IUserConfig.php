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

namespace Bacularis\Common\Modules;

/**
 * Defines methods to work on user config data.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Interfaces
 */
interface IUserConfig
{
	public function validateUsernamePassword($username, $password, $check_conf = true);
}
