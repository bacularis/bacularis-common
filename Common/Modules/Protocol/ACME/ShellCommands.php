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

use Bacularis\Common\Modules\Logging;
use Bacularis\Common\Modules\ShellCommandModule;

/**
 * ACME server common shell commands.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class ShellCommands extends ShellCommandModule
{
	/**
	 * Get command to create HTTP-01 challenge directory.
	 *
	 * @param string $dest_dir destination directory
	 * @param array $cmd_params command parameters
	 * @return array prepared command
	 */
	private static function getCreateChallengeHttp01Dir(string $dest_dir, array $cmd_params): array
	{
		// command
		$ret = [
			'mkdir',
			'-p',
			'"' . $dest_dir . '"'
		];
		static::setCommandParameters($ret, $cmd_params);
		return $ret;
	}

	/**
	 * Get command to create HTTP-01 challenge file.
	 *
	 * @param string $content file content
	 * @param string $dest_file destination file
	 * @param array $cmd_params command parameters
	 * @return array prepared command
	 */
	private static function getCreateChallengeHttp01File(string $content, string $dest_file, array $cmd_params): array
	{
		$ret = [
			'echo',
			'-n',
			'"' . $content . '"',
			'>',
			$dest_file
		];
		$cmd_params['use_shell'] = true; // force using shell
		static::setCommandParameters($ret, $cmd_params);
		return $ret;
	}

	/**
	 * Get directory/file paths for http-01 challenge.
	 *
	 * @param string $token token value
	 * @return string file/dir path
	 */
	private static function getChallengeHttp01Path(string $token = ''): string
	{
		$parts = [
			APPLICATION_WEBROOT,
			'.well-known',
			'acme-challenge'
		];
		if (!empty($token)) {
			$parts[] = $token;
		}
		$path = implode(
			DIRECTORY_SEPARATOR,
			$parts
		);
		return $path;
	}

	/**
	 * Create directory for the http-01 challenge.
	 *
	 * @param array $cmd_params command properties (use_sudo, user, password ...)
	 * @return array command result
	 */
	public function createChallengeHttp01Dir(array $cmd_params = []): array
	{
		$path = self::getChallengeHttp01Path();
		$cmd = self::getCreateChallengeHttp01Dir($path, $cmd_params);
		$result = $this->execCommand($cmd, $cmd_params);
		$state = ($result['error'] == 0);
		if (!$state) {
			$output = implode(PHP_EOL, $result['output']);
			$lmsg = "Error while preparing directories for http-01 challenge. ExitCode: {$result['exitcode']}, Error: {$output}.";
			Logging::log(
				Logging::CATEGORY_APPLICATION,
				$lmsg
			);
		}
		return $result;
	}

	/**
	 * Create file for the http-01 challenge.
	 *
	 * @param string $token token that is the file name
	 * @param string $content the challenge file content
	 * @param array $cmd_params command properties (use_sudo, user, password ...)
	 * @return array command result
	 */
	public function createChallengeHttp01File(string $token, string $content, array $cmd_params = []): array
	{
		$file = $this->getChallengeHttp01Path($token);
		$cmd = self::getCreateChallengeHttp01File(
			$content,
			$file,
			$cmd_params
		);
		$result = $this->execCommand($cmd, $cmd_params);
		$state = ($result['error'] == 0);
		if (!$state) {
			$output = implode(PHP_EOL, $result['output']);
			$lmsg = "Error while preparing http-01 challenge file. ExitCode: {$result['exitcode']}, Error: {$output}.";
			Logging::log(
				Logging::CATEGORY_EXTERNAL,
				$lmsg
			);
		}
		return $result;
	}
}
