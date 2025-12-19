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
use Bacularis\Common\Modules\RSAKey;
use Bacularis\Common\Modules\ShellCommandModule;
use Bacularis\Common\Modules\Logging;

/**
 * Create account on ACME server.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class Account extends CommonModule
{
	/**
	 * ACME protocol field type.
	 */
	private const FIELD_TYPE = 'newAccount';

	/**
	 * Create new account in ACME service.
	 *
	 * @param array $props new account properties
	 * @param array $cmd_params command properties (use_sudo, user, password ...)
	 * @return array output with output, error and nonce or empty array on error
	 */
	public function createAccount(array $props, array $cmd_params = []): array
	{
		$directory = Directory::get($props['directory_url']);
		if (!key_exists(self::FIELD_TYPE, $directory)) {
			// Directory does not contain the new account field
			return [
				'error' => 1,
				'output' => ['Unable to communicate with the ACME server.']
			];
		}

		// This implementation assumes one e-mail address
		$contact = sprintf('mailto:%s', $props['email']);

		$data = [
			'termsOfServiceAgreed' => $props['tos_agreed'],
			'contact' => [
				$contact
			]
		];

		$privkey_file = $props['privkey_file'] ?? '';
		$crypto_keys = $this->getModule('crypto_keys');

		// Create account private key if not exists
		if (!ShellCommandModule::fileExists($privkey_file, $cmd_params)) {
			$result = $crypto_keys->createPrivateKey(
				RSAKey::KEY_TYPE,
				$privkey_file,
				$cmd_params
			);
			$state = ($result['error'] == 0);
			if (!$state) {
				return $result;
			}
		}

		$result = $crypto_keys->getPublicKey(
			RSAKey::KEY_TYPE,
			$privkey_file,
			$cmd_params
		);
		$state = ($result['error'] == 0);
		if (!$state) {
			return $result;
		}
		$pubkey = $result['output'];

		$ret = $crypto_keys->getPublicKeyJWKFormat(
			RSAKey::KEY_TYPE,
			$pubkey,
			$cmd_params
		);
		$state = ($ret['error'] == 0);
		if (!$state) {
			return $ret;
		}
		$jwk = $ret['output'];
		$nonce = '';
		if (key_exists('nonce', $props)) {
			// If nonce exists, use it
			$nonce = $props['nonce'];
		} else {
			// If nonce not exists, acquire it
			$acme_nonce = $this->getModule('acme_nonce');
			$nonce = $acme_nonce->newNonce($props);
		}

		$jwt_props = [
			'key_type' => RSAKey::KEY_TYPE,
			'privkey_file' => $privkey_file,
			'pubkey' => $jwk,
			'nonce' => $nonce,
			'kid' => '', // no kid in this request type
			'url' => $directory[self::FIELD_TYPE]
		];
		$jwt_props = array_merge($props, $jwt_props);

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
				'createAccount',
				[$props, $cmd_params],
				$ret
			);
		}
		$state = ($ret['error'] == 0);
		if (!$state) {
			// Error while setting up account
			$emsg = "Error while creating CA account.";
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
	 * Get existing account from ACME service.
	 *
	 * @param array $props new account properties
	 * @param array $cmd_params command properties (use_sudo, user, password ...)
	 * @return array output with output, error and nonce or empty array on error
	 */
	public function getExistingAccount(array $props, array $cmd_params = []): array
	{
		$directory = Directory::get($props['directory_url']);
		if (!key_exists(self::FIELD_TYPE, $directory)) {
			// Directory does not contain the new account field
			return [
				'error' => 1,
				'output' => ['Unable to communicate with the ACME server.']
			];
		}

		$data = [
			'onlyReturnExisting' => true
		];

		$privkey_file = $props['privkey_file'] ?? '';
		$crypto_keys = $this->getModule('crypto_keys');
		$result = $crypto_keys->getPublicKey(
			RSAKey::KEY_TYPE,
			$privkey_file,
			$cmd_params
		);
		$state = ($result['error'] == 0);
		if (!$state) {
			return $result;
		}
		$pubkey = $result['output'];

		$ret = $crypto_keys->getPublicKeyJWKFormat(
			RSAKey::KEY_TYPE,
			$pubkey,
			$cmd_params
		);
		$state = ($ret['error'] == 0);
		if (!$state) {
			return $ret;
		}
		$jwk = $ret['output'];
		$acme_nonce = $this->getModule('acme_nonce');
		$nonce = $acme_nonce->newNonce($props);

		$jwt_props = [
			'key_type' => RSAKey::KEY_TYPE,
			'privkey_file' => $privkey_file,
			'pubkey' => $jwk,
			'nonce' => $nonce,
			'kid' => '', // no kid in this request type
			'url' => $directory[self::FIELD_TYPE]
		];
		$jwt_props = array_merge($props, $jwt_props);

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
				'getExistingAccount',
				[$props, $cmd_params],
				$ret
			);
		}
		$state = ($ret['error'] == 0);
		if (!$state) {
			// Error while setting up account
			$emsg = "Error while getting existing ACME account.";
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
