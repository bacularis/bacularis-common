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

namespace Bacularis\Common\Modules;

use Bacularis\Common\Modules\Errors\GenericError;

/**
 * Self-signed certificate management.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class SelfSignedCert extends CommonModule
{
	/**
	 * Certificate type.
	 */
	public const CERT_TYPE = 'self-signed';

	/**
	 * Create self-signed certificate.
	 *
	 * @param array $params certificate params
	 * @param array $cmd_params command parameters
	 * @return array command results
	 */
	public function createCert(array $params, array $cmd_params): array
	{
		$misc = $this->getModule('misc');
		$days_no = $params['days_no'] ?? '3650';
		$common_name = $params['common_name'] ?? null;
		$email = $params['email'] ?? '';
		$country_code = $params['country_code'] ?? '';
		$state = $params['state'] ?? '';
		$locality = $params['locality'] ?? '';
		$organization = $params['organization'] ?? '';
		$organization_unit = $params['organization_unit'] ?? '';
		$is_valid = $misc->isValidCertificateDays($days_no);
		$is_valid = $is_valid && is_string($common_name) && $misc->isValidCertificateCommonName($common_name);
		$is_valid = $is_valid && is_string($email) && $misc->isValidCertificateEmail($email);
		$is_valid = $is_valid && is_string($country_code) && $misc->isValidCertificateCountry($country_code);
		$is_valid = $is_valid && is_string($state) && $misc->isValidCertificateTextValue($state);
		$is_valid = $is_valid && is_string($locality) && $misc->isValidCertificateTextValue($locality);
		$is_valid = $is_valid && is_string($organization) && $misc->isValidCertificateTextValue($organization);
		$is_valid = $is_valid && is_string($organization_unit) && $misc->isValidCertificateTextValue($organization_unit);
		if (!$is_valid) {
			return [
				'output' => [GenericError::MSG_ERROR_INVALID_COMMAND],
				'output_id' => '',
				'exitcode' => GenericError::ERROR_INVALID_COMMAND,
				'error' => GenericError::ERROR_INVALID_COMMAND
			];
		}

		$user = $cmd_params['user'] ?? '';
		$password = $cmd_params['password'] ?? '';
		$use_sudo = $cmd_params['use_sudo'] ?? false;

		$cmd = SSLCertificate::getPrepareHTTPSCertCommand($params, $cmd_params);
		$su = $this->getModule('su');
		$params = [
			'command' => implode(' ', $cmd),
			'use_sudo' => $use_sudo
		];
		$result = $su->execCommand(
			$user,
			$password,
			$params
		);
		return $result;
	}

	/**
	 * Renew self-signed certificate.
	 *
	 * @param array $cmd_params command parameters
	 * @return array command results
	 */
	public function renewCert(array $cmd_params = []): array
	{
		$result = SSLCertificate::getCertInfo();
		$state = ($result['error'] == 0);
		if ($state) {
			$params = $result['output']['subject'] ?? [];
			$params['days_no'] = $result['output']['days_no'] ?? null;
			$result = $this->createCert($params, $cmd_params);
		}
		return $result;
	}
}
