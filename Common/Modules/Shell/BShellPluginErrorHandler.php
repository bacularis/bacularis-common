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

namespace Bacularis\Common\Modules\Shell;

use Bacularis\Common\Modules\BPluginException;

/**
 * Bacularis shell plugin module.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class BShellPluginErrorHandler extends BShellErrorHandler
{
	public function handleError($sender, $exception)
	{
		$msg = $exception->getMessage();
		if ($exception instanceof BPluginException) {
			fwrite(STDERR, PHP_EOL . '[ERROR] ' . $msg . PHP_EOL);
		} else {
			$trace = $exception->getTraceAsString();
			fwrite(STDERR, PHP_EOL . '[ERROR] ' . $msg . PHP_EOL);
			fwrite(STDERR, PHP_EOL . '[TRACEBACK] ' . $trace . PHP_EOL);
		}
		exit(1);
	}
}
