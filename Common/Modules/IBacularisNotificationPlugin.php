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
 * Interface for notification plugin type.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Interface
 */
interface IBacularisNotificationPlugin extends IBacularisPlugin
{
	/**
	 * Main execute command.
	 *
	 * @param string $type message type (INFO, WARNING, ERROR)
	 * @param string $category message category (Config, Action, Application, Security)
	 * @param string $msg message body
	 * @return bool true on success, false otherwise
	 */
	public function execute(string $type, string $category, string $msg): bool;
}
