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

namespace Bacularis\Common\Modules\Protocol\WebAuthn;

use Bacularis\Common\Modules\Miscellaneous;

/**
 * Register WebAuthn client on server.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class Register extends Base
{
	/**
	 * Credential identifier string length.
	 */
	private const CREDENTIALID_SIZE = 32;

	/**
	 * Maximum time to register single authenticator.
	 */
	public const REGISTRATION_TIMEOUT = 60000;

	/**
	 * Get new credential identifier.
	 * @see https://w3c.github.io/webauthn/#dom-publickeycredentialuserentity-id
	 *
	 * @return string challenge string
	 */
	public function getCredentialId(): string
	{
		$crypto = $this->getModule('crypto');
		$credential_id = $crypto->getRandomString(self::CREDENTIALID_SIZE);
		return $credential_id;
	}

	/**
	 * Get data structure to register a new authenticator.
	 *
	 * @param array $user user account properties
	 * @param string $origin origin
	 * @return array data structure to register
	 */
	public function getRegistrationData(array $user, string $origin): array
	{
		// Prepare excluded credentials - to not propose register already registered credentials
		$exclude_creds = [];
		if (key_exists('fidou2f_credentials', $user) && is_array($user['fidou2f_credentials'])) {
			foreach ($user['fidou2f_credentials'] as $id => $value) {
				$exclude_creds[] = [
					'type' => 'public-key',
					'id' => $id
				];
			}
		}

		// Prepare supported key algorithms
		$pubkey_cred_params = [];
		for ($i = 0; $i < count(parent::PUBLIC_KEY_ALG_TYPES); $i++) {
			$pubkey_cred_params[] = [
				'alg' => parent::PUBLIC_KEY_ALG_TYPES[$i],
				'type' => 'public-key'
			];
		}

		// Registration parameters
		$props = [
			'publicKey' => [
				'challenge' => parent::getChallenge(),
				'rp' => [
					'name' => parent::RP_NAME,
					'id' => $origin
				],
				'user' => [
					'id' => $user['username'],
					'name' => $user['username'],
					'displayName' => $user['username']
				],
				'pubKeyCredParams' => $pubkey_cred_params,
				'timeout' => self::REGISTRATION_TIMEOUT,
				'attestation' => 'none',
				'excludeCredentials' => $exclude_creds,
				'userVerification' => 'discouraged'
			]
		];
		return $props;
	}

	/**
	 * Create authenticator credentials
	 *
	 * @param string $org_id organization identifier to set credentials
	 * @param string $user_id user identifier to set credentials
	 * @param array $data_reg validated registration data
	 * @return string current credential identifier on success, empty string otherwise
	 */
	public function createCredential(string $org_id, string $user_id, array $data_reg): string
	{
		$crypto_keys = $this->getModule('crypto_keys');

		// Prepare public key
		$pubkey_der = implode('', array_map('chr', $data_reg['publicKey']));
		$pubkey_pem = $crypto_keys->derToPem($pubkey_der, 'PUBLIC KEY');

		// Prepare credential
		$credential_id = Miscellaneous::base64UrlToBase64($data_reg['id']);
		$credential_val = http_build_query([
			'pubkey' => $pubkey_pem,
			'pubkey_alg' => $data_reg['publicKeyAlgorithm'],
			'transports' => $data_reg['transports'],
			'name' => 'Security key',
			'added' => time(),
			'last_used' => 0
		]);

		// Prepare U2F config
		$config = [
			'fidou2f_credentials' => [
				$credential_id => $credential_val
			]
		];
		$user_config = $this->getModule('user_config');
		$result = $user_config->updateUserConfig($org_id, $user_id, $config);
		$ret = ($result ? $credential_id : '');
		return $ret;
	}


	/**
	 * Registration data validator.
	 *
	 * @param array $data_reg registration data to validate
	 * @param string $origin origin
	 * @param string $rp_id relaying party id
	 * @param string $challenge challenge string
	 */
	public function validateRegistration(array $data_reg, string $origin, string $rp_id, string $challenge): array
	{
		$misc = $this->getModule('misc');

		// Prepare relying party identifier to validate
		$auth_data = implode(array_map('chr', $data_reg['authData'] ?? []));
		$rp_id_hash = bin2hex(substr($auth_data, 0, 32));

		// Prepare challenge to validate
		$client_data_challenge = $misc->decodeBase64URL($data_reg['clientDataJSON']['challenge']);

		$error = '';
		if (!key_exists('clientDataJSON', $data_reg)) {
			$error = 'Missing client data property';
		} elseif (!is_array($data_reg['clientDataJSON'])) {
			$error = 'Invalid client data type';
		} elseif (!key_exists('type', $data_reg['clientDataJSON'])) {
			$error = 'Missing type property in client data';
		} elseif ($data_reg['clientDataJSON']['type'] !== 'webauthn.create') {
			$error = 'Invalid client data type property';
		} elseif (!key_exists('challenge', $data_reg['clientDataJSON'])) {
			$error = 'Missing challenge property';
		} elseif (empty($challenge) || strcmp($client_data_challenge, $challenge) != 0) {
			$error = 'Invalid challenge property';
		} elseif (!key_exists('origin', $data_reg['clientDataJSON'])) {
			$error = 'Missing origin property';
		} elseif ($data_reg['clientDataJSON']['origin'] !== $origin) {
			$error = 'Invalid data origin property';
		} elseif (empty($rp_id_hash)) {
			$error = 'Missing rpIdHash property';
		} elseif (!hash_equals(hash('sha256', $rp_id), $rp_id_hash)) {
			$error = 'Invalid rpHashid property';
		} elseif (!key_exists('publicKey', $data_reg)) {
			$error = 'Missing publicKey property';
		}
		return [
			'valid' => empty($error),
			'error' => $error
		];
	}
}
