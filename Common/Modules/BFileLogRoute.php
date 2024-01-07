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

use Prado\Util\TFileLogRoute;

/**
 * Custom file log route.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class BFileLogRoute extends TFileLogRoute
{
	protected function formatLogMessage($log, $level, $category, $time)
	{
		$timestamp = date('Y-m-d H:i:s');
		$format = sprintf('[%s]', $category);
		return implode(
			' ',
			[$timestamp, $format, $log]
		);
	}
}
