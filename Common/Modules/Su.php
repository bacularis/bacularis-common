<?php
/*
 * Bacularis - Bacula web interface
 *
 * Copyright (C) 2021-2024 Marcin Haba
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
 * SU command module.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Common
 */
class Su extends CommonModule
{
	/**
	 * Base command to run.
	 */
	private const CMD = 'su';

	/**
	 * Pattern types used to prepare command.
	 */
	public const PTYPE_REG_CMD = 0;

	/**
	 * SU command patterns.
	 */
	private const SU_COMMAND_PATTERN = "%s - \"%s\" %s";

	/**
	 * SU command timeout in seconds.
	 */
	private const SU_COMMAND_TIMEOUT = 1200;

	/**
	 * Single expect case timeout in seconds.
	 */
	private const SU_CASE_TIMEOUT = 20;

	/**
	 * Execute command.
	 *
	 * @param string $user username to log in
	 * @param string $password password to log in
	 * @param array $params SU command parameters
	 * @param int $ptype command pattern type
	 * @param array $env_vars environment variables
	 * @return array command output
	 */
	public function execCommand(string $user, string $password, array $params, int $ptype = self::PTYPE_REG_CMD, $env_vars = []): array
	{
		$cmd = $this->prepareCommand(
			$user,
			$params,
			$ptype
		);
		$expect = $this->getModule('expect');
		$expect->setCommand($cmd['cmd']);
		if ($ptype === self::PTYPE_REG_CMD) {
			$expect->addAction('Password:$', $password, self::SU_CASE_TIMEOUT);
			if (key_exists('use_sudo', $params) && $params['use_sudo'] === true) {
				$expect->addAction('.sudo. password for.*:', $password, self::SU_CASE_TIMEOUT);
			}
		}

		$out = $expect->exec($env_vars);
		$out = implode('', $out);
		$output = explode(PHP_EOL, $out);
		$exitcode = self::getExitCode($output);
		if ($exitcode != 0) {
			$emsg = "Error while running su command User: '{$user}', Command: '{$cmd['cmd']}', Output: '{$out}'";
			Logging::log(
				Logging::CATEGORY_EXECUTE,
				$emsg
			);
		}
		return [
			'output' => $output,
			'output_id' => $cmd['output_id'],
			'exitcode' => $exitcode
		];
	}

	/**
	 * Prepare command to execution.
	 *
	 * @param string $user username to log in
	 * @param array $params command parameters
	 * @param int $ptype command pattern type
	 * @return string full command string
	 */
	private function prepareCommand(string $user, array $params, int $ptype)
	{
		// SU command parameters
		$opts = [];
		if (key_exists('command', $params) && !empty($params['command'])) {
			$opts[] = '-c "' . str_replace('"', '\\"', $params['command']) . '"';
		}
		$options = implode(' ', $opts);

		// Main SU command
		$cp_cmd = $this->getCmdPattern($ptype);

		$cmd = sprintf(
			$cp_cmd,
			self::CMD,
			$user,
			$options
		);
		return [
			'cmd' => $this->prepareExpectCommand($cmd, null),
			'output_id' => ''
		];
		return $cmd;
	}

	/**
	 * Get command pattern by pattern type.
	 * So far support is only foreground command regular pattern.
	 *
	 * @param int $ptype command pattern type
	 * @return string command pattern
	 */
	private function getCmdPattern(int $ptype): string
	{
		$pattern = null;
		switch ($ptype) {
			case self::PTYPE_REG_CMD: $pattern = self::SU_COMMAND_PATTERN;
				break;
			default: $pattern = self::SU_COMMAND_PATTERN;
				break;
		}
		return $pattern;
	}

	/**
	 * Get remote command exit code basing on command output.
	 * In output there is provided EXITCODE=XX string with real exit code.
	 * If exitcode not found, default exit code is -1.
	 *
	 * @param array command output
	 * @param array $output
	 * @return int command exit code
	 */
	private static function getExitCode(array $output)
	{
		$exitcode = -1; // -1 means that process is pending
		$output_count = count($output);
		if ($output_count > 1 && preg_match('/^EXITCODE=(?P<exitcode>\d+)$/i', $output[$output_count - 2], $match) === 1) {
			$exitcode = (int) $match['exitcode'];
		}
		return $exitcode;
	}

	/**
	 * Prepare command to execution via expect.
	 *
	 * @param string $cmd command to spawn
	 * @param string $file file path to put output (only for background commands)
	 * @return string command to execute
	 */
	private function prepareExpectCommand($cmd, $file)
	{
		$command = '';
		if (!empty($file)) {
			$command = $this->prepareExpectBgCommand($cmd, $file);
		} else {
			$command = $this->prepareExpectFgCommand($cmd);
		}
		return $command;
	}

	/**
	 * Use foreground expect prepare SU command to spawn.
	 *
	 * @param string $cmd SU command
	 * @param string $file file for writing output
	 * @return string expect command ready to run
	 */
	private function prepareExpectFgCommand($cmd)
	{
		return 'expect -c \'spawn ' . $this->quoteExpectCommand($cmd) . '
set timeout ' . self::SU_COMMAND_TIMEOUT . '
set prompt "(.*)\[#%>:\$\]  $"
expect {
	-re "\[Pp\]assword:" {
		expect_user -re "(.*)\n"
		set pwd $expect_out(1,string)
		send "$pwd\r"
		exp_continue
	}
	-re ".sudo. password for.*:" {
		expect_user -re "(.*)\n"
		set pwd $expect_out(1,string)
		send "$pwd\r"
		exp_continue
	}
	-re "$prompt" {
		puts "Prompt -> exit"
	}
	timeout {
		puts "Timeout occurred -> exit"
	}
	eof {
		puts ""
	}
}
lassign [wait] pid spawnid os_error_flag value
puts "\nEXITCODE=$value"
puts "quit"
exit\' || echo "
EXITCODE=1
=== 
"';
	}

	/**
	 * Quote special characters in expect spawn command.
	 *
	 * @param string spawn expect command
	 * @param mixed $cmd
	 * @return string spawn expect command with escaped special characters
	 */
	private function quoteExpectCommand($cmd)
	{
		return str_replace(
			['[', ']'],
			['\\[', '\\]'],
			$cmd
		);
	}
}
