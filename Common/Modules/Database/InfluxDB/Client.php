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

namespace Bacularis\Common\Modules\Database\InfluxDB;

use Bacularis\Common\Modules\CommonModule;
use Bacularis\Common\Modules\Errors\GenericError;
use Bacularis\Common\Modules\ExecuteCommand;

/**
 * Common InfluxDB CLI connection helpers.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class Client extends CommonModule
{
	/**
	 * List all buckets in an explicitly selected organization using CLI JSON output.
	 * The CLI handles pagination. Bucket identities are validated before being returned.
	 *
	 * @param string $influx_path InfluxDB CLI binary path
	 * @param string $host server URL
	 * @param string $organization organization name
	 * @param string $token connection token
	 * @param bool $verify_tls verify the TLS certificate
	 * @param string $ca_cert_path optional CA certificate file
	 * @return array output, error status and buckets containing id and name
	 */
	public function listBuckets(string $influx_path, string $host, string $organization, string $token, bool $verify_tls = true, string $ca_cert_path = ''): array
	{
		$is_valid = $this->isValidConnection($host, $token, $ca_cert_path);
		if (!$is_valid || $organization === '') {
			$result = ExecuteCommand::prepareResult(
				['Invalid InfluxDB connection values, organization or CA certificate file.'],
				GenericError::ERROR_INVALID_COMMAND
			);
			$result['buckets'] = [];
			return $result;
		}
		$connection_args = $this->getConnectionArguments($host, $organization, $verify_tls);
		$command = array_merge(
			[$influx_path, 'bucket', 'list'],
			$connection_args,
			['--json', '--limit', '0']
		);
		$environment = $this->getConnectionEnvironment($token, $ca_cert_path);
		$result = ExecuteCommand::execProgram($command, $environment);
		$result['buckets'] = [];
		if ($result['error'] !== 0) {
			return $result;
		}
		$json = implode(PHP_EOL, $result['output']);
		$json = trim($json);
		$buckets = json_decode($json, true);
		// A nil Go slice is emitted as JSON null when no buckets are returned.
		if ($json === 'null') {
			return $result;
		}
		$is_valid = json_last_error() === JSON_ERROR_NONE && is_array($buckets) && substr($json, 0, 1) === '[';
		$ids = [];
		if ($is_valid) {
			foreach ($buckets as $bucket) {
				if (!is_array($bucket) || !isset($bucket['id'], $bucket['name']) || !is_string($bucket['id']) || !is_string($bucket['name']) || $bucket['name'] === '' || preg_match('/\A[0-9a-fA-F]{16}\z/D', $bucket['id']) !== 1) {
					$is_valid = false;
					break;
				}
				$id = strtolower($bucket['id']);
				if (key_exists($id, $ids)) {
					$is_valid = false;
					break;
				}
				$ids[$id] = true;
				$result['buckets'][] = ['id' => $id, 'name' => $bucket['name']];
			}
		}
		if (!$is_valid) {
			$result = ExecuteCommand::prepareResult(
				['Invalid InfluxDB bucket list response.'],
				GenericError::ERROR_INTERNAL_ERROR
			);
			$result['buckets'] = [];
		}
		return $result;
	}

	/**
	 * Resolve a binary in the configured directory, or use PATH if it is empty.
	 *
	 * @param string $binary_path directory containing the binaries
	 * @param string $binary binary name
	 * @return string binary path
	 */
	protected function getBinaryPath(string $binary_path, string $binary): string
	{
		return $binary_path !== '' ? rtrim($binary_path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $binary : $binary;
	}

	/**
	 * Get non-secret connection arguments as an argv list, without shell quoting.
	 *
	 * @param string $host server URL
	 * @param string $organization organization name, or empty for no organization filter
	 * @param bool $verify_tls verify the TLS certificate
	 * @return array CLI arguments
	 */
	protected function getConnectionArguments(string $host, string $organization, bool $verify_tls): array
	{
		// An empty TOML store prevents an unrelated active CLI profile being used.
		$args = [
			'--configs-path', '/dev/null',
			'--host', $host
		];
		if ($organization !== '') {
			$args[] = '--org';
			$args[] = $organization;
		}
		if (!$verify_tls) {
			$args[] = '--skip-verify';
		}
		return $args;
	}

	/**
	 * Get the temporary CLI environment without adding credentials to argv.
	 *
	 * @param string $token connection token
	 * @param string $ca_cert_path optional CA certificate file
	 * @return array environment for ExecuteCommand::execProgram()
	 */
	protected function getConnectionEnvironment(string $token, string $ca_cert_path): array
	{
		$environment = ['INFLUX_TOKEN' => $token];
		if ($ca_cert_path !== '') {
			// InfluxDB uses Go's system certificate pool. There is no Native CA flag.
			$environment['SSL_CERT_FILE'] = $ca_cert_path;
		}
		return $environment;
	}

	/**
	 * Check the local connection values without contacting the server.
	 *
	 * @param string $host server URL
	 * @param string $token connection token
	 * @param string $ca_cert_path optional CA certificate file
	 * @return bool true if the required values and optional CA file are available
	 */
	protected function isValidConnection(string $host, string $token, string $ca_cert_path): bool
	{
		return $host !== '' && $token !== '' && ($ca_cert_path === '' || (is_file($ca_cert_path) && is_readable($ca_cert_path)));
	}
}
