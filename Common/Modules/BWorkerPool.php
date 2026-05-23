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

use Bacularis\Common\Modules\Logging;
use SplPriorityQueue;
use SplQueue;

/**
 * Bacularis worker pool class.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class BWorkerPool
{
	/**
	 * Worker pool ID prefix.
	 */
	private const WORKER_POOL_PREFIX = 'WORKER-POOL-';

	/**
	 * Worker pool identifier.
	 */
	private $id = '';

	/**
	 * Single worker class.
	 * It must implement IBWorker interface
	 */
	protected $worker_class;

	/**
	 * Maximum number of workers to create and use.
	 * Default is 16.
	 */
	protected $max_workers = 16;

	/**
	 * Created worker list.
	 */
	protected $workers = [];

	/**
	 * Main task queue.
	 */
	protected $main_queue;

	/**
	 * Retry task queue.
	 */
	protected $retry_queue;

	/**
	 * Stores custom worker task class.
	 */
	private $worker_task_class;

	public function __construct()
	{
		$this->setID();
		$this->initMainQueue();
		$this->initRetryQueue();
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
		if (!($worker instanceof IBWorker)) {
			$worker = null;
		}
		if ($worker) {
			Logging::log(
				Logging::CATEGORY_APPLICATION,
				"[$worker] Worker '{$worker_class}' has been created."
			);
		}
		return $worker;
	}

	/**
	 * Run worker.
	 *
	 * @param mixed $task task to do
	 * @return mixed worker identifier
	 */
	private function runWorker(IBWorkerTask $task): void
	{
		Logging::log(Logging::CATEGORY_APPLICATION, "[WORKER] Run worker.");
		$worker = $this->createWorker();
		$worker_id = $worker->run($task);
		$this->workers[$worker_id] = $task;
		Logging::log(
			Logging::CATEGORY_APPLICATION,
			"[$worker] Worker has started task '{$task}'."
		);
	}

	/**
	 * Create new worker task.
	 *
	 * @param array $params task parameters
	 * @return null|object task object or null if incompatible task class used
	 */
	private function createWorkerTask($params): ?object
	{
		Logging::log(Logging::CATEGORY_APPLICATION, "[TASK] Create worker task.");
		$worker_task_class = $this->getWorkerTaskClass();
		$worker_task = new $worker_task_class($params);
		if (!($worker_task instanceof IBWorkerTask)) {
			$worker_task = null;
		}
		if ($worker_task) {
			Logging::log(
				Logging::CATEGORY_APPLICATION,
				"[$worker_task] Worker task '{$worker_task_class}' has been created."
			);
		}
		return $worker_task;
	}

	/**
	 * Set worker class.
	 * The class must implement IBWorker interface
	 *
	 * @param string $class class name
	 */
	public function setWorkerClass(string $class): void
	{
		$this->worker_class = $class;
	}

	/**
	 * Get worker class.
	 *
	 * @return IBWorker
	 */
	public function getWorkerClass(): string
	{
		return $this->worker_class;
	}

	/**
	 * Set worker task class.
	 *
	 * @param string $class worker task class
	 */
	public function setWorkerTaskClass(string $class): void
	{
		$this->worker_task_class = $class;
	}

	/**
	 * Get worker task class.
	 *
	 * @return string worker task class
	 */
	public function getWorkerTaskClass(): string
	{
		return $this->worker_task_class;
	}

	/**
	 * Set maximum number of workers.
	 *
	 * @param int $max_workers maximum number of workers
	 */
	public function setMaxWorkers(int $max_workers): void
	{
		$this->max_workers = $max_workers;
	}

	/**
	 * Get maximum number of workers.
	 *
	 * @return int maximum  number of workers
	 */
	public function getMaxWorkers(): int
	{
		return $this->max_workers;
	}

	/**
	 * Add worker task to main running worker pool queue.
	 *
	 * @param array $data worker task data
	 */
	public function addTask(array $data): void
	{
		Logging::log(Logging::CATEGORY_APPLICATION, "[TASK] Add task to main queue.");
		$task = $this->createWorkerTask($data);
		$task->setRetries(0);
		$this->pushMainQueue($task);
		Logging::log(Logging::CATEGORY_APPLICATION, "[$task] Task added to main queue.");
	}

	/**
	 * Initialize main worker pool queue.
	 */
	protected function initMainQueue(): void
	{
		$this->main_queue = new SplQueue();
	}

	/**
	 * Push task to main worker pool queue.
	 *
	 * @param IBWorkerTask $task worker task object
	 */
	protected function pushMainQueue(IBWorkerTask $task): void
	{
		$this->main_queue->enqueue($task);
	}

	/**
	 * Pop task from main worker pool queue.
	 *
	 * @return null|IBWorkerTask worker pook task object from queue or null if main queue is empty
	 */
	protected function popMainQueue(): ?IBWorkerTask
	{
		$task = null;
		if (!$this->isMainQueueEmpty()) {
			$task = $this->main_queue->dequeue();
			Logging::log(
				Logging::CATEGORY_APPLICATION,
				"[$task] Pop task from main queue."
			);
		}
		return $task;
	}

	/**
	 * Check if main worker pool queue empty.
	 *
	 * @return book true if queue is empty, false otherwise
	 */
	protected function isMainQueueEmpty(): bool
	{
		return $this->main_queue->isEmpty();
	}

	/**
	 * Initialize retry worker pool queue.
	 */
	protected function initRetryQueue(): void
	{
		$this->retry_queue = new SplPriorityQueue();
		$this->retry_queue->setExtractFlags(SplPriorityQueue::EXTR_DATA);
	}

	/**
	 * Push task with given priority to retry queue.
	 *
	 * @param IBWorkerTask $task worker pool task object
	 * @param int $priority task priority
	 */
	protected function pushRetryQueue(IBWorkerTask $task, int $priority): void
	{
		$this->retry_queue->insert($task, $priority);
		Logging::log(
			Logging::CATEGORY_APPLICATION,
			"[$task] Task added to retry queue with priority {$priority}."
		);
	}

	/**
	 * Pop task from retry queue.
	 *
	 * @return null|IBWorkerTask worker pool task object or null if retry queue is empty
	 */
	protected function popRetryQueue(): ?IBWorkerTask
	{
		$task = null;
		if (!$this->retry_queue->isEmpty()) {
			$task = $this->retry_queue->extract();
			Logging::log(Logging::CATEGORY_APPLICATION, "[$task] Pop task from retry queue.");
		}
		return $task;
	}

	/**
	 * Get top worker pool task in the retry queue.
	 *
	 * @return null|IBWorkerTask worker pool task object or null if retry queue is empty
	 */
	protected function topRetryQueue(): ?IBWorkerTask
	{
		$task = null;
		if (!$this->retry_queue->isEmpty()) {
			$task = $this->retry_queue->top();
		}
		return $task;
	}

	/**
	 * Check if retry queue is empty.
	 *
	 * @return bool true if retry queue is empty, false otherwise
	 */
	protected function isRetryQueueEmpty(): bool
	{
		return $this->retry_queue->isEmpty();
	}

	/**
	 * Check if there is available at least one free worker to use.
	 *
	 * @return bool true if available worker exists, false otherwise
	 */
	public function isAvailableWorker(): bool
	{
		$available = $this->getAvailableWorkerCount();
		return ($available > 0);
	}

	/**
	 * Get number of available workers.
	 *
	 * @return int number of available workers
	 */
	protected function getAvailableWorkerCount(): int
	{
		$active = count($this->workers);
		$available = $this->max_workers - $active;
		return $available;
	}

	/**
	 * Fill workers by tasks.
	 * First is used the retry task queue and after is used main task queue.
	 */
	protected function fillWorkers(): void
	{
		Logging::log(Logging::CATEGORY_APPLICATION, "[$this] Fill workers.");
		$available = $this->getAvailableWorkerCount();

		if ($available <= 0) {
			// no worker available to use
			Logging::log(
				Logging::CATEGORY_APPLICATION,
				"[$this] No worker available."
			);
			return;
		}
		Logging::log(
			Logging::CATEGORY_APPLICATION,
			"[$this] Available are {$available} workers to use."
		);

		$now = time();

		// Retry queue first
		while ($available > 0 && !$this->isRetryQueueEmpty()) {
			$top_task = $this->topRetryQueue();
			if ($top_task->getRetryAt() > $now) {
				// task still needs to wait on run
				break;
			}

			$task = $this->popRetryQueue();
			$this->runWorker($task);

			$available--;
		}

		// Main queue next
		while ($available > 0 && !$this->isMainQueueEmpty()) {
			$task = $this->popMainQueue();
			$this->runWorker($task);

			$available--;
		}
	}

	/**
	 * Run one iteration of worker pool.
	 * This is useful to implement e.g. natural throottling where worker pool
	 * is not able to read more than is able to send to destination such as
	 * in data stream sent from Bacula storage daemon.
	 *
	 * @return int number of currently running workers
	 */
	public function runOne(): int
	{
		$this->fillWorkers();

		$this->processCompleted();

		Logging::log(
			Logging::CATEGORY_APPLICATION,
			"[$this] Pool is working."
		);

		$worker_len = count($this->workers);
		return $worker_len;
	}

	/**
	 * Run worker pool.
	 * This is useful when the task list is known in advance
	 * and can be all added before starting the worker pool.
	 */
	public function run(): void
	{
		do {
			$this->runOne();
			$state = $this->getRunningState();
		} while ($state);
	}

	/**
	 * Get worker pool running state.
	 *
	 * @return bool if true then worker pool ends working, otherwise it continues
	 */
	public function getRunningState(): bool
	{
		return (!$this->isMainQueueEmpty() || !$this->isRetryQueueEmpty());
	}

	/**
	 * Complete finished workers.
	 */
	protected function processCompleted(): void
	{
		$finished = [];
		foreach ($this->workers as $worker_id => $worker) {
			if ($worker->isFinished()) {
				$finished[] = $worker_id;
			}
		}
		$finished_len = count($finished);
		for ($i = 0; $i < $finished_len; $i++) {
			unset($this->workers[$finished[$i]]);
		}
	}

	/**
	 * Get worker pool identifier.
	 *
	 * @return string worker pool identifier
	 */
	public function getID(): string
	{
		return $this->id;
	}

	/**
	 * Set worker pool identifier.
	 */
	public function setID(): void
	{
		$this->id = self::WORKER_POOL_PREFIX . spl_object_id($this);
	}

	public function __toString()
	{
		return $this->getID();
	}
}
