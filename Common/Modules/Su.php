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

use Bacularis\Common\Modules\Errors\GenericError;

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
	 *
	 * Form:
	 * %env_params %cmd %user_param %other_parameters
	 */
	private const SU_COMMAND_PATTERN = "%s %s %s %s";

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
		$misc = $this->getModule('misc');
		if ($user !== '' && !$misc->isValidSystemUsername($user)) {
			return [
				'output' => [GenericError::MSG_ERROR_INVALID_COMMAND],
				'output_id' => '',
				'exitcode' => GenericError::ERROR_INVALID_COMMAND,
				'error' => GenericError::ERROR_INVALID_COMMAND
			];
		}

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
				$expect->addAction('(.sudo. password for.*|.sudo: authenticate. [Pp]assword):', $password, self::SU_CASE_TIMEOUT);
			}
		}

		$out = $expect->exec($env_vars);
		$out = implode('', $out);
		$output = explode(PHP_EOL, $out);
		$exitcode = self::getExitCode($output);
		$error = 0;
		if ($exitcode != 0) {
			$emsg = "Error while running su command User: '{$user}', Command: '{$cmd['cmd']}', Output: '{$out}'";
			Logging::log(
				Logging::CATEGORY_EXECUTE,
				$emsg
			);
			$error = GenericError::ERROR_WRONG_EXITCODE;
		}
		return [
			'output' => $output,
			'output_id' => $cmd['output_id'],
			'exitcode' => $exitcode,
			'error' => $error
		];
	}

	/**
	 * Prepare command to execution.
	 *
	 * @param string $user username to log in
	 * @param array $params command parameters
	 * @param int $ptype command pattern type
	 * @return array full command details
	 */
	private function prepareCommand(string $user, array $params, int $ptype): array
	{
		// SU command parameters
		$opts = [];
		if (key_exists('command', $params) && !empty($params['command'])) {
			$command = $params['command'];
			/*
			 * ShellCommandModule adds one transport backslash for the former Tcl
			 * quoting behavior. Remove it only from its exact legacy sh -c wrapper;
			 * quoteExpectCommand() below now preserves the logical command verbatim.
			 */
			$is_legacy_shell_command = preg_match('/^LANG=C (?:[^"\r\n ]+ )*sh -c " .* "$/sD', $command) === 1;
			if ($is_legacy_shell_command) {
				$command = str_replace(['\\\"'], ['\\"'], $command);
			}
			$command = $this->quoteExpectCommand($command);
			$opts[] = '-c "' . $command . '"';
		}
		$options = implode(' ', $opts);

		// Main SU command
		$cp_cmd = $this->getCmdPattern($ptype);

		// User parameter
		$cuser = $user;
		if (!empty($user)) {
			$expect_user = $this->quoteExpectCommand($user);
			$cuser = ' -l "' . $expect_user . '"';
		}

		/**
		 * Set TERM=dumb before running 'su'.
		 *
		 * Newer Fedora/systemd releases load OSC 3008 (Operating System Command)
		 * shell integration from /etc/profile.d/80-systemd-osc-context.sh.
		 * When executed through Expect's pseudo-terminal, these OSC sequences are
		 * emitted together with command output and can interfere with parsing.
		 *
		 * Setting TERM=dumb disables OSC 3008 initialization while preserving the
		 * normal login environment provided by 'su -l'.
		 */
		$env_vars = 'env TERM=dumb';

		$cmd = sprintf(
			$cp_cmd,
			$env_vars,
			self::CMD,
			$cuser,
			$options
		);
		$expect_command = $this->prepareExpectCommand($cmd, null);
		return [
			'cmd' => $expect_command,
			'output_id' => ''
		];
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
	 * @return string expect command ready to run
	 */
	private function prepareExpectFgCommand(string $cmd): string
	{
		$expect_program = 'spawn ' . $cmd . '
set timeout ' . self::SU_COMMAND_TIMEOUT . '
set prompt "(.*)\[#%>:\$\]  $"
expect {
	-re "\[Pp\]assword:" {
		expect_user -re "(.*)\n"
		set pwd $expect_out(1,string)
		send "$pwd\r"
		puts "\r\n\r"
		sleep 0.3
		exp_continue
	}
	-re ".sudo. password for.*:" {
		expect_user -re "(.*)\n"
		set pwd $expect_out(1,string)
		send "$pwd\r"
		puts "\r\n\r"
		sleep 0.3
		exp_continue
	}
	-re ".sudo: authenticate. \[Pp\]assword:" {
		expect_user -re "(.*)\n"
		set pwd $expect_out(1,string)
		send "$pwd\r"
		puts "\r\n\r"
		sleep 0.3
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
exit';
		$expect_program_arg = escapeshellarg($expect_program);
		return 'expect -c ' . $expect_program_arg . ' || echo "
EXITCODE=1
===
"';
	}

	/**
	 * Quote a dynamic value embedded in an Expect/Tcl double-quoted word.
	 *
	 * @param string $value dynamic Expect/Tcl word value
	 * @return string value with Expect/Tcl substitutions disabled
	 */
	private function quoteExpectCommand(string $value): string
	{
		return str_replace(
			['\\', '"', '[', ']', '$'],
			['\\\\', '\\"', '\\[', '\\]', '\\$'],
			$value
		);
	}
}
