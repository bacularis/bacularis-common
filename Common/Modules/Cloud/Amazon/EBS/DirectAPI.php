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

namespace Bacularis\Common\Modules\Cloud\Amazon\EBS;

use Bacularis\Common\Modules\Cloud\Amazon\Region as AmazonRegion;
use Bacularis\Common\Modules\Cloud\Amazon\SigV4 as AmazonSigV4;
use Bacularis\Common\Modules\Logging;
use Bacularis\Common\Modules\Protocol\HTTP\Client as HTTPClient;
use Bacularis\Common\Modules\Protocol\HTTP\Codes as HTTPCodes;
use Prado\Prado;

/**
 * Generic AWS EBS Direct API Amazon module.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class DirectAPI
{
	/**
	 * HTTP request signer object.
	 */
	private static $request_signer;

	/**
	 * Maximum number of retries if error occurs.
	 */
	private const MAX_RETRY_ATTEMPS = 8;

	/**
	 * API exceptions that require retry the request.
	 */
	private const RETRYABLE_EXCEPTIONS = [
		'ThrottlingException',
		'RequestThrottleException',
		'ValidationException'
	];

	/**
	 * Get supported EBS Direct API endpoint.
	 *
	 * @param string $endpoint_type endpoint type (ipv4, ipv46, ...)
	 * @param string $region region used for EBS
	 * @return string endpoint address or empty string if endpoint type is not supported in given region
	 */
	public static function getEndpoint(string $endpoint_type, string $region): string
	{
		$region_props = AmazonRegion::getRegionDetails($region);
		$supported = false;
		if ($region_props && key_exists($endpoint_type, $region_props['endpoints']['ebs'])) {
			$supported = $region_props['endpoints']['ebs'][$endpoint_type];
		}
		$endpoint = '';
		if ($supported) {
			$endpoints = EBS::getEndpoints('addr_pattern');
			$endpoint = str_replace(
				'%region',
				$region,
				$endpoints[$endpoint_type]
			);
		}
		return $endpoint;
	}

	/**
	 * Send GET request to EBS Direct API.
	 *
	 * @param string $url request URL
	 * @param array $headers HTTP headers
	 * @param array $args request properties
	 * @param int $retries current number of retries.
	 * @throws DirectAPIException on error with reading response body or retry limit reached
	 * @return array response with output, error and HTTP code
	 */
	public static function getInternal(string $url, array $headers, array $args = [], int $retries = 1): array
	{
		$heads = self::$request_signer->sign(
			'GET',
			$url,
			$headers,
			''
		);
		$result = HTTPClient::get($url, $heads);

		// Check if response is valid
		if ($result['http_code'] != HTTPCodes::CODE_OK) {
			Logging::log(
				Logging::CATEGORY_EXTERNAL,
				"Wrong HTTP error code. Code: {$result['http_code']}, Body: '{$result['output']}'."
			);
		}
		if ($result['http_code'] == HTTPCodes::CODE_FORBIDDEN) {
			// Refresh credentials
			self::initHeaderSigner($args, true);
		}

		// Check if repeating request is required
		if (self::isRetryRequired($result['output'], $result['http_code'])) {
			if ($retries < self::MAX_RETRY_ATTEMPS) {
				// Re-send request
				$result = self::getInternal($url, $headers, $args, ++$retries);
			} else {
				$emsg = "EBS Direct API: Retry limit REACHED URL: {$url}, Limit: {$retries}.";
				Logging::log(
					Logging::CATEGORY_EXTERNAL,
					$emsg
				);
				throw new DirectAPIException($emsg, 1);
			}
		}

		// Check if response is completed
		if ($result['http_code'] == HTTPCodes::CODE_OK) {
			$result['output_parsed'] = [];
			$output = json_decode($result['output'], true);
			if (is_array($output)) {
				// Store valid parsed output
				$result['output_parsed'] = $output;

				// Check if subsequent requests are required
				if (!self::isResponseCompleted($output, $next_token)) {
					$result['next_token'] = $next_token;
				} else {
					$result['next_token'] = null;
				}
			} else {
				// Response body is not valid JSON string
				$emsg = "Checking response completed - Error while decoding response body. Body: '{$result['output']}'.";
				Logging::log(
					Logging::CATEGORY_EXTERNAL,
					$emsg
				);
				throw new DirectAPIException($emsg, 1);
				$result['next_token'] = null;
			}
		} else {
			$result['next_token'] = null;
		}
		return $result;
	}

	/**
	 * Send GET request to EBS Direct API.
	 *
	 * @param string $url request URL
	 * @param array $headers HTTP headers
	 * @param array $args request properties
	 * @return array parsed response body with output, error number and HTTP code
	 */
	public static function get(string $url, array $headers = [], array $args = []): array
	{
		self::initHeaderSigner($args);
		$all_result = [];
		$error = ['output' => '', 'error' => 0, 'http_code' => 0];
		do {
			$result = self::getInternal($url, $headers, $args);
			if ($result['http_code'] != HTTPCodes::CODE_OK) {
				// Request failed (after repeating) - reset results and end
				$all_result = [];
				$error['output'] = $result['output'];
				$error['error'] = $result['error'];
				$error['http_code'] = $result['http_code'];
				break;
			}

			// Collect results
			$all_result[] = $result['output_parsed'];

			// Prepare next query (if any)
			$next_token = $result['next_token'];
			if ($next_token) {
				$query = parse_url($url, PHP_URL_QUERY);
				parse_str($query, $params);
				$params['pageToken'] = $next_token;
				$new_query = http_build_query($params);
				$url = str_replace($query, $new_query, $url);
			}
		} while (!empty($next_token));

		return ['result' => $all_result, 'error' => $error];
	}

	/**
	 * Check HTTP response to detect if retrying is required.
	 *
	 * @param string $body HTTP response body
	 * @param int $http_code HTTP error code
	 * @return bool true if request retry is required, false otherwise
	 */
	public static function isRetryRequired(string $body, int $http_code): bool
	{
		$retry = false;
		if (HTTPCodes::isServerCode($http_code)) {
			// Server codes 5xx - repeat the request
			$retry = true;
		} elseif ($http_code == HTTPCodes::CODE_FORBIDDEN) {
			$retry = true;
		} elseif ($http_code == HTTPCodes::CODE_BAD_REQUEST) {
			$out = json_decode($body, true);
			if (is_array($out)) {
				$retry = key_exists('__type', $out) && in_array($out['__type'], self::RETRYABLE_EXCEPTIONS);
				$oute = var_export($out, true);
				Logging::log(
					Logging::CATEGORY_EXTERNAL,
					$oute
				);
			} else {
				// Response body is not valid JSON string
				Logging::log(
					Logging::CATEGORY_EXTERNAL,
					"Checking retry required - Error while decoding response body. Body: '{$body}'."
				);
			}
		}
		return $retry;
	}

	/**
	 * Check if next request is needed to get full output.
	 *
	 * @param array $response HTTP response result
	 * @param null|string $next_token next token reference
	 * @return bool true if response is completed, false otherwise
	 */
	private static function isResponseCompleted(array $response, ?string &$next_token): string
	{
		$next_token = key_exists('NextToken', $response) && $response['NextToken'] !== 'null' ? $response['NextToken'] : '';
		$completed = empty($next_token);
		return $completed;
	}

	/**
	 * Initialize HTTP header signer object.
	 *
	 * @param array $args AWS properties
	 * @param bool $force force initialize signer object
	 */
	private static function initHeaderSigner(array $args, bool $force = false): void
	{
		if (is_object(self::$request_signer) && !$force) {
			return;
		}
		$app = Prado::getApplication();
		$aws_cmd = $app->getModule('aws_command');
		$creds = $aws_cmd->getAccountCredentials($args['account']);
		if (!$creds) {
			return;
		}
		self::$request_signer = new AmazonSigV4(
			$creds['AccessKeyId'],
			$creds['SecretAccessKey'],
			$args['region'],
			'ebs',
			($creds['SessionToken'] ?? null)
		);
	}
}
