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
	private const RESTORE_TEST_PLAN_EXT = '.json';

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
	 * Finalize restore verification through one-time Web Access.
	 *
	 * @param string $token web access token
	 * @param string $protocol web access interface protocol (http|https)
	 * @param string $address web access interface address
	 * @param string $port web access interface port
	 * @return bool true on success, otherwise false
	 */
	public static function finalizeRestoreTest(string $token, string $protocol, string $address, string $port): bool
	{
		if (!Miscellaneous::isValidWebAccessToken($token)) {
			Plugins::log(Plugins::LOG_ERROR, 'Invalid Restore Verification finalize token.');
			return false;
		}

		$web_token = rawurlencode($token);
		$url = "{$protocol}://{$address}:{$port}/web/access/{$web_token}";
		$options = [
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_SSL_VERIFYPEER => false
		];
		$response = HTTPClient::get($url, [], $options);
		$body = null;
		$success = false;
		if ($response['http_code'] == 200 && $response['error'] == 0) {
			$body = json_decode($response['output'], true);
			if (is_array($body) && key_exists('error', $body)) {
				$success = ($body['error'] == 0);
			}
		}

		if ($success) {
			$output = $body['message'] ?? '';
			if (is_array($output) || is_object($output)) {
				$output = Miscellaneous::json_value($output);
			} elseif (is_scalar($output)) {
				$output = (string) $output;
			} else {
				$output = '';
			}
			if ($output !== '') {
				fwrite(STDOUT, $output . PHP_EOL);
			}
		} else {
			$response_output = htmlspecialchars($response['output']);
			$emsg = sprintf(
				'Error while finalizing Restore Verification. HTTP Code: %d, Error: %d, Body: %s.',
				$response['http_code'],
				$response['error'],
				$response_output
			);
			Plugins::log(Plugins::LOG_ERROR, $emsg);
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
		if (!self::isValidTestId($test_id)) {
			return false;
		}
		$path = self::getPlanFileByTestId($test_id);
		$result = file_put_contents($path, $plan, LOCK_EX);
		$result = ($result === strlen($plan));
		if ($result) {
			$result = RestoreVerificationStatus::save(
				$test_id,
				RestoreVerificationStatus::STATE_READY
			);
			if (!$result) {
				self::removeTestPlan($test_id);
			}
		}
		return $result;
	}

	/**
	 * Validate restore test identifier.
	 *
	 * @param string $test_id restore test identifier
	 * @return bool true if identifier is valid, otherwise false
	 */
	public static function isValidTestId(string $test_id): bool
	{
		return (preg_match('/^rt-[a-zA-Z0-9]{32}$/D', $test_id) === 1);
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
		if (!self::isValidTestId($test_id) || strlen($plan) > self::RESTORE_TEST_PLAN_MAX_SIZE) {
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
		return Prado::getPathOfNamespace($file, self::RESTORE_TEST_PLAN_EXT);
	}

	/**
	 * Run restore test plan.
	 *
	 * @param string $test_id restore test identifer
	 * @return bool true on success, otherwise false
	 */
	public static function runTestPlan(string $test_id): bool
	{
		if (!self::isValidTestId($test_id)) {
			$emsg = sprintf('Invalid restore test identifier "%s".', $test_id);
			Plugins::log(
				Plugins::LOG_ERROR,
				$emsg
			);
			return false;
		}
		$plan = self::getTestPlan($test_id);
		if (!$plan) {
			Plugins::log(
				Plugins::LOG_ERROR,
				sprintf('No restore test plan found for test "%s".', $test_id)
			);
			return false;
		}
		$status = RestoreVerificationStatus::save(
			$test_id,
			RestoreVerificationStatus::STATE_RUNNING
		);
		if (!$status) {
			$emsg = sprintf('Test "%s" ERROR. Unable to save running status.', $test_id);
			Plugins::log(
				Plugins::LOG_ERROR,
				$emsg
			);
			return false;
		}
		$result = self::runPathTest($plan);

		// Clean up test plan (without impact on result)
		self::removeTestPlan($test_id);

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
		$history = $plan['history'] ?? [];
		$result_state = [];
		foreach ($paths as $rule_set => $rule_set_paths) {
			Plugins::log(Plugins::LOG_INFO, sprintf('START CHECKERS RULE SET: "%s"', $rule_set));
			Plugins::log(Plugins::LOG_INFO, ' ');
			foreach ($rule_set_paths as $fpath => $tests) {
				for ($i = 0; $i < count($tests); $i++) {
					$checker = sprintf('\\Bacularis\\Common\\Plugins\\%s', $tests[$i]['checker']);
					$is_data_checker = is_subclass_of($checker, IBacularisVerificationDataPlugin::class);

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

					// Pass historical data to data checker
					$config_hash = '';
					if ($is_data_checker) {
						$checker_config_name = $tests[$i]['checker_config_name'] ?? '';
						$checker_config = $tests[$i]['checker_config'] ?? [];

						$checker::setCheckerConfig($checker_config);

						$config_hash = self::getCheckerConfigHash($checker, $checker_config_name);
						if ($config_hash === '') {
							$success = false;
							continue;
						}
						$checker_history = self::getCheckerHistory(
							$history,
							$fpath,
							$tests[$i]['checker'],
							$config_hash
						);
						$checker::setHistory($checker_history);
					}

					$path = self::prepareTestPath($plan, $fpath);
					$test_config = ['path' => $path, 'config' => $tests[$i]];
					// Run before each test check
					if (method_exists($checker, 'setUp')) {
						$checker::setUp($test_config);
					}

					// Run main test check
					$result = $checker::check(
						($tests[$i]['operator'] ?? ''),
						$path,
						($tests[$i]['value'] ?? '')
					);
					if (!$result['result']) {
						$success = false;
					}

					// Add current plugin state
					if ($is_data_checker) {
						$state = $checker::getState($result);

						self::getCheckerState(
							$result_state,
							$plan,
							$fpath,
							$tests[$i]['checker'],
							$config_hash,
							$state
						);
					}

					// Run after each test check
					if (method_exists($checker, 'tearDown')) {
						$checker::tearDown($test_config);
					}

					// Report test check result
					self::reportTestResult(
						$result,
						$tests[$i]['checker'],
						($tests[$i]['operator'] ?? ''),
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

		// Save results
		$result = RestoreVerificationResult::save($plan['test_id'], $result_state);
		if (!$result) {
			$success = false;
			$emsg = sprintf('Test "%s" ERROR. Unable to write result file.', $plan['test_id']);
			Plugins::log(Plugins::LOG_ERROR, $emsg);
		} else {
			$result = RestoreVerificationStatus::save(
				$plan['test_id'],
				RestoreVerificationStatus::STATE_DONE
			);
			if (!$result) {
				$success = false;
				$emsg = sprintf('Test "%s" ERROR. Unable to save done status.', $plan['test_id']);
				Plugins::log(Plugins::LOG_ERROR, $emsg);
			}
		}

		Plugins::log(Plugins::LOG_INFO, sprintf('Finishing "%s" restore test.', $plan['test_id']));
		return $success;
	}


	/**
	 * Get single checker history items.
	 *
	 * @param array $history all test history
	 * @param string $fpath file/directory path
	 * @param string $checker checker name
	 * @param string $config_hash configuration hash
	 */
	private static function getCheckerHistory(array $history, string $fpath, string $checker, string $config_hash): array
	{
	 	return $history[$fpath][$checker][$config_hash] ?? [];
	}

	/**
	 * Get single checker state for current checker execution.
	 *
	 * @param array $result_state result state container
	 * @param array $plan restore plan metadata
	 * @param string $path current path examined by checker
	 * @param string $checker checker name
	 * @param string $config_hash current checker config hash
	 * @param array $state current single checker result
	 */
	private static function getCheckerState(array &$result_state, array $plan, string $path, string $checker, string $config_hash, array $state): void
	{
		if (!$state) {
			// No checker state to save
			return;
		}
		$test_name = $plan['test_name'];
		if (!key_exists($test_name, $result_state)) {
			$result_state[$test_name] = [];
		}
		if (!key_exists($path, $result_state[$test_name])) {
			$result_state[$test_name][$path] = [];
		}
		if (!key_exists($checker, $result_state[$test_name][$path])) {
			$result_state[$test_name][$path][$checker] = [];
		}
		if (!key_exists($config_hash, $result_state[$test_name][$path][$checker])) {
			$result_state[$test_name][$path][$checker][$config_hash] = [];
		}
		$result_state[$test_name][$path][$checker][$config_hash][] = $state;
	}

	/**
	 * Get current checker configuration hash.
	 *
	 * @param string $checker checker class name
	 * @param array $config checker configuration
	 * @return string checker configuration hash or empty string on error
	 */
	public static function getCheckerConfigHash(string $checker, string $config_name = ''): string
	{
		$hist_config = $checker::getHistoryConfig();

		$hash_data = [
			'config_name' => $config_name,
			'history' => $hist_config
		];

		Miscellaneous::sortArrayRecursive($hash_data);

		$json = json_encode(
			$hash_data,
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);

		if ($json === false) {
			return '';
		}

		return hash('sha256', $json);
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

		$checker = sprintf('\\Bacularis\\Common\\Plugins\\%s', $checker);
		$operators = $checker::getOperators();

		if ($operators) {
			$line_pattern = '       EXPECTED: %s';
			$line = sprintf($line_pattern, $result['expected']);
			Plugins::log($type, $line);
		}
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
		if (!$result) {
			$emsg = sprintf('Test "%s" ERROR. Unable to remove test plan file.', $test_id);
			Plugins::log(Plugins::LOG_ERROR, $emsg);
		}
		return $result;
	}
}
