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

use Bacularis\Common\Modules\Protocol\HTTP\Header as HTTPHeader;

/**
 * Bacularis HTTP worker pool class.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class HTTPBWorkerPool extends BWorkerPool
{
	/**
	 * HTTP worker pool async request handler.
	 */
	private $mh;

	/**
	 * Function to check if HTTP response is valid.
	 */
	private $is_response_valid_func;

	/**
	 * Single worker class name.
	 */
	private const WORKER_CLASS = '\Bacularis\Common\Modules\HTTPBWorker';

	public function __construct()
	{
		$this->initialize();
		parent::__construct();
	}

	/**
	 * Initialize HTTP worker pool module.
	 */
	private function initialize()
	{
		$this->mh = curl_multi_init();
		$this->setWorkerClass(self::WORKER_CLASS);
	}

	/**
	 * Create new worker.
	 *
	 * @return null|object worker object or null if incompatible worker class used
	 */
	protected function createWorker(): ?object
	{
		Logging::log(Logging::CATEGORY_APPLICATION, "[WORKER] Create worker.");
		$worker_class = $this->getWorkerClass();
		$worker = new $worker_class();
		if ($worker instanceof IBHTTPWorker) {
			$worker->setHTTPHandler($this->mh);
		} else {
			$worker = null;
		}
		if ($worker) {
			Logging::log(
				Logging::CATEGORY_APPLICATION,
				"[$this][$worker] Worker '{$worker_class}' has been created."
			);
		}
		return $worker;
	}

	/**
	 * Run one HTTP worker pool action/iteration.
	 * This is specially useful if worker pool tasks are not known in advance
	 * but they are added after worker pool started.
	 *
	 * @return int number of currently running workers
	 */
	public function runOne(): int
	{
		curl_multi_exec($this->mh, $running);

		$this->processCompleted();

		$this->fillWorkers();

		if ($running > 0) {
			// Wait max. 0.5 second until reading or writing is possible for any connection
			curl_multi_select($this->mh, 0.5);
		}
		return $running;
	}

	/**
	 * Run main worker pool loop.
	 * Here the HTTP worker pool starts working.
	 */
	public function run(): void
	{
		do {
			Logging::log(
				Logging::CATEGORY_APPLICATION,
				"[$this] Pool is working."
			);
			$state = $this->getRunningState();
			$running = $this->runOne();

		} while ($state || $running);
	}

	/**
	 * Get worker pool running state.
	 *
	 * @return bool if true, worker pool is running, false otherwise
	 */
	public function getRunningState(): bool
	{
		return (!$this->main_queue->isEmpty() || !$this->retry_queue->isEmpty());
	}

	/**
	 * Complete single request.
	 * This is called when the request ends working and the response is available.
	 *
	 * @throws BWorkerPoolException on error with reading response info
	 */
	protected function processCompleted(): void
	{
		while ($info = curl_multi_info_read($this->mh)) {
			if (!is_array($info)) {
				$emsg = "[$this] Error while reading response info.";
				Logging::log(
					Logging::CATEGORY_APPLICATION,
					$emsg
				);
				throw new BWorkerPoolException($emsg, 1);
			}
			$ch = $info['handle'];
			$worker_id = (int) $ch;
			$task = $this->workers[$worker_id];
			Logging::log(
				Logging::CATEGORY_APPLICATION,
				"[$this][$task] Task is finishing."
			);

			$resp_func = $this->getIsResponseValidFunc();

			$url = curl_getinfo($ch, \CURLINFO_EFFECTIVE_URL);
			if ($info['result'] === \CURLE_OK && (is_null($resp_func) || $resp_func($ch) === true)) {
				// Response successful
				$this->handleSuccess($ch, $task);
			} elseif ($url === '' && $info['result'] === \CURLE_URL_MALFORMAT) {
				// Special local task that should not be sent
				$this->handleLocal($task);
			} else {
				// Response with error
				Logging::log(
					Logging::CATEGORY_APPLICATION,
					"[$this] Request finished with error. Error: '{$info['result']}'."
				);
				$this->handleError($task);
			}

			// Clean-up finished connection
			curl_multi_remove_handle($this->mh, $ch);
			curl_close($ch);
			unset($this->workers[$worker_id]);
		}
	}

	/**
	 * Handle successful HTTP response.
	 *
	 * @param mixed $ch HTTP connection handler
	 * @param IBWorkerTask $task task object
	 */
	private function handleSuccess($ch, IBWorkerTask $task): void
	{
		// Get response details (headers, data)
		$url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
		$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		$content = curl_multi_getcontent($ch);
		$headers_str = substr($content, 0, $header_size);
		$headers = HTTPHeader::parseAll($headers_str);
		$data = substr($content, $header_size);

		Logging::log(
			Logging::CATEGORY_APPLICATION,
			"[$this][$task] URL: $url, Headers: $headers_str."
		);

		Logging::log(
			Logging::CATEGORY_APPLICATION,
			"[$this][$task] Task finished successfully."
		);

		$result = ['url' => $url, 'headers' => $headers, 'data' => $data, 'task_data' => $task->getData()];
		$task->finish($result);
	}

	/**
	 * Handle special local task.
	 *
	 * @param IBWorkerTask $task task object
	 */
	private function handleLocal(IBWorkerTask $task): void
	{
		Logging::log(
			Logging::CATEGORY_APPLICATION,
			"[$this][$task] Local task finished."
		);

		$result = ['url' => '', 'headers' => [], 'data' => '', 'task_data' => $task->getData()];
		$task->finish($result);
	}

	/**
	 * Handle error HTTP response.
	 *
	 * @param IBWorkerTask $task task object
	 * @throws BWorkerPoolException on reaching maximum retry attemps for task
	 */
	private function handleError(IBWorkerTask $task)
	{
		if ($task->isMaxRetriesReached()) {
			$emsg = "[$this][$task] Max retry attemps REACHED for task.";
			Logging::log(
				Logging::CATEGORY_APPLICATION,
				$emsg
			);
			throw new BWorkerPoolException($emsg, 1);
		}

		// Prepare to task retry
		$retries = $task->getRetries();
		$retries++;
		$delay = pow(2, $retries);
		$retry_at = time() + $delay;

		$task->setRetries($retries);
		$task->setRetryAt($retry_at);

		Logging::log(
			Logging::CATEGORY_APPLICATION,
			"[$this][$task] Task finished with ERROR. Move it back to retry queue. Retries: {$retries}, Retry at: {-$retry_at}"
		);

		// Put task in retry queue
		$this->pushRetryQueue($task, -$retry_at);
	}

	/**
	 * Set function to check if HTTP response is valid.
	 *
	 * @param callable $func function to check HTTP response
	 */
	public function setIsResponseValidFunc(callable $func): void
	{
		$this->is_response_valid_func = $func;
	}

	/**
	 * Get function to check if HTTP response is valid.
	 *
	 * @return null|callable function to check HTTP response or null if function not Set
	 */
	public function getIsResponseValidFunc(): ?callable
	{
		return $this->is_response_valid_func;
	}
}
