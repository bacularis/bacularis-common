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
 * Interface for action plugin.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Interface
 */
interface IBacularisActionPlugin extends IBacularisPlugin
{
	/**
	 * Main run action command.
	 *
	 * @param null|string $name action object to run
	 * @param null|string $type resource type
	 * @return bool true on success, false otherwise
	 */
	public function run(?string $type, ?string $name = null): bool;

	/**
	 * Get action resource.
	 *
	 * @return string action resource
	 */
	public static function getResource(): string;
}
