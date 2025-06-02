<?php
/*
 * Bacularis - Bacula web interface
 *
 * Copyright (C) 2021-2025 Marcin Haba
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
 * Interface for running action plugin.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Interface
 */
interface IBacularisRunActionPlugin extends IBacularisPlugin
{
	/**
	 * Main run action command.
	 *
	 * @param string $action_type action name
	 * @param string|null $type resource type
	 * @param string|null $name resource name
	 */
	public function run(string $action_type, ?string $type = null, ?string $name = null);
}
