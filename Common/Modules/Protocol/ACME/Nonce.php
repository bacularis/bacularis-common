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

namespace Bacularis\Common\Modules\Protocol\ACME;

use Bacularis\Common\Modules\AuditLog;
use Bacularis\Common\Modules\CommonModule;
use Bacularis\Common\Modules\Logging;

/**
 * Get nonce from ACME server.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class Nonce extends CommonModule
{
	/**
	 * ACME protocol field type.
	 */
	private const FIELD_TYPE = 'newNonce';

	/**
	 * Get nonce from ACME service.
	 *
	 * @param array $props new account properties
	 * @return string new nonce
	 */
	public function newNonce(array $props): string
	{
		$directory = Directory::get($props['directory_url']);
		if (!key_exists(self::FIELD_TYPE, $directory)) {
			// Directory does not contain the new nonce field
			return '';
		}

		$ret = Request::head($directory[self::FIELD_TYPE]);
		$state = ($ret['error'] == 0);
		$result = '';
		if (!$state) {
			// Error while setting up account
			$emsg = "Error while getting nonce from ACME server.";
			$output = implode(PHP_EOL, $ret['output']);
			$lmsg = $emsg . " ExitCode: {$ret['exitcode']}, Error: {$output}.";
			Logging::log(
				Logging::CATEGORY_EXTERNAL,
				$lmsg
			);
		} else {
			$result = $ret['nonce'];
		}
		return $result;
	}
}
