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
