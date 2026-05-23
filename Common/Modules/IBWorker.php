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

namespace Bacularis\Common\Modules;

/**
 * Bacularis worker interface.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
interface IBWorker
{
	/**
	 * Run Bacularis worker.
	 *
	 * @param IBWorkerTask $task task object
	 * @return mixed worker resource handler
	 */
	public function run(IBWorkerTask $task);
}
