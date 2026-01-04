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
 * Send authz type request to the ACME server.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class Finalize extends CommonModule
{
	/**
	 * Finalize sending CSR to the ACME service.
	 *
	 * @param array $props new order properties
	 * @param array $cmd_params command properties (use_sudo, user, password ...)
	 * @return array finalize details
	 */
	public function finalization(array $props, array $cmd_params = []): array
	{
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

		// First do backup current key and cert (if any)
		$ssl_cert = $this->getModule('ssl_cert');
		$result = $ssl_cert->backupCertAndKeyFiles($cmd_params);
		$state = ($result['error'] == 0);
		if (!$state) {
			return $result;
		}

		// Next remove previous key and cert (if any)
		$ssl_cert = $this->getModule('ssl_cert');
		$result = $ssl_cert->removeCertAndKeyFiles($cmd_params);
		$state = ($result['error'] == 0);
		if (!$state) {
			return $result;
		}

		// Create certificate private key
		$key = SSLCertificate::getKeyFilePath();
		$crypto_keys = $this->getModule('crypto_keys');
		$result = $crypto_keys->createPrivateKey(
			RSAKey::KEY_TYPE,
			$key,
			$cmd_params
		);
		$state = ($result['error'] == 0);
		if (!$state) {
			$ssl_cert->restoreCertAndKeyFiles($cmd_params);
			return $result;
		}

		$ssl_cert = new SSLCertificate();
		$result = $ssl_cert->createCSR(
			$props['common_name'],
			$props['email'],
			$cmd_params
		);

		$csr = '';
		if ($result['error'] == 0) {
			$csr = $result['output'];
		}

		$data = [
			'csr' => $csr
		];
		$jwt = $this->getModule('jwt');
		$parts = $jwt->getTokenParts($jwt_props, $data, $cmd_params);
		$body = Request::prepareBody(
			$parts['header'],
			$parts['data'],
			$parts['signature']
		);
		$ret = Request::post($url, $body);

		if ($ret['error'] == 0 && isset($ret['output']['status']) && $ret['output']['status'] == Request::STATUS_PENDING) {
			$authz_props = [
				'url' => $props['authz_url'],
				'nonce' => $ret['nonce'],
				'kid' => $props['kid']
			];
			$ret = $this->waitOnFinishAuthz($authz_props, $cmd_params, $ret);
		} elseif ($ret['error'] == 403 && $ret['type'] == 'orderNotReady') {
			$order_props = [
				'url' => $props['order_url'],
				'nonce' => $ret['nonce'],
				'kid' => $props['kid'],
				'privkey_file' => $props['privkey_file']
			];
			$ret = $this->checkOrder($order_props, $cmd_params, $ret);
		}

		if ($ret['error'] == Request::ERROR_REPEAT_REQUEST) {
			$ret = Request::resend(
				$this,
				'finalization',
				[$props, $cmd_params],
				$ret
			);
		}
		$state = ($ret['error'] == 0);
		if (!$state) {
			// Error
			$emsg = "Error while sending finalize order request to ACME server.";
			$lmsg = $emsg . " ExitCode: {$ret['error']}, Error: {$ret['raw']}.";
			Logging::log(
				Logging::CATEGORY_EXTERNAL,
				$lmsg
			);
			$ssl_cert->restoreCertAndKeyFiles($cmd_params);
		}
		return $ret;
	}

	private function checkOrder(array $props, array $cmd_params, array $result): array
	{
		$check_success = fn ($result) => (isset($result['output']['status']) && $result['output']['status'] == Request::STATUS_READY);
		$check_error = fn ($result) => (isset($result['output']['status']) && $result['output']['status'] == Request::STATUS_INVALID);
		/**
		 * Certificate is not ready yet.
		 * Check order status until it is ready (or max retry count achieved)
		 */
		Logging::log(
			Logging::CATEGORY_EXTERNAL,
			'Certificate is not ready yet. Wait until it is ready.'
		);
		$order = $this->getModule('acme_order');
		for ($i = 0; $i < Request::MAX_RETRY_REQUEST; $i++) {
			$result = Request::resend(
				$order,
				'checkOrder',
				[$props, $cmd_params],
				$result
			);
			$props['nonce'] = $result['nonce'];

			if ($check_success($result)) {
				// this way the finalize will be checked one more time after correct authz status
				$result['error'] = Request::ERROR_REPEAT_REQUEST;
				break;
			} elseif ($check_error($result)) {
				$result['error'] = 1;
				break;
			}
			sleep(2);
		}
		return $result;
	}

	private function waitOnFinishAuthz(array $props, array $cmd_params, array $result): array
	{
		$end_statuses = [
			Request::STATUS_VALID,
			Request::STATUS_INVALID
		];
		$check_auth = fn ($result) => (isset($result['output']['status']) && in_array($result['output']['status'], $end_statuses));

		/**
		 * Auth has not been checked yet.
		 * Check auth status until it is ready (or max retry count achieved)
		 */
		Logging::log(
			Logging::CATEGORY_EXTERNAL,
			'Auth is not ready yet. Wait until it is ready.'
		);
		$authz = $this->getModule('acme_authz');
		for ($i = 0; $i < Request::MAX_RETRY_REQUEST; $i++) {
			$result = Request::resend(
				$authz,
				'authorize',
				[$props, $cmd_params],
				$result
			);
			$props['nonce'] = $result['nonce'];

			if ($check_auth($result)) {
				// this way the finalize will be checked one more time after correct authz status
				$result['error'] = Request::ERROR_REPEAT_REQUEST;
				break;
			}
			sleep(2);
		}
		return $result;
	}
}
