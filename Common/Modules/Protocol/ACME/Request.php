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

use DateTime;
use Bacularis\Common\Modules\CommonModule;
use Bacularis\Common\Modules\Errors\ConnectionError;
use Bacularis\Common\Modules\Protocol\HTTP\Headers;
use Bacularis\Common\Modules\Logging;

/**
 * Request support to ACME server.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class Request extends CommonModule
{
	/**
	 * Special error codes.
	 */
	public const ERROR_REPEAT_REQUEST = -10;

	/**
	 * Maximum number of retrying request.
	 */
	public const MAX_RETRY_REQUEST = 10;

	/**
	 * Request statuses.
	 */
	public const STATUS_INVALID = 'invalid'; // certificate will not be issued
	public const STATUS_PENDING = 'pending'; // server does not believe that the client has fulfilled the requirements
	public const STATUS_READY = 'ready'; // server agrees that the requirements have been fulfilled
	public const STATUS_PROCESSING = 'processing'; // certificate is being issued
	public const STATUS_VALID = 'valid'; // server has issued the certificate

	/**
	 * Get a new connection.
	 *
	 * @return CurlHandle|resource cURL connection instance
	 */
	private static function getConnection()
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
		curl_setopt($ch, CURLINFO_HEADER_OUT, true);
		curl_setopt($ch, CURLOPT_HEADER, true);
		// TO REMOVE:
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		return $ch;
	}

	/**
	 * Get common HTTP headers included to every request.
	 *
	 * @return array common HTTP headers
	 */
	private static function getHeaders(): array
	{
		return [
			'Content-Type: application/jose+json'
		];
	}

	/**
	 * GET request method.
	 *
	 * @param string $url destination URL
	 * @return array response result
	 */
	public static function get(string $url): array
	{
		$ch = self::getConnection();
		$headers = self::getHeaders();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		$result = curl_exec($ch);
		$error = curl_error($ch);
		$errno = curl_errno($ch);
		$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		curl_close($ch);
		$head = substr($result, 0, $header_size);
		$body = substr($result, $header_size);
		$heads = Headers::parseAll($head);
		$ret = self::prepareResult(
			$url,
			$body,
			$error,
			$errno,
			$heads
		);
		return $ret;
	}

	/**
	 * POST request method.
	 *
	 * @param string $url destination URL
	 * @param string $body request body
	 * @return array response result
	 */
	public static function post(string $url, string $body): array
	{
		$ch = self::getConnection();
		$headers = self::getHeaders();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
		$result = curl_exec($ch);
		$error = curl_error($ch);
		$errno = curl_errno($ch);
		$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		curl_close($ch);
		$head = substr($result, 0, $header_size);
		$rbody = substr($result, $header_size);
		$heads = Headers::parseAll($head);
		Logging::log(
			Logging::CATEGORY_EXTERNAL,
			'REQUEST BODY ===> ' . $body . ' <==='
		);
		$ret = self::prepareResult(
			$url,
			$rbody,
			$error,
			$errno,
			$heads
		);
		return $ret;
	}

	/**
	 * HEAD request method.
	 *
	 * @param string $url destination URL
	 * @return array response result
	 */
	public static function head(string $url): array
	{
		$ch = self::getConnection();
		$headers = self::getHeaders();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_NOBODY, true);
		$result = curl_exec($ch);
		$error = curl_error($ch);
		$errno = curl_errno($ch);
		$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		curl_close($ch);
		$head = substr($result, 0, $header_size);
		$body = substr($result, $header_size);
		$heads = Headers::parseAll($head);
		$ret = self::prepareResult(
			$url,
			$body,
			$error,
			$errno,
			$heads
		);
		return $ret;
	}

	/**
	 * Prepare response results.
	 *
	 * @param string $url destination URL
	 * @param mixed $output response body (payload)
	 * @param string $error error message
	 * @param int $errno error number
	 * @param array $headers HTTP response headers
	 * @return array output, error and nonce
	 */
	private static function prepareResult(string $url, $output, string $error, int $errno, array $headers = []): array
	{
		if ($errno != 0) {
			$output = ConnectionError::MSG_ERROR_CONNECTION_TO_HOST_PROBLEM;
			$output .= sprintf('Errno: %d, Error: %s, Output: %s', $errno, $error, var_export($output, true));
			$error = ConnectionError::ERROR_CONNECTION_TO_HOST_PROBLEM;
		}

		Logging::log(
			Logging::CATEGORY_EXTERNAL,
			'REQUEST URL ===> ' . $url . ' <==='
		);

		Logging::log(
			Logging::CATEGORY_EXTERNAL,
			$headers
		);
		Logging::log(
			Logging::CATEGORY_EXTERNAL,
			$output
		);
		$nonce = $headers['replay-nonce'] ?? '';
		$retry_after = $headers['retry-after'] ?? '';
		$kid = $headers['location'] ?? '';
		$out = ($errno == 0 ? json_decode($output, true) : []);
		$type = '';
		if (is_array($out) && key_exists('type', $out) && key_exists('detail', $out) && key_exists('status', $out)) {
			// Detected error from ACME server
			$type_val = explode(':', $out['type']);
			$type = array_pop($type_val);
			if (in_array($type, ['badNonce'])) {
				$errno = self::ERROR_REPEAT_REQUEST; // repeat the request
				Logging::log(
					Logging::CATEGORY_EXTERNAL,
					'REPEAT REQUEST ===> ' . $url . ' <==='
				);
				sleep(1);
			} else {
				// other problems than badNonce are reported as errors
				$errno = $out['status'];
			}
		}
		if (!empty($retry_after)) {
			$secs = 0;
			if (is_numeric($retry_after)) {
				$secs = (int) $retry_after;
			} else {
				$f = date_create($retry_after)->getTimestamp();
				$t = date_create()->getTimestamp();
				$secs = $f - $t;
			}
			sleep($secs);
		}

		return [
			'nonce' => $nonce,
			'kid' => $kid,
			'output' => $out,
			'raw' => $output,
			'error' => $errno,
			'type' => $type
		];
	}

	/**
	 * Prepare request body.
	 *
	 * @param string $header base64url-encoded request header
	 * @param string $payload base64url-encoded request payload data
	 * @param string $signature JWT signature
	 */
	public static function prepareBody(string $header, string $payload, string $signature): string
	{
		$body = [
			'protected' => $header,
			'payload' => $payload,
			'signature' => $signature
		];
		return json_encode($body);
	}

	/**
	 * Resend request.
	 * It can happen if an error happen (ex. badNonce error)
	 *
	 * @param object $obj sending request object
	 * @param string $method sending request method
	 * @param array $params sending method parameters
	 * @param array $prev_ret previous request response
	 * @return array new repeated request response
	 */
	public static function resend($obj, string $method, array $params, array $prev_ret): array
	{
		[$props, $cmd_params] = $params;
		$props['resend'] = !key_exists('resend', $props) ? 1 : ++$props['resend'];
		if ($props['resend'] <= Request::MAX_RETRY_REQUEST) {
			// Request has to be resend with a new nonce
			$props['nonce'] = $prev_ret['nonce'];
			// Repeat request
			$ret = $obj->{$method}($props, $cmd_params);
		} else {
			// Max request count - stop repeating
			$ret = $prev_ret;
		}
		return $ret;
	}
}
