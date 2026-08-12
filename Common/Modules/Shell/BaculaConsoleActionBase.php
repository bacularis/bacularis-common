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

namespace Bacularis\Common\Modules\Shell;

use Bacularis\Common\Modules\Logging;
use Bacularis\Common\Modules\Shell\BShellAction;
use Prado\Prado;

/**
 * Generic Bacula console command action.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
abstract class BaculaConsoleActionBase extends BShellAction
{
	/**
	 * Stores required parameters common for all Bacula console actions.
	 * Parameters are added to all actions.
	 */
	protected const BASE_PARAMETERS = [];

	/**
	 * Stores optional parameters for all Bacula console actions.
	 * Parameters are added to all actions.
	 */
	protected const BASE_OPTIONAL = ['debug' => '0/1'];

	/**
	 * Standard input, output and error pipes for Bconsole process.
	 */
	private $pipes = [];

	/**
	 * Resource representing Bacula console process.
	 */
	private $process;

	/**
	 * Session command file prefix.
	 */
	private const SESSION_FILE_COMMAND_PREFIX = 'session_command';

	/**
	 * Session output file prefix.
	 */
	private const SESSION_FILE_OUTPUT_PREFIX = 'session_output';

	/**
	 * Session idle timeout (in miliseconds).
	 * Default: 4 hours.
	 */
	private const SESSION_IDLE_TIMEOUT = 14400000;

	/**
	 * Start Bacula console session.
	 *
	 * @param string $sid session identifier
	 * @param string $command console command
	 * @return bool true if console session started successfully, false otherwise
	 */
	protected function runConsole(string $sid, string $command): bool
	{
		$fcpath = $this->getSessionCommandFile($sid);
		if (file_exists($fcpath)) {
			// session already started, do not run new console in this session
			return false;
		}

		$descriptorspec = [
			['pipe', 'r'], // stdin
			['pipe', 'w'], // stdout
			['pipe', 'w']  // stderr
		];

		$env = ['LANG' => 'C'];

		$this->process = proc_open(
			$command,
			$descriptorspec,
			$this->pipes,
			null,
			$env
		);

		stream_set_blocking($this->pipes[0], false);
		stream_set_blocking($this->pipes[1], false);
		stream_set_blocking($this->pipes[2], false);

		$this->listenForCommands($sid);

		return is_resource($this->process);
	}

	/**
	 * Main process listener.
	 * It listends for Bconsole commands, executes them and saves outputs.
	 *
	 * @param string $sid session identifier
	 */
	private function listenForCommands(string $sid): void
	{
		$cid = null;
		$output = '';
		$idle_timeout = self::SESSION_IDLE_TIMEOUT;
		while (true) {
			$command = $this->popCommand($sid);
			if (is_null($command)) {
				// Bconsole is waiting for commands
				// Do nothing
				$idle_timeout -= 100;
			} elseif ($command['command']) {
				if ($command['command'][0] == 'quit') {
					// End Bconsole session
					Logging::log(Logging::CATEGORY_APPLICATION, "End session SID: {$sid}.");
					break;
				} else {
					// Run command
					$cid = $command['cid'];
					$cmd = implode(' ', $command['command']);
					fwrite($this->pipes[0], "{$cmd}\n");
					$idle_timeout = self::SESSION_IDLE_TIMEOUT;
					Logging::log(Logging::CATEGORY_APPLICATION, "Execute command SID: {$sid}, CID: {$cid}, CMD: {$cmd}.");
				}
			}
			$out = stream_get_contents($this->pipes[1]);
			if (!is_string($out)) {
				// Stream failed, finish
				Logging::log(Logging::CATEGORY_APPLICATION, "Stream failed. SID: {$sid}.");
				break;
			}
			if ($out) {
				Logging::log(Logging::CATEGORY_APPLICATION, "Read command output. SID: {$sid}, Output: $out");
			}
			$output .= $out;
			if (self::isBaculaConsoleEnded($output)) {
				if (is_string($cid)) {
					$this->saveOutput($sid, $cid, $output);
				} elseif (is_null($cid) && self::isBaculaConsoleStarted($output)) {
					// Session started
					$this->createSession($sid);
				}
				$output = '';
			}
			if (feof($this->pipes[1])) {
				break;
			}
			if ($idle_timeout <= 0) {
				// Too long inactivity - idle timeout takes place
				break;
			}
			usleep(100000);
		}

		// Session clean up
		$this->finishConsole($sid);
	}

	/**
	 * Detect if Bacula console process is initialized and fully started.
	 *
	 * @param string $output command output
	 * @return bool true if Bacula console process is ready, false otherwise
	 */
	private static function isBaculaConsoleStarted(string $output): bool
	{
		$started = false;
		$out = explode(PHP_EOL, $output);
		for ($i = 0; $i < count($out); $i++) {
			if (preg_match('/^1000 OK:.+$/', $out[$i]) === 1) {
				$started = true;
				break;
			}
		}
		return $started;
	}

	/**
	 * Detect if Bacula console process is ready for commands.
	 *
	 * @param string $output command output
	 * @return bool true if Bacula console is ready for getting commands
	 */
	private static function isBaculaConsoleEnded(string $output): bool
	{
		$output = trim($output);
		$out = explode(PHP_EOL, $output);
		$line = array_pop($out);
		$end_lines = [
			'\$',
			'\*',
			'OK to run\? \(Yes\/mod\/no\):',
			'Enter a period to cancel a command\.',
			'Select parameter to modify',
			'Please enter the full path prefix for restore',
			'Select replace option',
			'Select Client \(File daemon\) resource',
			'Job queued. JobId=',
			'You have messages',
			'Do you want to restore all the files\? \(yes\|no\):'
		];
		$pattern = '/^(' . implode('|', $end_lines) . ')/i';
		return (preg_match($pattern, $line) === 1);
	}

	/**
	 * Execute Bacula console command.
	 *
	 * @param string $sid session identifier
	 * @param string $cid command identifier
	 * @param array $command command to execute
	 * @return bool true if command has been queued to execute, false otherwise
	 */
	public function executeConsole(string $sid, string $cid, array $command): bool
	{
		return $this->pushCommand($sid, $cid, $command, false);
	}

	/**
	 * Finalize and close Bacula console session.
	 *
	 * @param string $sid session identifier
	 */
	protected function finishConsole($sid): void
	{
		// Clean up console session and end
		fclose($this->pipes[0]);
		fclose($this->pipes[1]);
		fclose($this->pipes[2]);
		proc_close($this->process);
		$this->closeSession($sid);
	}

	/**
	 * Execute Bacula console command and initially wait on response.
	 *
	 * @param string $sid session identifier
	 * @param string $cid command identifier
	 * @param array $command command to execute
	 * @return null|string command output if command finished or null if output is not ready yet
	 */
	protected function executeCommand(string $sid, string $cid, array $command): ?string
	{
		$this->executeConsole($sid, $cid, $command);
		$result = $this->waitOnResult($sid, $cid, 3000);
		return $result;
	}

	/**
	 * Add command to command execution queue.
	 *
	 * @param string $sid session identifier
	 * @param string $cid command identifier
	 * @param array $command command to execute
	 * @param bool $strict if true, it overwrites all commands and adds first command (good for initialization)
	 * @return bool true if command successfuly added to execution queue, false otherwise
	 */
	private function pushCommand(string $sid, string $cid, array $command, bool $strict = true): bool
	{
		$content = '';
		$fpath = $this->getSessionCommandFile($sid);
		if (file_exists($fpath)) {
			// File exists, add new command
			$lock = fopen($fpath, 'rb');
			flock($lock, LOCK_SH);
			$cont = file_get_contents($fpath);
			$cmds = json_decode($cont, true);
			if (is_array($cmds)) {
				// Content decoded, add new command
				$cmds[] = [
					'cid' => $cid,
					'command' => $command
				];
			} else {
				// Error while decoding content - reset it
				$cmds = [];
			}
			$content = json_encode($cmds);
			flock($lock, LOCK_UN);
			fclose($lock);
		} elseif ($strict) {
			// File does not exist, create it and add new command
			$commands = [$command];
			$content = json_encode($commands);
		}
		$strict_str = $strict ? 1 : 0;
		Logging::log(Logging::CATEGORY_APPLICATION, "Add new command SID: {$sid}, CID: {$cid}, STRICT: {$strict_str}, CMD: '{$content}'.");
		$result = false;
		if ($content) {
			$result = (file_put_contents($fpath, $content, LOCK_EX) !== false);
		}
		return $result;
	}

	/**
	 * Get Bacula console command from queue to execute.
	 *
	 * @param string $sid session identifier
	 * @return null|array command array or null on error
	 */
	private function popCommand(string $sid): ?array
	{
		$fpath = $this->getSessionCommandFile($sid);
		if (!file_exists($fpath)) {
			return null;
		}
		$lock = fopen($fpath, 'r');
		flock($lock, LOCK_SH);
		$content = file_get_contents($fpath);
		$command = null;
		$cmds = json_decode($content, true);
		$save = false;
		if (is_array($cmds) && $cmds) {
			$command = array_shift($cmds);
			$content = json_encode($cmds);
			$save = true;
		}
		flock($lock, LOCK_UN);
		fclose($lock);
		if ($save && file_put_contents($fpath, $content, LOCK_EX) === false) {
			return null;
		}
		return $command;
	}

	/**
	 * Wait on Bacula console command result.
	 *
	 * @param string $sid session identifier
	 * @param string $cid command identifier
	 * @param null|int $timeout maximum waiting time for result before it times out
	 * @return null|string command output or null on error or timeout in miliseconds
	 */
	protected function waitOnResult(string $sid, string $cid, ?int $timeout = null): ?string
	{
		$result = null;
		if (!$this->sessionExists($sid)) {
			return $result;
		}
		$interval_msec = 100;
		while (true) {
			$result = $this->readOutput($sid, $cid);
			if (is_string($result)) {
				// Result is ready - stop waiting
				break;
			}
			if (is_int($timeout)) {
				if ($timeout > 0) {
					$timeout -= $interval_msec;
				} else {
					break;
				}
			}
			usleep($interval_msec * 1000);
		}
		if ($result) {
			$this->deleteOutputFile($sid, $cid);
		}
		return $result;
	}

	/**
	 * Check if command result is ready.
	 *
	 * @param string $sid session identifier
	 * @param string $cid command identifier
	 * @return bool true if result is ready, false otherwise
	 */
	protected function isResultReady(string $sid, string $cid): bool
	{
		$fpath = $this->getSessionOutputFile($sid, $cid);
		return file_exists($fpath);
	}

	/**
	 * Read command output.
	 *
	 * @param string $sid session identifier
	 * @param string $cid command identifier
	 * @return null|string command output or null if output is not ready or non-existing
	 */
	private function readOutput(string $sid, string $cid): ?string
	{
		$result = null;
		$fpath = $this->getSessionOutputFile($sid, $cid);
		if (!$this->isResultReady($sid, $cid)) {
			return $result;
		}
		$lock = fopen($fpath, 'rb');
		flock($lock, LOCK_SH);
		$content = file_get_contents($fpath);
		$result = $content !== false ? $content : null;
		flock($lock, LOCK_UN);
		fclose($lock);
		return $result;
	}

	/**
	 * Save Bacula console command output.
	 *
	 * @param string $sid session identifier
	 * @param string $cid command identifier
	 * @param string $content command output to save
	 * @return bool true if output saved successfully, false otherwise
	 */
	private function saveOutput(string $sid, string $cid, string $content): bool
	{
		$fpath = $this->getSessionOutputFile($sid, $cid);
		Logging::log(Logging::CATEGORY_APPLICATION, "Save output SID: {$sid}, CID: {$cid}, Output: {$content}.");
		$orig_umask = umask(0);
		umask(0077);
		$ret = (file_put_contents($fpath, $content, LOCK_EX) !== false);
		umask($orig_umask);
		return $ret;
	}

	/**
	 * Delete Bacula console command output file.
	 *
	 * @param string $sid session identifier
	 * @param string $cid command identifier
	 * @return bool true on success, false otherwise
	 */
	private function deleteOutputFile(string $sid, string $cid): bool
	{
		$success = false;
		$fpath = $this->getSessionOutputFile($sid, $cid);
		if (file_exists($fpath)) {
			$success = unlink($fpath);
		}
		return $success;
	}

	/**
	 * Delete Bacula console command file.
	 *
	 * @param string $sid session identifier
	 * @return bool true on success, false otherwise
	 */
	private function deleteCommandFile(string $sid): bool
	{
		$success = false;
		$fpath = $this->getSessionCommandFile($sid);
		if (file_exists($fpath)) {
			$success = unlink($fpath);
		}
		return $success;
	}

	/**
	 * Get session output file path.
	 *
	 * @param string $sid session identifier
	 * @param string $cid command identifier
	 * @return session output file path
	 */
	private static function getSessionOutputFile(string $sid, string $cid): string
	{
		$path = Prado::getPathOfNamespace('Bacularis.Common.Working');
		return sprintf(
			'%s/%s_%s_%s.out',
			$path,
			self::SESSION_FILE_OUTPUT_PREFIX,
			$sid,
			$cid
		);
	}

	/**
	 * Get session command file path.
	 *
	 * @param string $sid session identifier
	 * @return session command file path
	 */
	private static function getSessionCommandFile(string $sid): string
	{
		$path = Prado::getPathOfNamespace('Bacularis.Common.Working');
		return sprintf(
			'%s/%s_%s.cmd',
			$path,
			self::SESSION_FILE_COMMAND_PREFIX,
			$sid
		);
	}

	/**
	 * Create Bacula console session.
	 *
	 * @param string $sid session identifier
	 * @return bool true on success, false otherwise
	 */
	protected function createSession(string $sid): bool
	{
		$fpath = $this->getSessionCommandFile($sid);
		$orig_umask = umask(0);
		umask(0077);
		$result = (file_put_contents($fpath, '[]', LOCK_EX) !== false);
		umask($orig_umask);
		if ($result) {
			Logging::log(
				Logging::CATEGORY_APPLICATION,
				"Start restore session. SID: $sid."
			);
		} else {
			Logging::log(
				Logging::CATEGORY_APPLICATION,
				"Error while starting restore session. SID: $sid."
			);
		}
		return $result;
	}

	/**
	 * Check if Bacula console session exists.
	 *
	 * @param string $sid session identifier
	 * @return true if session exists, false if not
	 */
	protected function sessionExists(string $sid): bool
	{
		$fpath = $this->getSessionCommandFile($sid);
		return file_exists($fpath);
	}

	/**
	 * Wait on session start.
	 *
	 * @param string $sid session identifier
	 * @param int $timeout maximum waiting time for new session in miliseconds
	 */
	protected function waitOnSession(string $sid, int $timeout): bool
	{
		$interval_ms = 100; // miliseconds

		$interval_qs = $interval_ms * 1000; // microseconds
		for ($i = 0; $timeout > 0; $i++) {
			if ($this->sessionExists($sid)) {
				break;
			}
			$timeout -= $interval_ms;
			usleep($interval_qs);
		}
		return ($timeout > 0);
	}

	/**
	 * Close Bacula console session.
	 *
	 * @param string $sid session identifier
	 * @return bool true on success, false otherwise
	 */
	private function closeSession(string $sid): bool
	{
		return $this->deleteCommandFile($sid);
	}

	/**
	 * Prepare a new command identifier.
	 *
	 * @return string command identifier
	 */
	protected function generateCommandId(): string
	{
		$crypto = $this->getModule('crypto');
		return $crypto->getRandomString(12);
	}

	/**
	 * Help command renderer.
	 *
	 * @param string $cmd command
	 */
	public function renderHelpCommand($cmd): void
	{
		$this->showHelpCommand(
			$this->action,
			$this->methods,
			$this->parameters,
			$this->optional,
			$this->description,
			$cmd
		);
	}
}
