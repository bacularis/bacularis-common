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
use Bacularis\Common\Modules\Logging;
use RuntimeException;
use Throwable;

/**
 * Module responsible for executing a program.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class ExecuteCommand extends CommonModule
{
	/**
	 * Command pattern used to execute command.
	 */
	public const COMMAND_PATTERN = '%s 2>&1';

	/**
	 * Get command pattern.
	 *
	 * @return string command pattern
	 */
	private static function getCmdPattern(): string
	{
		// Default command pattern
		return self::COMMAND_PATTERN;
	}

	/**
	 * Execute a shell expression assembled from command fragments.
	 * Shell operators and already-composed fragments are interpreted by the shell.
	 *
	 * @param array $params command with parameters
	 */
	public static function execCommand(array $params): array
	{
		$cmd_pattern = self::getCmdPattern();
		$base_cmd = implode(' ', $params);
		$cmd = sprintf($cmd_pattern, $base_cmd);
		exec($cmd, $output, $exitcode);
		Logging::log(
			Logging::CATEGORY_EXECUTE,
			Logging::prepareCommand($cmd, $output)
		);
		$result = self::prepareResult($output, $exitcode);
		return $result;
	}

	/**
	 * Execute a program with literal arguments, escaping every array element.
	 * Shell operators such as pipes and redirects are treated as literal arguments.
	 * Unlike execCommand(), this method does not accept shell expressions.
	 * Temporary environment values are restored before logging and result preparation.
	 * Environment values are not logged, but child output is logged without redaction.
	 *
	 * @param array $params program name followed by individual literal arguments
	 * @param array $environment temporary environment variable names and scalar values
	 * @return array output and error status, with a nonzero error for invalid input
	 */
	public static function execProgram(array $params, array $environment = []): array
	{
		if (count($params) === 0) {
			$output = [GenericError::MSG_ERROR_INVALID_COMMAND];
			return self::prepareResult($output, GenericError::ERROR_INVALID_COMMAND);
		}
		$temporary_environment = [];
		foreach ($environment as $name => $value) {
			if (!is_string($name) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $name) !== 1 || !is_scalar($value)) {
				$output = [GenericError::MSG_ERROR_INVALID_COMMAND];
				return self::prepareResult($output, GenericError::ERROR_INVALID_COMMAND);
			}
			$value = (string) $value;
			if (strpos($value, "\0") !== false) {
				$output = [GenericError::MSG_ERROR_INVALID_COMMAND];
				return self::prepareResult($output, GenericError::ERROR_INVALID_COMMAND);
			}
			$temporary_environment[$name] = $value;
		}
		$cmd_pattern = self::getCmdPattern();
		$safe_params = array_map('escapeshellarg', $params);
		$base_cmd = implode(' ', $safe_params);
		$cmd = sprintf($cmd_pattern, $base_cmd);
		$previous_environment = [];
		foreach ($temporary_environment as $name => $value) {
			$previous_environment[$name] = getenv($name);
		}
		$environment_set = true;
		try {
			foreach ($temporary_environment as $name => $value) {
				if (!putenv("{$name}={$value}")) {
					$environment_set = false;
					break;
				}
			}
			if ($environment_set) {
				exec($cmd, $output, $exitcode);
			}
		} finally {
			foreach ($previous_environment as $name => $value) {
				if ($value === false) {
					putenv($name);
				} else {
					putenv("{$name}={$value}");
				}
			}
		}
		if (!$environment_set) {
			$output = [GenericError::MSG_ERROR_INVALID_COMMAND];
			return self::prepareResult($output, GenericError::ERROR_INVALID_COMMAND);
		}
		$log_message = Logging::prepareCommand($cmd, $output);
		Logging::log(Logging::CATEGORY_EXECUTE, $log_message);
		$result = self::prepareResult($output, $exitcode);
		return $result;
	}

	/**
	 * Execute a non-interactive Unix program directly, without a shell.
	 * Both output streams are captured as unmodified bytes. The positive timeout
	 * covers the entire child process; termination and pipe cleanup may take up
	 * to two additional seconds. Only the direct child is managed, not its descendants.
	 * Environment overrides are passed to the child without changing PHP's environment.
	 * An empty override removes only that variable; inherited empty values are preserved.
	 * A combined output limit is enforced before appending bytes to either buffer.
	 *
	 * The returned array always contains:
	 * - stdout, stderr: captured strings, possibly incomplete on runner failure;
	 * - exitcode: application exit code, or null if unavailable or signal-terminated;
	 * - signal: terminating Unix signal number, or null;
	 * - error: GenericError::ERROR_NO_ERRORS on completed capture, including nonzero
	 *   application exits and signals; ERROR_INVALID_COMMAND for invalid arguments,
	 *   or ERROR_INTERNAL_ERROR for startup, I/O, timeout, overflow or cleanup failure;
	 * - error_message: runner diagnostic, independent of the application's stderr;
	 * - timed_out, output_limited: flags identifying timeout and capture overflow.
	 * Callers must check error, exitcode and signal separately. Unknown exit status
	 * is a runner failure. Cleanup diagnostics are appended to the original error_message.
	 * Output and environment values are not logged here.
	 *
	 * @param array $params nonempty program argv containing only strings without NUL bytes
	 * @param int $timeout positive process timeout in seconds
	 * @param array $environment inherited environment overrides with scalar values
	 * @param int $max_output_bytes positive combined stdout/stderr capture limit
	 * @return array process output, application status and independent runner status
	 */
	public static function execProcess(array $params, int $timeout, array $environment = [], int $max_output_bytes = 16777216): array
	{
		$result = [
			'stdout' => '',
			'stderr' => '',
			'exitcode' => null,
			'signal' => null,
			'error' => GenericError::ERROR_NO_ERRORS,
			'error_message' => '',
			'timed_out' => false,
			'output_limited' => false
		];
		if (!$params || $timeout <= 0 || $max_output_bytes <= 0) {
			$result['error'] = GenericError::ERROR_INVALID_COMMAND;
			$result['error_message'] = 'Expected positive timeout and output limit.';
			return $result;
		}
		$params = array_values($params);
		foreach ($params as $param) {
			if (!is_string($param) || strpos($param, "\0") !== false) {
				$result['error'] = GenericError::ERROR_INVALID_COMMAND;
				$result['error_message'] = 'Program arguments must be strings without NUL bytes.';
				return $result;
			}
		}
		if ($params[0] === '') {
			$result['error'] = GenericError::ERROR_INVALID_COMMAND;
			$result['error_message'] = 'The program name must not be empty.';
			return $result;
		}
		foreach ($environment as $name => $value) {
			if (!is_string($name) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $name) !== 1 || !is_scalar($value)) {
				$result['error'] = GenericError::ERROR_INVALID_COMMAND;
				$result['error_message'] = 'Invalid environment override.';
				return $result;
			}
			$value = (string) $value;
			if (strpos($value, "\0") !== false) {
				$result['error'] = GenericError::ERROR_INVALID_COMMAND;
				$result['error_message'] = 'Environment values must not contain NUL bytes.';
				return $result;
			}
			$environment[$name] = $value;
		}

		$process = null;
		$pipes = [];
		$running = true;
		$captured = 0;
		try {
			$child_environment = null;
			if ($environment) {
				$inherited_environment = getenv();
				if (!is_array($inherited_environment)) {
					throw new RuntimeException('Unable to read the inherited environment.');
				}
				$merged_environment = array_replace($inherited_environment, $environment);
				$child_environment = [];
				foreach ($merged_environment as $name => $value) {
					if ($value !== '' || !key_exists($name, $environment)) {
						// Unix proc_open accepts indexed NAME=value entries. NAME= is
						// nonempty, so PHP 7.4 does not discard inherited empty values.
						$child_environment[] = $name . '=' . $value;
					}
				}
			}
			$descriptors = [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']];
			$deadline = hrtime(true) / 1000000000 + $timeout;
			$process = @proc_open($params, $descriptors, $pipes, null, $child_environment);
			if (!is_resource($process)) {
				throw new RuntimeException('Unable to start the process.');
			}
			foreach ([0, 1, 2] as $index) {
				if (!isset($pipes[$index]) || !is_resource($pipes[$index])) {
					throw new RuntimeException('Unable to open process pipes.');
				}
			}
			if (!fclose($pipes[0])) {
				throw new RuntimeException('Unable to close process stdin.');
			}
			unset($pipes[0]);
			foreach ($pipes as $pipe) {
				if (!stream_set_blocking($pipe, false)) {
					throw new RuntimeException('Unable to configure nonblocking process pipes.');
				}
			}
			$drain_deadline = null;
			while ($running || $pipes) {
				if ($running) {
					$running = self::updateProcessStatus($process, $result);
				}
				$now = hrtime(true) / 1000000000;
				if ($running && $now >= $deadline) {
					$result['timed_out'] = true;
					throw new RuntimeException('Process timed out.');
				}
				if (!$running) {
					if ($drain_deadline === null) {
						$drain_deadline = $now + 1;
					}
					if ($pipes && $now >= $drain_deadline) {
						throw new RuntimeException('Process output pipes remained open after the child exited.');
					}
				}
				self::readProcessOutput($pipes, $result, $max_output_bytes, $captured);
				if ($result['output_limited']) {
					throw new RuntimeException('Combined process output limit exceeded.');
				}
			}
		} catch (Throwable $exception) {
			$result['error'] = GenericError::ERROR_INTERNAL_ERROR;
			$result['error_message'] = $exception->getMessage();
		} finally {
			if (is_resource($process)) {
				self::finishProcess($process, $pipes, $result, $running, $max_output_bytes, $captured);
			}
			foreach ($pipes as $pipe) {
				if (is_resource($pipe)) {
					try {
						if (!fclose($pipe)) {
							throw new RuntimeException('Unable to close a process pipe.');
						}
					} catch (Throwable $exception) {
						$result['error'] = GenericError::ERROR_INTERNAL_ERROR;
						$result['error_message'] .= ' Cleanup: unable to close a process pipe.';
					}
				}
			}
		}
		return $result;
	}

	/**
	 * Poll a child and retain the first termination status for PHP before 8.3.
	 *
	 * @param resource $process process handle
	 * @param array $result process result reference
	 * @return bool true while the child is running
	 */
	private static function updateProcessStatus($process, array &$result): bool
	{
		$status = @proc_get_status($process);
		if (!is_array($status)) {
			throw new RuntimeException('Unable to obtain process status.');
		}
		if (!$status['running']) {
			if ($status['signaled']) {
				$result['signal'] = $status['termsig'];
				$result['exitcode'] = null;
			} elseif ($result['exitcode'] === null && $status['exitcode'] >= 0) {
				$result['exitcode'] = $status['exitcode'];
			}
		}
		return $status['running'];
	}

	/**
	 * Drain ready streams fairly, retaining at most the combined capture limit.
	 *
	 * @param array $pipes open stdout/stderr pipes, removed when EOF is reached
	 * @param array $result process result reference
	 * @param int $max_output_bytes combined capture limit
	 * @param int $captured number of retained bytes
	 */
	private static function readProcessOutput(array &$pipes, array &$result, int $max_output_bytes, int &$captured): void
	{
		if (!$pipes) {
			usleep(10000);
			return;
		}
		$read = array_values($pipes);
		$write = $except = null;
		$ready = @stream_select($read, $write, $except, 0, 20000);
		if ($ready === false) {
			throw new RuntimeException('Unable to select process output pipes.');
		}
		foreach ($pipes as $index => $pipe) {
			if (in_array($pipe, $read, true)) {
				$chunk = @fread($pipe, 8192);
				if ($chunk === false) {
					throw new RuntimeException('Unable to read process output.');
				}
				$remaining = $max_output_bytes - $captured;
				$length = strlen($chunk);
				if ($length > $remaining) {
					$result['output_limited'] = true;
					$chunk = substr($chunk, 0, $remaining);
					$length = $remaining;
				}
				$key = $index === 1 ? 'stdout' : 'stderr';
				$result[$key] .= $chunk;
				$captured += $length;
			}
			if (feof($pipe)) {
				if (!fclose($pipe)) {
					throw new RuntimeException('Unable to close process output pipe.');
				}
				unset($pipes[$index]);
			}
		}
	}

	/**
	 * Stop a failed child, drain available bytes and release resources without
	 * calling blocking proc_close() on a child whose termination is unconfirmed.
	 * Unix PHP resource destruction uses WNOHANG when proc_close() is not called.
	 * This releases the handle without waiting; it does not guarantee termination
	 * or eventual reaping of a child that cannot be killed. Cleanup errors are
	 * recorded once per operation, preserving the original runner diagnostic.
	 *
	 * @param resource $process process handle
	 * @param array $pipes open pipe references
	 * @param array $result process result reference
	 * @param bool $running last known child state
	 * @param int $max_output_bytes combined capture limit
	 * @param int $captured number of retained bytes
	 */
	private static function finishProcess($process, array &$pipes, array &$result, bool $running, int $max_output_bytes, int &$captured): void
	{
		$started = hrtime(true) / 1000000000;
		$term_sent = $kill_sent = false;
		$cleanup_errors = [];
		$read_pipes = [];
		// Read only successfully configured output pipes. All original pipes,
		// including stdin from a failed initialization, are closed in one place below.
		foreach ($pipes as $index => $pipe) {
			if ($index === 0 || !is_resource($pipe)) {
				continue;
			}
			try {
				if (!stream_set_blocking($pipe, false)) {
					throw new RuntimeException('Unable to configure nonblocking process output.');
				}
				$read_pipes[$index] = $pipe;
			} catch (Throwable $exception) {
				$cleanup_errors['pipe-' . $index] = $exception->getMessage();
			}
		}
		while ($running) {
			try {
				$running = self::updateProcessStatus($process, $result);
			} catch (Throwable $exception) {
				$cleanup_errors['status'] = $exception->getMessage();
			}
			if (!$running) {
				break;
			}
			$now = hrtime(true) / 1000000000;
			$signals = [];
			if (!$term_sent) {
				$signals[] = 15;
				$term_sent = true;
			}
			if (!$kill_sent && $now >= $started + 1) {
				$signals[] = 9;
				$kill_sent = true;
			}
			foreach ($signals as $signal) {
				$label = $signal === 15 ? 'SIGTERM' : 'SIGKILL';
				try {
					if (!@proc_terminate($process, $signal)) {
						throw new RuntimeException('Signal could not be sent.');
					}
				} catch (Throwable $exception) {
					$cleanup_errors[$label] = $label . ': ' . $exception->getMessage();
				}
			}
			if ($now >= $started + 2) {
				$cleanup_errors['termination'] = 'Unable to confirm child termination after SIGKILL.';
				break;
			}
			try {
				self::readProcessOutput($read_pipes, $result, $max_output_bytes, $captured);
			} catch (Throwable $exception) {
				$cleanup_errors['output'] = $exception->getMessage();
				usleep(10000);
			}
		}
		// Drain only currently available bytes, never wait for inherited descriptors.
		try {
			if (!$running && $read_pipes && $result['error'] !== GenericError::ERROR_NO_ERRORS) {
				self::readProcessOutput($read_pipes, $result, $max_output_bytes, $captured);
			}
		} catch (Throwable $exception) {
			$cleanup_errors['output'] = $exception->getMessage();
		}
		foreach ($pipes as $pipe) {
			if (is_resource($pipe)) {
				try {
					if (!fclose($pipe)) {
						throw new RuntimeException('Pipe close failed.');
					}
				} catch (Throwable $exception) {
					$cleanup_errors['close'] = 'Unable to close process pipe: ' . $exception->getMessage();
				}
			}
		}
		$pipes = [];
		// A false running flag without a terminal status may itself be a waitpid
		// failure. Never enter a blocking wait on that evidence alone.
		if (!$running && ($result['exitcode'] !== null || $result['signal'] !== null)) {
			try {
				// PHP 7.4 may return -1 after polling reaped the child. The saved
				// terminal status remains authoritative; proc_close releases the handle.
				@proc_close($process);
			} catch (Throwable $exception) {
				$cleanup_errors['handle'] = 'Unable to close process handle: ' . $exception->getMessage();
			}
		}
		if ($result['error'] === GenericError::ERROR_NO_ERRORS && $result['exitcode'] === null && $result['signal'] === null) {
			$result['error'] = GenericError::ERROR_INTERNAL_ERROR;
			$result['error_message'] = 'Process termination status is unavailable.';
		}
		if ($cleanup_errors) {
			$result['error'] = GenericError::ERROR_INTERNAL_ERROR;
			$details = implode(' ', $cleanup_errors);
			$result['error_message'] = trim($result['error_message'] . ' Cleanup: ' . $details);
		}
	}

	/**
	 * Prepare command result.
	 *
	 * @param array $output output from command execution
	 * @param int $exitcode command exit code
	 * @return array result with output and exitcode
	 */
	public static function prepareResult($output, $exitcode)
	{
		$result = [
			'output' => $output,
			'error' => $exitcode
		];
		return $result;
	}
}
