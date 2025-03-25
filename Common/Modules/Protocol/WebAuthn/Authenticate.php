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

namespace Bacularis\Common\Modules\Protocol\WebAuthn;

use Prado\Prado;
use Bacularis\Common\Modules\Miscellaneous;

/**
 * Authenticate WebAuthn client on server.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class Authenticate extends Base
{
	/**
	 * Maximum time to authenticate by key.
	 */
	public const AUTHENTICATION_TIMEOUT = 60000;

	/**
	 * Get data structure to authenticate with authenticator.
	 *
	 * @param array $user user account properties
	 * @param string $origin origin
	 * @return array data sturture to authenticate
	 */
	public static function getAuthData(array $user, string $origin): array
	{
		$challenge = parent::getChallenge();
		$challenge = base64_decode($challenge);
		$auth_data = [
			'challenge' => array_map(
				'ord',
				str_split($challenge)
			),
			'user' => [
				'id' => $user['username'],
				'name' => $user['username'],
				'displayName' => ($user['long_name'] ?: '')
			],
			'rpId' => $origin,
			'allowCredentials' => [],
			'timeout' => self::AUTHENTICATION_TIMEOUT,
			'userVerification' => 'discouraged'
		];
		$fidou2f_creds = $user['fidou2f_credentials'] ? array_keys($user['fidou2f_credentials']) : [];
		for ($i = 0; $i < count($fidou2f_creds); $i++) {
			parse_str($user['fidou2f_credentials'][$fidou2f_creds[$i]], $params);
			$auth_data['allowCredentials'][] = [
				'id' => array_map(
					'ord',
					str_split(base64_decode($fidou2f_creds[$i]))
				),
				'transports' => $params['transports'],
				'type' => 'public-key'
			];
		}
		return $auth_data;
	}

	/**
	 * Validate authentication data.
	 *
	 * @param mixed $data_auth authentication data
	 * @return array authentication result and error message
	 */
	public function validateAuth($data_auth)
	{
		$error = '';
		if (!is_array($data_auth)) {
			$error = 'Wrong response authentication data.';
		} elseif (!key_exists('id', $data_auth)) {
			$error = 'Missing id property';
		} elseif (!key_exists('response', $data_auth)) {
			$error = 'Missing client response property';
		} elseif (!key_exists('clientDataJSON', $data_auth['response'])) {
			$error = 'Missing client data property';
		} elseif (!key_exists('authenticatorData', $data_auth['response'])) {
			$error = 'Missing client authenticator data property';
		} elseif (!key_exists('signature', $data_auth['response'])) {
			$error = 'Missing data signature property';
		}
		return [
			'valid' => empty($error),
			'error' => $error
		];
	}

	/**
	 * Authenticate user in second factor logging in.
	 *
	 * @param array $data_auth validated authentication data
	 * @param string $username user name
	 * @return bool true on success, false otherwise
	 */
	public function authenticate(array $data_auth, string $username): bool
	{
		// Prepare client data
		$client_data_json = Miscellaneous::decodeBase64URL($data_auth['response']['clientDataJSON']);
		$client_data_json_hash_bin = hash('sha256', $client_data_json, true);

		// Prepare authenticator data
		$authenticator_data_bin = Miscellaneous::decodeBase64URL($data_auth['response']['authenticatorData']);
		$signature = Miscellaneous::decodeBase64URL($data_auth['response']['signature']);
		$data = $authenticator_data_bin . $client_data_json_hash_bin;

		// Prepare user data
		$user_config = $this->getModule('user_config');
		$user = $user_config->getUserConfig($username);
		$credential_id = Miscellaneous::base64UrlToBase64($data_auth['id']);

		if (!key_exists($credential_id, $user['fidou2f_credentials'])) {
			// try to log in with non-existing credential. It should not happen.
			return false;
		}

		// Prpeare public key
		parse_str($user['fidou2f_credentials'][$credential_id], $creds);
		$creds['last_used'] = time();
		$pubkey = trim($creds['pubkey']);

		$result = $this->verifySignature(
			$data,
			$signature,
			$pubkey
		);

		// Update authenticator last used time
		$config = [
			'fidou2f_credentials' => [
				$credential_id => http_build_query($creds)
			]
		];
		$user_config->updateUserConfig(
			$username,
			$config
		);
		return $result;
	}

	/**
	 * Verify authenticator signature.
	 *
	 * @param string $data signed data
	 * @param string $signature data signature
	 * @param string $pubkey public key to verify signature
	 * @return bool true if signature is verified successfully, false otherwise
	 */
	public function verifySignature(string $data, string $signature, string $pubkey): bool
	{
		$crypto_keys = $this->getModule('crypto_keys');
		$tmpdir = Prado::getPathOfNamespace('Bacularis.Web.Config');
		$state = $crypto_keys->verifySignatureString($pubkey, $signature, $data, $tmpdir);
		return $state;
	}
}
