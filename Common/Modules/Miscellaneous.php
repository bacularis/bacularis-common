<?php
/*
 * Bacularis - Bacula web interface
 *
 * Copyright (C) 2021-2024 Marcin Haba
 *
 * The main author of Bacularis is Marcin Haba, with contributors, whose
 * full list can be found in the AUTHORS file.
 *
 * Bacula(R) - The Network Backup Solution
 * Baculum   - Bacula web interface
 *
 * Copyright (C) 2013-2020 Kern Sibbald
 *
 * The main author of Baculum is Marcin Haba.
 * The original author of Bacula is Kern Sibbald, with contributions
 * from many others, a complete list can be found in the file AUTHORS.
 *
 * You may use this file and others of this release according to the
 * license defined in the LICENSE file, which includes the Affero General
 * Public License, v3.0 ("AGPLv3") and some additional permissions and
 * terms pursuant to its AGPLv3 Section 7.
 *
 * This notice must be preserved when any source code is
 * conveyed and/or propagated.
 *
 * Bacula(R) is a registered trademark of Kern Sibbald.
 */

namespace Bacularis\Common\Modules;

use Prado\TModule;

/**
 * Module with miscellaneous tools.
 * Targetly it is meant to remove after splitting into smaller modules.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class Miscellaneous extends TModule
{
	public const RPATH_PATTERN = '/^b2\d+$/';

	public $job_types = [
		'B' => 'Backup',
		'M' => 'Migrated',
		'V' => 'Verify',
		'R' => 'Restore',
		'I' => 'Internal',
		'D' => 'Admin',
		'A' => 'Archive',
		'C' => 'Copy',
		'c' => 'Copy Job',
		'g' => 'Migration'
	];

	private $jobLevels = [
		'F' => 'Full',
		'I' => 'Incremental',
		'D' => 'Differential',
		'B' => 'Base',
		'f' => 'VirtualFull',
		'V' => 'InitCatalog',
		'C' => 'Catalog',
		'O' => 'VolumeToCatalog',
		'd' => 'DiskToCatalog',
		'A' => 'Data'
	];

	public $jobStates = [
		'C' => ['value' => 'Created', 'description' => 'Created but not yet running'],
		'R' => ['value' => 'Running', 'description' => 'Running'],
		'B' => ['value' => 'Blocked', 'description' => 'Blocked'],
		'T' => ['value' => 'Terminated', 'description' => 'Terminated normally'],
		'W' => ['value' => 'Terminated', 'description' => 'Terminated normally with warnings'],
		'E' => ['value' => 'Error', 'description' => 'Terminated in Error'],
		'e' => ['value' => 'Non-fatal error', 'description' => 'Non-fatal error'],
		'f' => ['value' => 'Fatal error', 'description' => 'Fatal error'],
		'D' => ['value' => 'Verify Diff.', 'description' => 'Verify Differences'],
		'A' => ['value' => 'Canceled', 'description' => 'Canceled by the user'],
		'I' => ['value' => 'Incomplete', 'description' => 'Incomplete Job'],
		'F' => ['value' => 'Waiting on FD', 'description' => 'Waiting on the File daemon'],
		'S' => ['value' => 'Waiting on SD', 'description' => 'Waiting on the Storage daemon'],
		'm' => ['value' => 'Waiting for new vol.', 'description' => 'Waiting for a new Volume to be mounted'],
		'M' => ['value' => 'Waiting for mount', 'description' => 'Waiting for a Mount'],
		's' => ['value' => 'Waiting for storage', 'description' => 'Waiting for Storage resource'],
		'j' => ['value' => 'Waiting for job', 'description' => 'Waiting for Job resource'],
		'c' => ['value' => 'Waiting for client', 'description' => 'Waiting for Client resource'],
		'd' => ['value' => 'Waiting for Max. jobs', 'description' => 'Wating for Maximum jobs'],
		't' => ['value' => 'Waiting for start', 'description' => 'Waiting for Start Time'],
		'p' => ['value' => 'Waiting for higher priority', 'description' => 'Waiting for higher priority job to finish'],
		'i' => ['value' => 'Batch insert', 'description' => 'Doing batch insert file records'],
		'a' => ['value' => 'Despooling attributes', 'description' => 'SD despooling attributes'],
		'l' => ['value' => 'Data despooling', 'description' => 'Doing data despooling'],
		'L' => ['value' => 'Commiting data', 'description' => 'Committing data (last despool)']
	];

	private $jobStatesOK = ['T', 'D'];
	private $jobStatesWarning = ['W'];
	private $jobStatesError = ['E', 'e', 'f', 'I'];
	private $jobStatesCancel = ['A'];
	private $jobStatesRunning = ['C', 'R', 'B', 'F', 'S', 'm', 'M', 's', 'j', 'c', 'd', 't', 'p', 'i', 'a', 'l', 'L'];

	private $runningJobStates = ['C', 'R'];

	private $components = [
		'dir' => [
			'full_name' => 'Director',
			'url_name' => 'director',
			'main_resource' => 'Director'
		],
		'sd' => [
			'full_name' => 'Storage Daemon',
			'url_name' => 'storage',
			'main_resource' => 'Storage'
		],
		'fd' => [
			'full_name' => 'File Daemon',
			'url_name' => 'client',
			'main_resource' => 'FileDaemon'
		],
		'bcons' => [
			'full_name' => 'Console',
			'url_name' => 'console',
			'main_resource' => 'Director'
		]
	];

	private $replace_opts = [
		'always',
		'ifnewer',
		'ifolder',
		'never'
	];

	public function getJobLevels()
	{
		return $this->jobLevels;
	}

	public function getJobState($jobStateLetter = null)
	{
		$state = null;
		if (is_null($jobStateLetter)) {
			$state = $this->jobStates;
		} else {
			$state = array_key_exists($jobStateLetter, $this->jobStates) ? $this->jobStates[$jobStateLetter] : null;
		}
		return $state;
	}

	public function isJobRunning($jobstatus)
	{
		$running_job_states = $this->getRunningJobStates();
		return in_array($jobstatus, $running_job_states);
	}

	public function getRunningJobStates()
	{
		return $this->runningJobStates;
	}

	public function getComponents()
	{
		return array_keys($this->components);
	}

	public function getMainComponentResource($type)
	{
		$resource = null;
		if (array_key_exists($type, $this->components)) {
			$resource = $this->components[$type]['main_resource'];
		}
		return $resource;
	}

	public function getComponentFullName($type)
	{
		$name = '';
		if (array_key_exists($type, $this->components)) {
			$name = $this->components[$type]['full_name'];
		}
		return $name;
	}

	public function getComponentUrlName($type)
	{
		$name = '';
		if (key_exists($type, $this->components)) {
			$name = $this->components[$type]['url_name'];
		}
		return $name;
	}

	public function getJobStatesByType($type)
	{
		$statesByType = [];
		$states = [];
		switch ($type) {
			case 'ok':
				$states = $this->jobStatesOK;
				break;
			case 'warning':
				$states = $this->jobStatesWarning;
				break;
			case 'error':
				$states = $this->jobStatesError;
				break;
			case 'cancel':
				$states = $this->jobStatesCancel;
				break;
			case 'running':
				$states = $this->jobStatesRunning;
				break;
		}

		for ($i = 0; $i < count($states); $i++) {
			$statesByType[$states[$i]] = $this->getJobState($states[$i]);
		}

		return $statesByType;
	}

	/*
	 * @TODO: Move it to separate validation module.
	 */
	public function isValidJobLevel($jobLevel)
	{
		return key_exists($jobLevel, $this->getJobLevels());
	}

	public function isValidJobType($job_type)
	{
		return key_exists($job_type, $this->job_types);
	}

	public function isValidName($name)
	{
		return (preg_match('/^[\w:\.\-\s]{1,127}$/', $name) === 1);
	}

	public function isValidState($state)
	{
		return (preg_match('/^[\w\-]+$/', $state) === 1);
	}

	public function isValidInteger($num)
	{
		return (preg_match('/^\d+$/', $num) === 1);
	}

	public function isValidBoolean($val)
	{
		return (preg_match('/^(yes|no|1|0|true|false)$/i', $val) === 1);
	}

	public function isValidBooleanTrue($val)
	{
		return (preg_match('/^(yes|1|true)$/i', $val) === 1);
	}

	public function isValidBooleanFalse($val)
	{
		return (preg_match('/^(no|0|false)$/i', $val) === 1);
	}

	public function isValidId($id)
	{
		return (preg_match('/^\d+$/', $id) === 1);
	}

	public function isValidPath($path)
	{
		return (preg_match('/^[\p{L}\p{N}\p{Z}\p{Sc}\p{Pd}\[\]\-\'\/\\(){}:.#~_,+!$]{0,10000}$/u', $path) === 1);
	}

	public function isValidFilename($path)
	{
		return (preg_match('/^[\p{L}\p{N}\p{Z}\p{Sc}\p{Pd}\[\]\-\'\\(){}:.#~_,+!$]{0,1000}$/u', $path) === 1);
	}

	public function isValidReplace($replace)
	{
		return in_array($replace, $this->replace_opts);
	}

	public function isValidIdsList($list)
	{
		return (preg_match('/^[\d,]+$/', $list) === 1);
	}

	public function isValidBvfsPath($path)
	{
		return (preg_match('/^b2\d+$/', $path) === 1);
	}

	public function isValidBDateAndTime($time)
	{
		return (preg_match('/^\d{4}-\d{2}-\d{2} \d{1,2}:\d{2}:\d{2}$/', $time) === 1);
	}

	public function isValidRange($range)
	{
		return (preg_match('/^[\d\-\,]+$/', $range) === 1);
	}

	public function isValidAlphaNumeric($str)
	{
		return (preg_match('/^[a-zA-Z0-9]+$/', $str) === 1);
	}

	public function isValidListFilesType($type)
	{
		return (preg_match('/^(all|deleted)$/', $type) === 1);
	}

	public function isValidOutput($type)
	{
		return (preg_match('/^(raw|json)$/', $type) === 1);
	}

	public function escapeCharsToConsole($path)
	{
		return preg_replace('/([$])/', '\\\${1}', $path);
	}

	public function objectToArray($data)
	{
		return json_decode(json_encode($data), true);
	}

	public function findJobIdStartedJob($output)
	{
		$jobid = null;
		$output = array_reverse($output); // jobid is ussually at the end of output
		for ($i = 0; $i < count($output); $i++) {
			if (preg_match('/^Job queued\.\sJobId=(?P<jobid>\d+)$/', $output[$i], $match) === 1) {
				$jobid = $match['jobid'];
				break;
			}
		}
		return $jobid;
	}
}
