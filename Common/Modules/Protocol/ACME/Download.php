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
use Bacularis\Common\Modules\RSAKey;

/**
 * Download a new certificate from the ACME server.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class Download extends CommonModule
{
	/**
	 * Send download certificate request to the ACME service.
	 *
	 * @param array $props new order properties
	 * @param array $cmd_params command properties (use_sudo, user, password ...)
	 * @return array order details
	 */
	public function downloadCertificate(array $props, array $cmd_params = []): array
	{
		$data = [];
		$privkey_file = $props['privkey_file'] ?? '';
		$nonce = $props['nonce'] ?? '';
		$kid = $props['kid'] ?? '';
		$url = $props['url'] ?? '';
		$jwt_props = [
			'key_type' => RSAKey::KEY_TYPE,
			'privkey_file' => $privkey_file,
			'nonce' => $nonce,
			'kid' => $kid,
			'url' => $url
		];

		$jwt = $this->getModule('jwt');
		$parts = $jwt->getTokenParts($jwt_props, $data, $cmd_params);
		$body = Request::prepareBody(
			$parts['header'],
			$parts['data'],
			$parts['signature']
		);
		$ret = Request::post($url, $body);
		if ($ret['error'] == Request::ERROR_REPEAT_REQUEST) {
			$ret = Request::resend(
				$this,
				'downloadCertificate',
				[$props, $cmd_params],
				$ret
			);
		}
		$state = ($ret['error'] == 0);
		if (!$state) {
			// Error
			$emsg = "Error while downloading certificate from ACME server.";
			$output = implode(PHP_EOL, $ret['output']);
			$lmsg = $emsg . " ExitCode: {$ret['error']}, Error: {$output}.";
			Logging::log(
				Logging::CATEGORY_EXTERNAL,
				$lmsg
			);
		}
		return $ret;
	}
}
