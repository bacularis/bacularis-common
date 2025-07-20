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

namespace Bacularis\Common\Modules\Protocol\HTTP;

use DateTime;
use Bacularis\Common\Modules\CommonModule;
use Bacularis\Common\Modules\Errors\ConnectionError;
use Bacularis\Common\Modules\Protocol\HTTP\Headers;
use Bacularis\Common\Modules\Logging;

/**
 * Generic HTTP client module.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class Client extends CommonModule
{
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
		return $ch;
	}

	/**
	 * Get common HTTP headers included to every request.
	 *
	 * @param array $heads user provided headers
	 * @return array common HTTP headers
	 */
	private static function getHeaders(array $heads = []): array
	{
		$pre_def_heads = [];
		$headers = array_merge($pre_def_heads, $heads);
		return $headers;
	}

	/**
	 * GET request method.
	 *
	 * @param string $url destination URL
	 * @param array $heads HTTP headers
	 * @return array response result
	 */
	public static function get(string $url, array $heads = []): array
	{
		$ch = self::getConnection();
		$headers = self::getHeaders($heads);
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
	 * @param array $heads HTTP headers
	 * @return array response result
	 */
	public static function post(string $url, string $body, array $heads = []): array
	{
		$ch = self::getConnection();
		$headers = self::getHeaders($heads);
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
	 * @param array $heads HTTP headers
	 * @return array response result
	 */
	public static function head(string $url, array $heads = []): array
	{
		$ch = self::getConnection();
		$headers = self::getHeaders($heads);
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
			$output .= sprintf(
				'Errno: %d, Error: %s, Output: %s',
				$errno,
				$error,
				var_export($output, true)
			);
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

		return [
			'output' => $output,
			'error' => $errno,
			'headers' => $headers
		];
	}
}
