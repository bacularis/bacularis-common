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

namespace Bacularis\Common\Modules\Cloud\Amazon;

/**
 * AWS SigV4 signature module.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class SigV4
{
	/**
	 * AWS account access key.
	 */
	private $access_key;

	/**
	 * AWS account secret key.
	 */
	private $secret_key;

	/**
	 * AWS account session token.
	 * This token is used only for STS assumed-role.
	 */
	private $session_token;

	/**
	 * AWS region name.
	 */
	private $region;

	/**
	 * AWS service short name ('ebs', 'ec2' ...etc.)
	 */
	private $service;

	public function __construct(string $access_key, string $secret_key, string $region, string $service, ?string $session_token = null)
	{
		$this->access_key = $access_key;
		$this->secret_key = $secret_key;
		$this->region = $region;
		$this->service = $service;
		$this->session_token = $session_token;
	}

	/**
	 * Sign request and return HTTP headers.
	 *
	 * @param string $method HTTP request method
	 * @param string $url HTTP request URL
	 * @param array $headers additional headers to add to request
	 * @param string $body request body
	 * @return array HTTP header list with signature
	 */
	public function sign(string $method, string $url, array $headers = [], string $body = ''): array
	{
		$parsed_url = parse_url($url);

		$host = $parsed_url['host'];
		$uri = $parsed_url['path'] ?? '/';
		$query = $parsed_url['query'] ?? '';

		// Set dates
		$amz_date = gmdate('Ymd\THis\Z');
		$date_stamp = gmdate('Ymd');

		// Prepare payload hash
		$payload_hash = hash('sha256', $body);

		// Add requred headers
		$headers = array_change_key_case($headers, CASE_LOWER);

		$headers['host'] = $host;
		$headers['x-amz-date'] = $amz_date;
		$headers['x-amz-content-sha256'] = $payload_hash;

		// If STS assumed role is used, add session token
		if ($this->session_token) {
			$headers['x-amz-security-token'] = $this->session_token;
		}

		// Sort canonical headers
		ksort($headers);

		$canonical_headers = '';
		foreach ($headers as $key => $value) {
			$canonical_headers .= strtolower($key) . ':' . trim($value) . "\n";
		}

		// Signed headers
		$signed_headers = implode(';', array_keys($headers));

		// Canonical query string (sort + encode)
		$canonical_query = $this->buildCanonicalQueryString($query);

		// Canonical request
		$request_params = [
			strtoupper($method),
			$uri,
			$canonical_query,
			$canonical_headers,
			$signed_headers,
			$payload_hash
		];
		$canonical_request = implode("\n", $request_params);

		// Prepare string to sign
		$algorithm = 'AWS4-HMAC-SHA256';
		$credential_scope = "{$date_stamp}/{$this->region}/{$this->service}/aws4_request";
		$string_params = [
			$algorithm,
			$amz_date,
			$credential_scope,
			hash('sha256', $canonical_request)
		];
		$string_to_sign = implode("\n", $string_params);

		// Signing key
		$signing_key = $this->getSigningKey($date_stamp);

		// Signature
		$signature = hash_hmac('sha256', $string_to_sign, $signing_key);

		// Authorization header
		$authorization_header = $algorithm . ' ' .
			"Credential={$this->access_key}/{$credential_scope}, " .
			"SignedHeaders={$signed_headers}, " .
			"Signature={$signature}";

		$headers['authorization'] = $authorization_header;
		$heads = [];
		foreach ($headers as $hname => $hval) {
			$heads[] = "{$hname}: {$hval}";
		}
		return $heads;
	}

	/**
	 * Build canonical query string.
	 *
	 * @param string $query query string
	 * @return string canonical query string
	 */
	private function buildCanonicalQueryString(string $query): string
	{
		if (empty($query)) {
			return '';
		}

		parse_str($query, $params);
		ksort($params);
		$encoded = [];
		foreach ($params as $key => $value) {
			$encoded[] = rawurlencode($key) . '=' . rawurlencode($value);
		}
		return implode('&', $encoded);
	}

	/**
	 * Sign message with key.
	 * The signature is computed using HMAC method.
	 *
	 * @param string $key signing key
	 * @param string $msg message to sign
	 * @return string signature
	 */
	private function signKey(string $key, string $msg): string
	{
		return hash_hmac('sha256', $msg, $key, true);
	}

	/**
	 * Get SigV4 signing key.
	 *
	 * @param string $date_stamp current date stamp
	 * @return string SigV4 signing key
	 */
	private function getSigningKey(string $date_stamp): string
	{
		$k_date = $this->signKey("AWS4" . $this->secret_key, $date_stamp);
		$k_region = $this->signKey($k_date, $this->region);
		$k_service = $this->signKey($k_region, $this->service);
		return $this->signKey($k_service, 'aws4_request');
	}
}
