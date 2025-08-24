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

namespace Bacularis\Common\Modules;

/**
 * Base module class for common plugins.
 * Every base plugin class should extend it.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
abstract class BacularisCommonPluginBase extends CommonModule
{
	/**
	 * Stores single plugin configuration.
	 */
	private $config = [];

	/**
	 * Encode/decode charaters.
	 * Some plugin parameters need to be encoded into entities.
	 */
	private const ENCODE_PARAM_CHARS = [' ', ':'];
	private const DECODE_PARAM_CHARS = ['\\x20', '\\x3A'];

	public function __construct(array $config = [])
	{
		$this->setConfig($config);
	}

	/**
	 * Get plugin configuration.
	 *
	 * @return array plugin configuration or empty array inf configuration does not exist
	 */
	protected function getConfig(): array
	{
		return $this->config;
	}

	/**
	 * Set plugin configuration.
	 *
	 * @param array $config plugin configuration
	 */
	protected function setConfig(array $config): void
	{
		$this->config = $config;
	}

	/**
	 * Prepare parameters to use in plugin commands.
	 *
	 * @param array $params parameters to prepare
	 * @param bool $encode if true, decode the parameters
	 * @return array prepared parameters
	 */
	public function prepareCommandParameters(array $params, bool $encode = false): array
	{
		$params_encode = fn ($param) => str_replace(self::ENCODE_PARAM_CHARS, self::DECODE_PARAM_CHARS, $param);
		$cmd = [];
		foreach ($params as $key => $value) {
			if ($value === true) {
				$cmd[] = "--{$key}";
			} elseif ($value === null) {
				$cmd[] = $key;
			} else {
				if (is_array($value)) {
					for ($i = 0; $i < count($value); $i++) {
						if ($encode) {
							$value[$i] = $params_encode($value[$i]);
						}
						$cmd[] = "--{$key}=\"{$value[$i]}\"";
					}
				} else {
					if ($encode) {
						$value = $params_encode($value);
					}
					$cmd[] = "--{$key}=\"$value\"";
				}
			}
		}
		return $cmd;
	}

	/**
	 * Script single parameter parser.
	 *
	 * @param string $param parameter string
	 * @return array parsed key/value result or empty array if parameter is invalid
	 */
	protected static function parseCommandParameter(string $param): array
	{
		$params_decode = fn ($param) => str_replace(self::DECODE_PARAM_CHARS, self::ENCODE_PARAM_CHARS, $param);
		$values = [];
		$is_param = (preg_match('/^--(?P<key>[^=]+)(="?(?<value>[^"]*)"?)?$/', $param, $match) === 1);
		if ($is_param) {
			$values[$match['key']] = true;
			if (key_exists('value', $match)) {
				$values[$match['key']] = $params_decode($match['value']);
			}
		}
		return $values;
	}

	/**
	 * Parse multiple command parameters.
	 *
	 * @param array $params parameters to parse
	 * @return array parsed parameters in key/value form.
	 */
	public static function parseCommandParameters(array $params): array
	{
		$values = [];
		for ($i = 0; $i < count($params); $i++) {
			$value = self::parseCommandParameter($params[$i]);
			$values = array_merge_recursive($values, $value);
		}
		return $values;
	}

	/**
	 * Command execution method.
	 * Used to executed any script or program in plugin part.
	 *
	 * @param array $command command with parameters
	 * @param bool $passthru if true, it writes results on stdout instead returning them
	 * @return array result with command output and exit code
	 */
	protected function execCommand(array $command, $passthru = false): array
	{
		$misc = $this->getModule('misc');
		$cmd = implode(' ', $command);
		$output = [];
		if ($passthru) {
			passthru($cmd, $exitcode);
		} else {
			exec($cmd, $output, $exitcode);
		}
		if ($exitcode != 0) {
			$params_mask = $misc->maskPasswordParams($command);
			$cmd_mask = implode(' ', $params_mask);
			$errstr = implode(PHP_EOL, $output);
			Plugins::log(Plugins::LOG_ERROR, "Error while executing '{$cmd_mask}' command. Error: $exitcode, Output: {$errstr}.");
		}
		return [
			'output' => $output,
			'exitcode' => $exitcode
		];
	}

	/**
	 * Get single plugin command.
	 *
	 * @param string $action command action (ex. command/backup)
	 * @param array $params command parameters
	 * @param bool $runs_on_client if true, command is prepared to run on the FD side, otherwise it is executed on the Director side
	 * @param bool $encode_params if true, encode parameters
	 * @return array command ready to execute
	 */
	public function getPluginCommand(string $action, array $params, bool $runs_on_client = false, bool $encode_params = true): array
	{
		$path = [
			APPLICATION_PROTECTED,
			'Common',
			'Bin',
			'plugin'
		];
		$cparams = $this->prepareCommandParameters($params, $encode_params);
		$cmd = [];
		$cmd[] = ($runs_on_client ? '\\\\' : '') . implode(DIRECTORY_SEPARATOR, $path);
		$cmd[] = $action;
		$cmd = array_merge($cmd, $cparams);
		return $cmd;
	}

	/**
	 * Filter command parameters by given category.
	 * By default there are also added plugin internal parameters (@see $extra).
	 *
	 * @param array $args command arguments
	 * @param array $category command category (general, backup, restore ...etc.)
	 * @return array filtered command parameters
	 */
	protected function filterParametersByCategory(array $args, array $category): array
	{
		$result = [
			'plugin-name' => $args['plugin-name']
		];
		$first = '';
		$params = $this->getParameters();
		for ($i = 0; $i < count($params); $i++) {
			if (!key_exists($params[$i]['name'], $args)) {
				// argument does not exist, check if it has default value
				if (!isset($params[$i]['default']) || in_array($params[$i]['default'], ['', false])) {
					continue;
				}
				$args[$params[$i]['name']] = $params[$i]['default'];
			}
			if (key_exists('first', $params[$i]) && $params[$i]['first']) {
				$first = $params[$i]['name'];
			}
			for ($j = 0; $j < count($category); $j++) {
				if (in_array($category[$j], $params[$i]['category'])) {
					$result[$params[$i]['name']] = $args[$params[$i]['name']];
				}
			}
		}
		if (key_exists($first, $result)) {
			$result = array_merge([$first => $result[$first]], $result);
		}
		return $result;
	}

	/**
	 * Get plugin configuration parameters.
	 *
	 * return array plugin parameters
	 */
	public static function getParameters(): array
	{
		// this method is overriden by every module. NOTE: It cannot be abstract
		return [];
	}
}
