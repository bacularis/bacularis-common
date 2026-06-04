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

namespace Bacularis\Common\Modules\Protocol\HTTP;

use Bacularis\Common\Modules\CommonModule;
use Prado\Prado;

/**
 * HTTP redirections module.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class Redirection extends CommonModule
{
	/**
	 * Redirect to given address.
	 * NOTE: This method exits the app
	 *
	 * @param string $url destination address
	 */
	public static function redirect(string $url): void
	{
		header('Location: ' . $url);
		$app = Prado::getApplication();
		$log = $app->getModule('log');
		if ($log) {
			$log->collectLogs(null);
		}
		exit();
	}
}
