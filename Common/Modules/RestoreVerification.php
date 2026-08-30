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

use Bacularis\Common\Modules\Protocol\HTTP\Client as HTTPClient;
use Prado\Prado;

/**
 * Restore verification module.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class RestoreVerification extends CommonModule
{
	/**
	 * Default values for web access connection.
	 */
	public const DEFAULT_WEB_ACCESS_PROTOCOL = 'http';
	public const DEFAULT_WEB_ACCESS_ADDRESS = '127.0.0.1';
	public const DEFAULT_WEB_ACCESS_PORT = '9097';
	public const DEFAULT_WEB_ALLOWED_IPS = '';


	/**
	 * Restore test plan file path.
	 */
	private const RESTORE_TEST_PLAN_FILE = 'Bacularis.Common.Working.RestoreTestPlan-%test_id';

	/**
	 * Maximum plan file size (in bytes)
	 */
	private const RESTORE_TEST_PLAN_MAX_SIZE = 1048576; // 1 MB

	/**
	 * Run restore test.
	 *
	 * @param string $token web access token
	 * @param string $protocol web access interface protocol (http|https)
	 * @param string $address web access interface address
	 * @param string $port web access interface port
	 * @param string $level job level letter
	 * @return bool true on success, otherwise false
	 */
	public static function runRestoreTest(string $token, string $protocol, string $address, string $port, string $level): bool
	{
		// Prepare script parameters
		$web_token = rawurlencode($token);

		// Send restore test request
		$url = "{$protocol}://{$address}:{$port}/web/access/{$web_token}";
		if ($level) {
			$level_short = Miscellaneous::getJobLevelShort($level);
			$url .= "?level={$level_short}";
		}
		$options = [
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_SSL_VERIFYPEER => false
		];
		$response = HTTPClient::get($url, [], $options);
		$success = false;
		if ($response['http_code'] == 200 && $response['error'] == 0) {
			$body = json_decode($response['output'], true);
			if (is_array($body)) {
				$success = ($body['error'] == 0);
			} else {
				Plugins::log(Plugins::LOG_ERROR, $response['output']);
			}
		} else {
			Plugins::log(Plugins::LOG_ERROR, $url);
		}

		if (!$success) {
			// Error - something went wrong
			$emsg = sprintf(
				'Error while starting restore test. URL: %s, HTTP Code: %d, Error: %d, Body: %s.',
				$url,
				$response['http_code'],
				$response['error'],
				htmlspecialchars($response['output'])
			);
			Plugins::log(
				Plugins::LOG_ERROR,
				$emsg
			);
		}
		return $success;
	}

	/**
	 * Save restore test plan.
	 *
	 * @param string $test_id restore test identifier
	 * @param string $plan plan JSON string
	 * @return bool true on success, otherwise false
	 */
	public static function saveRestoreTestPlan(string $test_id, string $plan): bool
	{
		$path = self::getPlanFileByTestId($test_id);
		return (file_put_contents($path, $plan, LOCK_EX) !== false);
	}

	/**
	 * Do plan validation before saving.
	 *
	 * @param string $test_id restore test identifier
	 * @param string $plan plan JSON string
	 * @return bool true plan valid, otherwise false
	 */
	public static function validatePlan(string $test_id, string $plan): bool
	{
		$valid = false;
		if (strlen($plan) > self::RESTORE_TEST_PLAN_MAX_SIZE) {
			$valid = false;
		} else {
			$content = json_decode($plan, true);
			if (is_array($content)) {
				$test_identifier = $content['test_id'] ?? null;
				$valid = ($test_id === $test_identifier);
			} else {
				$valid = false;
			}
		}
		return $valid;
	}

	/**
	 * Get test plan file path by restore test identifer.
	 *
	 * @param string $test_id restore test identifier
	 * @return string test plan file path
	 */
	private static function getPlanFileByTestId(string $test_id): string
	{
		$file = str_replace(
			'%test_id',
			$test_id,
			self::RESTORE_TEST_PLAN_FILE
		);
		return Prado::getPathOfNamespace($file, '.json');
	}

	/**
	 * Run restore test plan.
	 *
	 * @param string $test_id restore test identifer
	 * @return bool true on success, otherwise false
	 */
	public static function runTestPlan(string $test_id): bool
	{
		$plan = self::getTestPlan($test_id);
		if (!$plan) {
			Plugins::log(
				Plugins::LOG_ERROR,
				sprintf('No restore test plan found for test "%s".', $test_id)
			);
			return false;
		}
		$result = self::runPathTest($plan);
		if ($result) {
			self::removeTestPlan($test_id);
		}
		return $result;
	}

	/**
	 * Execute restore plan test suite, one by one.
	 *
	 * @param array $plan restore plan structure
	 * @return bool true if all tests passed, false if at least one test failed
	 */
	private static function runPathTest(array $plan): bool
	{
		Plugins::log(Plugins::LOG_INFO, sprintf('RUN RESTORE TEST ID: "%s"', $plan['test_id']));
		Plugins::log(Plugins::LOG_INFO, ' ');
		$success = true;
		$paths = $plan['paths'] ?? [];
		$destination = $plan['restore']['destination_name'] ?? '';
		$capabilities = $plan['restore']['destination_capabilities'] ?? [];
		foreach ($paths as $rule_set => $rule_set_paths) {
			Plugins::log(Plugins::LOG_INFO, sprintf('START CHECKERS RULE SET: "%s"', $rule_set));
			Plugins::log(Plugins::LOG_INFO, ' ');
			foreach ($rule_set_paths as $fpath => $tests) {
				for ($i = 0; $i < count($tests); $i++) {
					$checker = sprintf('\\Bacularis\\Common\\Plugins\\%s', $tests[$i]['checker']);

					// Check if destination supports checker capabilities
					$is_capable = true;
					$caps = $checker::getCapabilities();
					for ($j = 0; $j < count($caps); $j++) {
						if (!in_array($caps[$j], $capabilities)) {
							$is_capable = false;
							break;
						}
					}
					if (!$is_capable || !$caps) {
						Plugins::log(
							Plugins::LOG_WARNING,
							sprintf(
								'RESTORE DESTINATION "%s" DOES NOT SUPPORT "%s" CHECKER CAPABILITIES',
								$destination,
								$tests[$i]['checker']
							)
						);
						continue;
					}

					$path = self::prepareTestPath($plan, $fpath);
					$test_config = ['path' => $path, 'config' => $tests[$i]];
					// Run before each test check
					if (method_exists($checker, 'setUp')) {
						$checker::setUp($test_config);
					}

					// Run main test check
					$result = $checker::check(
						$tests[$i]['operator'],
						$path,
						$tests[$i]['value']
					);
					if (!$result['result']) {
						$success = false;
					}

					// Run after each test check
					if (method_exists($checker, 'tearDown')) {
						$checker::tearDown($test_config);
					}

					// Report test check result
					self::reportTestResult(
						$result,
						$tests[$i]['checker'],
						$tests[$i]['operator'],
						$fpath
					);
					Plugins::log(Plugins::LOG_INFO, ' ');
				}
			}
		}
		if ($success) {
			Plugins::log(Plugins::LOG_INFO, sprintf('All checks in "%s" restore test passed SUCCESSFULLY.', $plan['test_id']));
		} else {
			Plugins::log(Plugins::LOG_ERROR, sprintf('Test "%s" FAILED.', $plan['test_id']));
		}

		Plugins::log(Plugins::LOG_INFO, sprintf('Finishing "%s" restore test.', $plan['test_id']));
		return $success;
	}

	/**
	 * Prepare single test path to test.
	 * This mainly applies path mapping defined in the restore plan.
	 *
	 * @param array $plan test plan structure
	 * @param string $path single path to perform test
	 * @return string updated path ready to test
	 */
	private static function prepareTestPath(array $plan, string $path): string
	{
		$ppath = $path;
		foreach ($plan['restore']['path_mapping'] as $path_from => $path_to) {
			$ppath = preg_replace('!^' . $path_from . '!', $path_to, $ppath);
		}
		return $ppath;
	}

	/**
	 * Create single test report to restore job log.
	 *
	 * @param array $result single test result (true - test passed, false - test failed)
	 * @param string $checker rule set checker name used in test
	 * @param string $operator checker operator used in test
	 * @param string $path tested path
	 */
	private static function reportTestResult(array $result, string $checker, string $operator, string $path): void
	{
		$type = '';
		if ($result['result']) {
			$type = Plugins::LOG_INFO;
		} else {
			$type = Plugins::LOG_ERROR;
		}
		if (is_array($result['expected']) || is_object($result['expected'])) {
			$result['expected'] = json_encode($result['expected'], JSON_PRETTY_PRINT);
		}

		$line_pattern = '===> CHECKER: %s';
		$line = sprintf($line_pattern, $checker);
		Plugins::log($type, $line);

		$line_pattern = '       TEST: %s';
		$line = '';
		if ($result['result']) {
			$line = sprintf($line_pattern, 'PASSED');
		} else {
			$line = sprintf($line_pattern, 'FAILED');
		}
		Plugins::log($type, $line);

		$line_pattern = '       PATH: %s';
		$line = sprintf($line_pattern, $path);
		Plugins::log($type, $line);

		$line_pattern = '       OPERATOR: %s';
		$line = sprintf($line_pattern, $operator);
		Plugins::log($type, $line);

		$line_pattern = '       CURRENT: %s';
		$line = sprintf($line_pattern, $result['current']);
		Plugins::log($type, $line);

		$line_pattern = '       EXPECTED: %s';
		$line = sprintf($line_pattern, $result['expected']);
		Plugins::log($type, $line);
	}

	/**
	 * Get test plan by the restore test identifier.
	 *
	 * @param string $test_id restore test identifer
	 * @return null|array plan structure or null on error
	 */
	private static function getTestPlan(string $test_id): ?array
	{
		$plan = [];
		$path = self::getPlanFileByTestId($test_id);
		if (file_exists($path)) {
			$content = file_get_contents($path);
			if ($content) {
				$plan = json_decode($content, true);
			}
		}
		return $plan;
	}

	/**
	 * Remove test plan by the restore test identifier.
	 *
	 * @param string $test_id restore test identifer
	 * @return bool true on success, otherwise false
	 */
	private static function removeTestPlan(string $test_id): bool
	{
		$result = false;
		$path = self::getPlanFileByTestId($test_id);
		if (file_exists($path)) {
			$result = unlink($path);
		}
		return $result;
	}
}
