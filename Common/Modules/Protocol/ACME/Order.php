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

namespace Bacularis\Common\Modules\Protocol\ACME;

use Bacularis\Common\Modules\AuditLog;
use Bacularis\Common\Modules\CommonModule;
use Bacularis\Common\Modules\Logging;
use Bacularis\Common\Modules\RSAKey;
use Bacularis\Common\Modules\SSLCertificate;

/**
 * Send new order to the ACME server.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class Order extends CommonModule
{
	/**
	 * ACME protocol field type.
	 */
	private const FIELD_TYPE = 'newOrder';

	/**
	 * Send order request to the ACME service.
	 *
	 * @param array $props new order properties
	 * @param array $cmd_params command properties (use_sudo, user, password ...)
	 * @return array order details
	 */
	public function createOrder(array $props, array $cmd_params = []): array
	{
		$directory = Directory::get($props['directory_url']);
		if (!key_exists(self::FIELD_TYPE, $directory)) {
			// Directory does not contain the new order field
			return [
				'error' => 1,
				'output' => ['Unable to communicate with the ACME server.']
			];
		}

		$data = [
			'identifiers' => [
				['type' => 'dns', 'value' => $props['common_name']]
			]
		];

		$privkey_file = $props['privkey_file'] ?? '';

		$nonce = $props['nonce'] ?? '';
		$kid = $props['kid'] ?? '';
		$jwt_props = [
			'key_type' => RSAKey::KEY_TYPE,
			'privkey_file' => $privkey_file,
			'nonce' => $nonce,
			'kid' => $kid,
			'url' => $directory[self::FIELD_TYPE]
		];

		$jwt = $this->getModule('jwt');
		$parts = $jwt->getTokenParts($jwt_props, $data, $cmd_params);
		$body = Request::prepareBody(
			$parts['header'],
			$parts['data'],
			$parts['signature']
		);
		$ret = Request::post($directory[self::FIELD_TYPE], $body);
		if ($ret['error'] == Request::ERROR_REPEAT_REQUEST) {
			$ret = Request::resend(
				$this,
				'createOrder',
				[$props, $cmd_params],
				$ret
			);
		}
		$state = ($ret['error'] == 0);
		if (!$state) {
			// Error while setting up order
			$emsg = "Error while sending a new certificate order to ACME server.";
			$output = implode(PHP_EOL, $ret['output']);
			$lmsg = $emsg . " ExitCode: {$ret['error']}, Error: {$output}.";
			Logging::log(
				Logging::CATEGORY_EXTERNAL,
				$lmsg
			);
		}
		return $ret;
	}

	/**
	 * Send check order request to the ACME service.
	 *
	 * @param array $props new order properties
	 * @param array $cmd_params command properties (use_sudo, user, password ...)
	 * @return array order details
	 */
	public function checkOrder(array $props, array $cmd_params = []): array
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
				'checkOrder',
				[$props, $cmd_params],
				$ret
			);
		}
		$state = ($ret['error'] == 0);
		if (!$state) {
			// Error while setting up order
			$emsg = "Error while sending check certificate order to ACME server.";
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
