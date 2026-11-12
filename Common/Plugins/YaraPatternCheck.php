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

namespace Bacularis\Common\Plugins;

use Bacularis\Common\Modules\BacularisCommonPluginBase;
use Bacularis\Common\Modules\IBacularisVerificationCheckPlugin;
use Bacularis\Common\Modules\IBacularisVerificationConfigPlugin;
use Bacularis\Common\Modules\Plugins;
use Bacularis\Common\Modules\RestoreDestinationCapability;
use Bacularis\Common\Modules\Security\YARA\Command as YARACommand;
use Bacularis\Common\Modules\Security\YARA\Common as YARACommon;
use InvalidArgumentException;
use Throwable;

/**
 * YARA malware and pattern checker.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Plugin
 */
class YaraPatternCheck extends BacularisCommonPluginBase implements IBacularisVerificationCheckPlugin, IBacularisVerificationConfigPlugin
{
	/**
	 * Scanner settings category.
	 */
	private const CATEGORY_SCAN = 'Scanner settings';

	/**
	 * Result evaulation category.
	 */
	private const CATEGORY_EVALUATION = 'Result evaluation';

	/**
	 * Maximum log details.
	 */
	private const LOG_DETAIL_LIMIT = 20;

	/**
	 * Maximum log message bytes.
	 */
	private const LOG_MESSAGE_BYTES = 1024;

	/**
	 * Configuration staged for one check only.
	 * Results and diagnostics stay local.
	 */
	private static $checker_config = [];
	private static $config_error = '';

	/**
	 * Get the user-facing plugin name.
	 *
	 * @return string plugin name
	 */
	public static function getName(): string
	{
		return 'YARA Malware & Pattern Detection';
	}

	/**
	 * Get the plugin version.
	 *
	 * @return string plugin version
	 */
	public static function getVersion(): string
	{
		return '1.0.0';
	}

	/**
	 * Get the plugin type.
	 *
	 * @return string plugin type
	 */
	public static function getType(): string
	{
		return 'verification';
	}

	/**
	 * Define named plugin settings using the existing plugin form metadata.
	 * Required paths and numeric ranges are checked at execution as well.
	 *
	 * @return array plugin parameters
	 */
	public static function getParameters(): array
	{
		return [
			[
				'name' => 'yara-engine',
				'type' => 'array',
				'default' => YARACommon::TYPE_YARAX,
				'data' => [YARACommon::TYPE_YARAX => 'YARA-X', YARACommon::TYPE_YARA => 'Classic YARA'],
				'label' => 'YARA engine',
				'category' => [self::CATEGORY_SCAN]
			],
			[
				'name' => 'yara-bin-path',
				'type' => 'string',
				'default' => '/usr/bin',
				'label' => 'YARA bin path',
				'placeholder' => 'ex: /usr/bin',
				'category' => [self::CATEGORY_SCAN]
			],
			[
				'name' => 'yara-rules-path',
				'type' => 'string',
				'default' => '',
				'label' => 'YARA rules path (required)',
				'placeholder' => 'ex: /dir/with/rules',
				'category' => [self::CATEGORY_SCAN]
			],
			[
				'name' => 'use-compiled-yara-rules',
				'type' => 'boolean',
				'default' => false,
				'label' => 'Use compiled YARA rules',
				'category' => [self::CATEGORY_SCAN]
			],
			[
				'name' => 'yarax-config-path',
				'type' => 'string',
				'default' => '',
				'label' => 'YARA-X configuration file path (YARA-X only)',
				'placeholder' => 'ex: /user/defined/my-config.toml',
				'category' => [self::CATEGORY_SCAN]
			],
			[
				'name' => 'maximum-scan-duration',
				'type' => 'integer',
				'default' => YARACommon::DEFAULT_TIMEOUT,
				'label' => 'Maximum scan duration (seconds)',
				'category' => [self::CATEGORY_SCAN]
			],
			[
				'name' => 'warning-threshold',
				'type' => 'integer',
				'default' => 0,
				'label' => 'Warning threshold (0 disables)',
				'category' => [self::CATEGORY_EVALUATION]
			],
			[
				'name' => 'error-threshold',
				'type' => 'integer',
				'default' => 1,
				'label' => 'Error threshold',
				'category' => [self::CATEGORY_EVALUATION]
			],
			[
				'name' => 'treat-warning-as-error',
				'type' => 'boolean',
				'default' => true,
				'label' => 'Treat warning as error',
				'category' => [self::CATEGORY_EVALUATION]
			]
		];
	}

	/**
	 * Stage a parameter map or the standard named configuration envelope.
	 * Replaces all prior settings; check() consumes the staged configuration once.
	 *
	 * @param array $config checker configuration
	 */
	public static function setCheckerConfig(array $config): void
	{
		self::$checker_config = [];
		self::$config_error = '';
		if (key_exists('plugin', $config) && $config['plugin'] !== 'YaraPatternCheck') {
			self::$config_error = 'Configuration belongs to a different plugin.';
		} elseif (key_exists('parameters', $config)) {
			if (!is_array($config['parameters'])) {
				self::$config_error = 'Invalid checker parameters.';
			} else {
				self::$checker_config = $config['parameters'];
			}
		} else {
			self::$checker_config = $config;
		}
	}

	/**
	 * Scan through the common adapter and evaluate rule-target records, not files.
	 * A promoted warning keeps its WARNING label and returns result=false.
	 * File-access diagnostics never activate threshold warnings or promotion.
	 *
	 * @param string $operator unused; this checker has no operators
	 * @param mixed $current_value restored filesystem path
	 * @param mixed $expected_value unused; thresholds come from named settings
	 * @return array result boolean, current status string and empty expected value
	 */
	public static function check(string $operator, $current_value, $expected_value): array
	{
		$config = self::$checker_config;
		$config_error = self::$config_error;
		self::setCheckerConfig([]);
		try {
			if ($config_error !== '') {
				throw new InvalidArgumentException($config_error);
			}
			$config = self::validateConfig($config);
			if (!is_string($current_value) || $current_value === '') {
				throw new InvalidArgumentException('A restored file or directory path is required.');
			}
			$type = $config['yara-engine'];
			$config_path = $type === YARACommon::TYPE_YARAX ? $config['yarax-config-path'] : '';
			$engine = $type === YARACommon::TYPE_YARAX ? 'YARA-X' : 'Classic YARA';
			self::logMessage(Plugins::LOG_INFO, 'Selected engine: ' . $engine);
			$rules = self::quoteLogValue($config['yara-rules-path']);
			self::logMessage(Plugins::LOG_INFO, 'Rules path: ' . $rules);
			self::logMessage(
				Plugins::LOG_INFO,
				'Rules mode: ' . ($config['use-compiled-yara-rules'] ? 'precompiled' : 'source')
			);

			// Version lookup has a separate short budget; the scan receives its full budget.
			$version_timeout = min(YARACommon::DEFAULT_VERSION_TIMEOUT, $config['maximum-scan-duration']);
			$version = YARACommand::getVersion($type, $config['yara-bin-path'], $version_timeout, $config_path);
			if ($version['error'] !== 0 || !is_string($version['version'])) {
				self::logScanDetails($version);
				return self::executionFailure($version);
			}
			$version_text = self::quoteLogValue($version['version']);
			self::logMessage(Plugins::LOG_INFO, 'Engine version: ' . $version_text);
			self::logScanDetails($version);

			$scan = YARACommand::runScan(
				$type,
				$config['yara-bin-path'],
				$config['yara-rules-path'],
				$current_value,
				$config['use-compiled-yara-rules'],
				$config['maximum-scan-duration'],
				$config_path
			);
			self::logScanDetails($scan);
			if ($scan['error'] !== 0 || !is_int($scan['match_count']) || $scan['match_count'] < 0) {
				return self::executionFailure($scan);
			}
			$count = $scan['match_count'];
			$status = 'PASS';
			$success = true;
			if ($count >= $config['error-threshold']) {
				$status = 'FAIL';
				$success = false;
				$message = sprintf(
					'Error threshold reached: %d matches >= %d.',
					$count,
					$config['error-threshold']
				);
				self::logMessage(Plugins::LOG_ERROR, $message);
			} elseif ($config['warning-threshold'] > 0 && $count >= $config['warning-threshold']) {
				$status = 'WARNING';
				$success = !$config['treat-warning-as-error'];
				$message = sprintf(
					'Warning threshold reached: %d matches >= %d.',
					$count,
					$config['warning-threshold']
				);
				self::logMessage(Plugins::LOG_WARNING, $message);
				if (!$success) {
					self::logMessage(
						Plugins::LOG_ERROR,
						'Warning promoted to failure by Treat warning as error.'
					);
				}
			}
			$current = sprintf('%s (matches: %d)', $status, $count);
			return ['result' => $success, 'current' => $current, 'expected' => ''];
		} catch (Throwable $exception) {
			$message = $exception->getMessage();
			$message = self::quoteLogValue($message);
			self::logMessage(Plugins::LOG_ERROR, 'Unable to run verification: ' . $message);
			return [
				'result' => false,
				'current' => 'FAIL (matches: unavailable)',
				'expected' => ''
			];
		}
	}

	/**
	 * Apply fresh defaults and validate saved form values without lossy casts.
	 * Error threshold and scan duration must be positive. Warning threshold zero
	 * disables warnings; an enabled warning threshold must be below the error threshold.
	 *
	 * @param array $config supplied parameters
	 * @throws InvalidArgumentException for invalid or missing settings
	 * @return array validated parameters
	 */
	private static function validateConfig(array $config): array
	{
		$parameters = self::getParameters();
		$validated = [];
		foreach ($parameters as $parameter) {
			$name = $parameter['name'];
			$value = key_exists($name, $config) ? $config[$name] : $parameter['default'];
			if ($parameter['type'] === 'integer') {
				$minimum = $name === 'warning-threshold' ? 0 : 1;
				if (!is_int($value) && !is_string($value)) {
					throw new InvalidArgumentException('Invalid integer parameter: ' . $name);
				}
				$value = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $minimum]]);
				if ($value === false) {
					throw new InvalidArgumentException('Invalid integer parameter: ' . $name);
				}
			} elseif ($parameter['type'] === 'boolean') {
				if (!is_bool($value) && !is_int($value) && !is_string($value)) {
					throw new InvalidArgumentException('Invalid boolean parameter: ' . $name);
				}
				$value = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
				if ($value === null) {
					throw new InvalidArgumentException('Invalid boolean parameter: ' . $name);
				}
			} elseif (!is_string($value) || strpos($value, "\0") !== false) {
				throw new InvalidArgumentException('Invalid string parameter: ' . $name);
			}
			$validated[$name] = $value;
		}
		if (!in_array($validated['yara-engine'], [YARACommon::TYPE_YARA, YARACommon::TYPE_YARAX], true)) {
			throw new InvalidArgumentException('Unsupported YARA engine.');
		}
		if ($validated['yara-rules-path'] === '') {
			throw new InvalidArgumentException('YARA rules path is required.');
		}
		if ($validated['warning-threshold'] > 0 && $validated['warning-threshold'] >= $validated['error-threshold']) {
			throw new InvalidArgumentException('Warning threshold must be less than error threshold when enabled.');
		}
		return $validated;
	}

	/**
	 * Report an execution failure without printing raw scanner output or source.
	 *
	 * @param array $scan normalized adapter result
	 * @return array failed checker result with an unavailable match count
	 */
	private static function executionFailure(array $scan): array
	{
		$reason = self::quoteLogValue($scan['error_message']);
		$message = sprintf(
			'Scanner execution failed: %s (error: %d, exitcode: %s, signal: %s, timeout: %s, output limit: %s).',
			$reason,
			$scan['error'],
			$scan['exitcode'] ?? 'unavailable',
			$scan['signal'] ?? 'none',
			$scan['timed_out'] ? 'yes' : 'no',
			$scan['output_limited'] ? 'yes' : 'no'
		);
		self::logMessage(Plugins::LOG_ERROR, $message);
		return [
			'result' => false,
			'current' => 'FAIL (matches: unavailable)',
			'expected' => ''
		];
	}

	/**
	 * Bound displayed details independently of the adapter's complete match count.
	 * Summarize compiler and runtime warnings separately and display distinct errors.
	 * Compiler and execution diagnostics may contain source snippets, so only
	 * adapter-provided summaries are displayed. File I/O messages contain paths.
	 *
	 * @param array $scan normalized scan or version result
	 */
	private static function logScanDetails(array $scan): void
	{
		$matches = array_slice($scan['matches'], 0, self::LOG_DETAIL_LIMIT);
		foreach ($matches as $match) {
			$rule = $match['namespace'] . ':' . $match['identifier'];
			$rule = self::quoteLogValue($rule);
			$path = self::quoteLogValue($match['path']);
			self::logMessage(
				Plugins::LOG_INFO,
				'Matched rule ' . $rule . ' at ' . $path . ' (path encoding: ' . $match['path_encoding'] . ').'
			);
		}
		$omitted = count($scan['matches']) - count($matches);
		if ($omitted > 0) {
			self::logMessage(
				Plugins::LOG_INFO,
				$omitted . ' additional match records omitted from the log; evaluation uses the full count.'
			);
		}
		$warning_count = 0;
		$runtime_warning_count = 0;
		$results_may_be_incorrect = false;
		$affected_rules = [];
		$details = [];
		foreach ($scan['diagnostics'] as $diagnostic) {
			if ($diagnostic['type'] === 'warning') {
				$warning_count++;
			} elseif ($diagnostic['type'] === 'runtime_warning') {
				$runtime_warning_count++;
				if (!empty($diagnostic['results_may_be_incorrect'])) {
					$results_may_be_incorrect = true;
					if (isset($diagnostic['rule']) && count($affected_rules) < self::LOG_DETAIL_LIMIT) {
						$affected_rules[$diagnostic['rule']] = true;
					}
				}
			} else {
				// The runner/parsing reason is already printed with the final status.
				if ($diagnostic['message'] === $scan['error_message']) {
					continue;
				}
				if ($diagnostic['type'] === 'file_error') {
					$message = self::quoteLogValue($diagnostic['message']);
					$message = 'Recoverable file-access error: ' . $message;
					$category = Plugins::LOG_WARNING;
				} elseif (isset($diagnostic['summary']) && $diagnostic['summary'] !== '') {
					$message = self::quoteLogValue($diagnostic['summary']);
					$message = 'Scanner diagnostic: ' . $message;
					$category = Plugins::LOG_ERROR;
				} else {
					$message = 'Unrecognized scanner diagnostic (source/body omitted).';
					$category = Plugins::LOG_ERROR;
				}
				$details[$message] = $category;
			}
		}
		if ($warning_count > 0) {
			self::logMessage(
				Plugins::LOG_INFO,
				'Scanner compiler warnings: ' . $warning_count . ' (rule source omitted).'
			);
		}
		if ($runtime_warning_count > 0) {
			self::logMessage(
				Plugins::LOG_WARNING,
				'Scanner runtime warnings: ' . $runtime_warning_count . ' (repeated messages omitted).'
			);
		}
		if ($results_may_be_incorrect) {
			$message = 'Scanner warning: Some rule results may be inaccurate due to excessive string matches. Reported match counts may be incomplete.';
			if ($affected_rules) {
				$rules = array_keys($affected_rules);
				$rules = implode(', ', $rules);
				$rules = self::quoteLogValue($rules);
				$message .= ' Affected rules (up to ' . self::LOG_DETAIL_LIMIT . '): ' . $rules;
			}
			self::logMessage(Plugins::LOG_WARNING, $message);
		}
		$diagnostics = array_slice($details, 0, self::LOG_DETAIL_LIMIT, true);
		foreach ($diagnostics as $message => $category) {
			self::logMessage($category, $message);
		}
		$omitted = count($details) - count($diagnostics);
		if ($omitted > 0) {
			self::logMessage(
				Plugins::LOG_INFO,
				$omitted . ' additional scanner diagnostics omitted from the log.'
			);
		}
	}

	/**
	 * Quote filesystem and diagnostic values so control characters cannot add lines.
	 *
	 * @param string $value log value
	 * @return string bounded JSON-escaped representation
	 */
	private static function quoteLogValue(string $value): string
	{
		$value = substr($value, 0, self::LOG_MESSAGE_BYTES);
		$encoded = json_encode(
			$value,
			JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES
		);
		return $encoded === false ? '"unrepresentable value"' : $encoded;
	}

	/**
	 * Write one bounded diagnostic using the existing plugin logging mechanism.
	 *
	 * @param int $category plugin log severity
	 * @param string $message diagnostic message
	 */
	private static function logMessage(int $category, string $message): void
	{
		if (strlen($message) > self::LOG_MESSAGE_BYTES) {
			$message = substr(
				$message,
				0,
				self::LOG_MESSAGE_BYTES
			) . ' [truncated]';
		}
		Plugins::log($category, 'YaraPatternCheck: ' . $message);
	}

	/**
	 * Get the main verification attribute.
	 *
	 * @return string attribute label
	 */
	public static function getAttribute(): string
	{
		return 'YARA Malware & Pattern Detection';
	}

	/**
	 * Get operators; thresholds are configured in plugin settings.
	 *
	 * @return array empty operator list
	 */
	public static function getOperators(): array
	{
		return [];
	}

	/**
	 * Get selectable values; this checker uses named plugin settings.
	 *
	 * @return array empty value list
	 */
	public static function getValues(): array
	{
		return [];
	}

	/**
	 * Get supported restore destination capabilities.
	 *
	 * @return array filesystem check capability for files and directories
	 */
	public static function getCapabilities(): array
	{
		return [RestoreDestinationCapability::FILE_CHECK];
	}

	/**
	 * Get additional framework requirements.
	 *
	 * @return array empty requirement list
	 */
	public static function getRequirements(): array
	{
		return [];
	}
}
