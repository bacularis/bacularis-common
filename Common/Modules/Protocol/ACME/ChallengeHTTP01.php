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

use StdClass;
use Bacularis\Common\Modules\AuditLog;
use Bacularis\Common\Modules\Logging;
use Bacularis\Common\Modules\RSAKey;
use Bacularis\Common\Modules\ShellCommandModule;

/**
 * Send challenge http-01 request to the ACME server.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class ChallengeHTTP01 extends ShellCommandModule
{
	/**
	 * Challenge name.
	 */
	public const CHALLENGE_NAME = 'http-01';

	/**
	 * Send challenge http-01 request to the ACME service.
	 *
	 * @param array $props new order properties
	 * @param array $cmd_params command properties (use_sudo, user, password ...)
	 * @return array challenge details or empty array on error
	 */
	public function challenge(array $props, array $cmd_params = []): array
	{
		$result = $this->prepareChallange($props, $cmd_params);
		if ($result['error'] != 0) {
			return $result;
		}

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
		$data = new StdClass();
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
				'challenge',
				[$props, $cmd_params],
				$ret
			);
		}
		$end_statuses = [
			Request::STATUS_VALID,
			Request::STATUS_INVALID
		];
		$check_order = fn ($result) => (isset($result['output']['status']) && in_array($result['output']['status'], $end_statuses));
		if ($check_order($ret)) {
			/**
			 * Certificate is not ready yet.
			 * Check order status until it is ready (or max retry count achieved)
			 */
			Logging::log(
				Logging::CATEGORY_EXTERNAL,
				'Certificate is not ready yet. Wait until it is ready.'
			);
			$props = [
				'kid' => $kid,
				'privkey_file' => $privkey_file,
				'url' => $kid
			];
			$order = $this->getModule('acme_order');
			$result = $order->checkOrder($props, $cmd_params);
			for ($i = 0; $i < Request::MAX_RETRY_REQUEST; $i++) {
				$props['nonce'] = $ret['nonce'];
				$ret = Request::resend(
					$order,
					'checkOrder',
					[$props, $cmd_params],
					$ret
				);

				if ($check_order($ret)) {
					break;
				}
				sleep(2);
			}
		}

		$state = ($ret['error'] == 0);
		if (!$state) {
			// Error
			$emsg = "Error while sending challenge http-01 request to ACME server.";
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
	 * Prepare http-01 challenge file.
	 *
	 * @param array $props new order properties
	 * @param array $cmd_params command properties (use_sudo, user, password ...)
	 * @return array challenge details or empty array on error
	 */
	private function prepareChallange(array $props, array $cmd_params = [])
	{
		$crypto_keys = $this->getModule('crypto_keys');
		$privkey_file = $props['privkey_file'] ?? '';
		$key_type = RSAKey::KEY_TYPE;

		$ret = $crypto_keys->getJWKThumbprint(
			$key_type,
			$privkey_file,
			$cmd_params
		);
		$state = ($ret['error'] == 0);
		if (!$state) {
			return $ret;
		}
		$thumbprint = $ret['output'];

		$token = $props['token'] ?? '';
		$content = $token . '.' . $thumbprint;

		$acme_shell_commands = $this->getModule('acme_shell_commands');
		$result = $acme_shell_commands->createChallengeHttp01Dir($cmd_params);
		$state = ($result['error'] == 0);
		if ($state) {
			$result = $acme_shell_commands->createChallengeHttp01File(
				$token,
				$content,
				$cmd_params
			);
			$state = ($result['error'] == 0);
		}
		return $result;
	}
}
