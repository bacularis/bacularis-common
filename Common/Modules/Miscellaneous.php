<?php
/*
 * Bacularis - Bacula web interface
 *
 * Copyright (C) 2021-2026 Marcin Haba
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

	/**
	 * Sort order types.
	 */
	public const ORDER_ASC = 'asc';
	public const ORDER_DESC = 'desc';

	/**
	 * Supported web servers definition.
	 */
	public const WEB_SERVERS = [
		'apache' => ['id' => 'apache', 'name' => 'Apache'],
		'nginx' => ['id' => 'nginx', 'name' => 'Nginx'],
		'lighttpd' => ['id' => 'lighttpd', 'name' => 'Lighttpd']
	];

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

	private const JOB_LEVELS = [
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

	private $resources = [
		'dir' => [
			'Director',
			'JobDefs',
			'Job',
			'Client',
			'Storage',
			'Catalog',
			'Schedule',
			'FileSet',
			'Pool',
			'Messages',
			'Console',
			'Statistics'
		],
		'sd' => [
			'Storage',
			'Director',
			'Device',
			'Autochanger',
			'Messages',
			'Cloud',
			'Statistics'
		],
		'fd' => [
			'FileDaemon',
			'Director',
			'Messages',
			'Schedule',
			'Console',
			'Statistics'
		],
		'bcons' => [
			'Director',
			'Console'
		]
	];

	private $replace_opts = [
		'always',
		'ifnewer',
		'ifolder',
		'never'
	];


	/**
	 * Volume status list possible to set by user via Bconsole.
	 */
	public const VOLSTATUS_USER = [
		'Append',
		'Archive',
		'Disabled',
		'Full',
		'Used',
		'Cleaning',
		'Read-Only'
	];

	public function getJobLevels()
	{
		return self::JOB_LEVELS;
	}

	public function getJobLevelLong($level)
	{
		return (self::JOB_LEVELS[$level] ?? '');
	}

	public static function getJobLevelShort(string $level_long): string
	{
		$levels = array_flip(self::JOB_LEVELS);
		return ($levels[$level_long] ?? '');
	}

	public function getJobState($jobStateLetter = null)
	{
		$state = null;
		if (is_null($jobStateLetter)) {
			$state = $this->jobStates;
		} else {
			$state = key_exists($jobStateLetter, $this->jobStates) ? $this->jobStates[$jobStateLetter] : null;
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

	/**
	 * Validate component type by its short name.
	 *
	 * @param mixed $comp component short name
	 * @return bool true if component type is supported, false otherwise
	 */
	public function isValidComponentType($comp): bool
	{
		if (!is_string($comp)) {
			return false;
		}

		$components = $this->getComponents();
		return in_array($comp, $components, true);
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

	public function getResources($component = null)
	{
		$resources = null;
		if (key_exists($component, $this->resources)) {
			$resources = $this->resources[$component];
		} else {
			$resources = $this->resources;
		}
		return $resources;
	}

	/**
	 * Validate resource type for a component.
	 * Resource type matching is case-insensitive.
	 *
	 * @param mixed $comp component short name
	 * @param mixed $res resource type
	 * @return bool true if resource type is supported by component, false otherwise
	 */
	public function isValidResourceType($comp, $res): bool
	{
		if (!$this->isValidComponentType($comp) || !is_string($res)) {
			return false;
		}

		$resources = $this->getResources($comp);
		for ($i = 0; $i < count($resources); $i++) {
			$resource = $this->setResourceToAPIForm($resources[$i]);
			if (strcasecmp($resource, $res) === 0) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Validate resource permission map keys for a component.
	 *
	 * @param string $component component name
	 * @param array $permissions resource permission map
	 * @return bool true if all resource keys are supported, false otherwise
	 */
	public function isValidResourcePermissions(string $component, array $permissions): bool
	{
		if (!key_exists($component, $this->resources)) {
			return false;
		}

		$resources = $this->getResources($component);
		$permission_resources = array_keys($permissions);
		for ($i = 0; $i < count($permission_resources); $i++) {
			if (!is_string($permission_resources[$i]) || !in_array($permission_resources[$i], $resources, true)) {
				return false;
			}
		}
		return true;
	}

	public function setResourceToAPIForm(string $resource): string
	{
		if ($resource == 'FileSet') {
			$resource = 'Fileset';
		}
		return $resource;
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

	/**
	 * Get job type name.
	 *
	 * @param string $type job type letter
	 * @return string job type name
	 */
	public function getJobTypeName(string $type): string
	{
		return $this->job_types[$type] ?? $type;
	}

	/*
	 * @TODO: Move it to separate validation module.
	 */
	public function isValidJobLevel($jobLevel)
	{
		return key_exists($jobLevel, self::JOB_LEVELS);
	}

	public function isValidJobType($job_type)
	{
		return key_exists($job_type, $this->job_types);
	}

	public function isValidName($name)
	{
		return (is_string($name) && preg_match('/^[\w:\.\-\s]{1,127}$/', $name) === 1);
	}

	/**
	 * Validate certificate validity period in days.
	 *
	 * @param mixed $days certificate validity period
	 * @return bool true if the period is in the 1..36500 range, otherwise false
	 */
	public function isValidCertificateDays($days): bool
	{
		if (!is_string($days) && !is_int($days)) {
			return false;
		}

		$days = (string) $days;
		if (preg_match('/^[0-9]{1,5}$/D', $days) !== 1) {
			return false;
		}

		$days_no = (int) $days;
		return $days_no >= 1 && $days_no <= 36500;
	}

	/**
	 * Validate certificate country code.
	 *
	 * An empty value is accepted because this certificate subject field is
	 * optional in the Bacularis form.
	 *
	 * @param string $country_code certificate country code
	 * @return bool true if the country code is valid, otherwise false
	 */
	public function isValidCertificateCountry(string $country_code): bool
	{
		return $country_code === '' || preg_match('/^[A-Za-z]{2}$/D', $country_code) === 1;
	}

	/**
	 * Validate certificate email address.
	 *
	 * An empty value is accepted because the self-signed certificate email
	 * field is optional.
	 *
	 * @param string $email certificate email address
	 * @return bool true if the email address is valid, otherwise false
	 */
	public function isValidCertificateEmail(string $email): bool
	{
		if ($email === '') {
			return true;
		}
		if (!$this->isValidCertificateTextValue($email)) {
			return false;
		}
		return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
	}

	/**
	 * Validate certificate common name used as a host or IP address.
	 *
	 * @param string $common_name certificate common name
	 * @return bool true if the common name is valid, otherwise false
	 */
	public function isValidCertificateCommonName(string $common_name): bool
	{
		if ($common_name === '' || strlen($common_name) > 253) {
			return false;
		}
		if (filter_var($common_name, FILTER_VALIDATE_IP) !== false) {
			return true;
		}

		$host = $common_name;
		if (strpos($common_name, '*.') === 0) {
			$host = substr($common_name, 2);
			if ($host === '' || strpos($host, '.') === false) {
				return false;
			}
		}
		return filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
	}

	/**
	 * Check if a value contains an ASCII control character.
	 *
	 * @param string $value value to check
	 * @return bool true if the value contains an ASCII control character, otherwise false
	 */
	public static function isASCIControlChar(string $value): bool
	{
		return preg_match('/[\x00-\x1F\x7F]/', $value) === 1;
	}

	/**
	 * Validate a Web Access token.
	 *
	 * @param string $token Web Access token
	 * @return bool true if the token is valid, otherwise false
	 */
	public static function isValidWebAccessToken(string $token): bool
	{
		return preg_match('/^[a-zA-Z0-9\-_]+$/D', $token) === 1;
	}

	/**
	 * Validate a human-readable certificate subject value.
	 *
	 * Control characters and OpenSSL slash-form record delimiters are not
	 * accepted. Printable punctuation remains valid and is protected separately
	 * at the POSIX shell argument boundary.
	 *
	 * @param string $value certificate subject value
	 * @return bool true if the subject value is valid, otherwise false
	 */
	public function isValidCertificateTextValue(string $value): bool
	{
		if (self::isASCIControlChar($value)) {
			return false;
		}
		return strpbrk($value, '/\\') === false;
	}

	/**
	 * Validate local system username used by su.
	 *
	 * @param string $username local system username
	 * @return bool true if the username is valid, otherwise false
	 */
	public function isValidSystemUsername(string $username): bool
	{
		return preg_match('/^[A-Za-z0-9_.][A-Za-z0-9_.-]{0,254}$/D', $username) === 1;
	}

	/**
	 * Validate SSH destination host or address.
	 *
	 * It accepts DNS names, concrete SSH config aliases, IPv4 addresses and
	 * IPv6 addresses in plain or bracketed form.
	 *
	 * @param string $host destination host or address
	 * @return bool true if the host is valid, otherwise false
	 */
	public function isValidSSHHost(string $host): bool
	{
		if ($host === '' || strlen($host) > 253) {
			return false;
		}

		$ip = $host;
		if ($host[0] === '[' && substr($host, -1) === ']') {
			$ip = substr($host, 1, -1);
		}
		if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
			return true;
		}

		$zone_pos = strrpos($ip, '%');
		if ($zone_pos !== false) {
			$ipv6 = substr($ip, 0, $zone_pos);
			$zone = substr($ip, $zone_pos + 1);
			$is_ipv6 = filter_var($ipv6, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
			$is_zone = preg_match('/^[A-Za-z0-9_.-]+$/D', $zone) === 1;
			return $is_ipv6 && $is_zone;
		}

		return preg_match('/^[A-Za-z0-9_](?:[A-Za-z0-9._-]{0,251}[A-Za-z0-9_.])?$/D', $host) === 1;
	}

	/**
	 * Validate SSH username.
	 *
	 * @param string $username SSH account name
	 * @return bool true if the username is valid, otherwise false
	 */
	public function isValidSSHUsername(string $username): bool
	{
		return preg_match('/^[A-Za-z0-9_.][A-Za-z0-9_.-]{0,254}$/D', $username) === 1;
	}

	/**
	 * Validate SSH port.
	 *
	 * @param string $port SSH port
	 * @return bool true if the port is in the 1..65535 range, otherwise false
	 */
	public function isValidSSHPort(string $port): bool
	{
		if (preg_match('/^[0-9]{1,5}$/D', $port) !== 1) {
			return false;
		}
		$port_number = (int) $port;
		return $port_number >= 1 && $port_number <= 65535;
	}

	public function filterValidNameList(array $name_list): array
	{
		return array_filter($name_list, fn ($item) => $this->isValidName($item));
	}

	public function isValidSimpleName($name)
	{
		return (preg_match('/^[\w.\-]{1,127}$/', $name) === 1);
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

	public function isValidOrderType($val)
	{
		$val = strtolower($val);
		return in_array($val, [self::ORDER_ASC, self::ORDER_DESC]);
	}

	public function isValidPath($path)
	{
		return (preg_match('/^[\p{L}\p{N}\p{Z}\p{Sc}\p{Pd}\[\]\-\'\/\\(){}:.#~_,+!$%=]{0,10000}$/u', $path) === 1);
	}

	/**
	 * Validate a restore Where value before it can be substituted into a bpipe
	 * writer command.
	 *
	 * @param mixed $where restore Where value
	 * @return bool true if the value is valid, otherwise false
	 */
	public function isValidRestoreWhere($where): bool
	{
		return is_string($where) && $this->isValidPath($where) && strpos($where, '$') === false;
	}

	public function isValidFilename($path)
	{
		return (preg_match('/^[\p{L}\p{N}\p{Z}\p{Sc}\p{Pd}\[\]\-\'\\(){}:.#~_,+!$=]{0,1000}$/u', $path) === 1);
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

	public function isValidDiffMethod(string $method): bool
	{
		return (preg_match('/^(a|b)_(and|until|not)_(a|b)$/', $method) === 1);
	}

	public function isValidOutput($type)
	{
		return (preg_match('/^(raw|json)$/', $type) === 1);
	}

	public static function isWindowsPath(string $path): bool
	{
		return (preg_match('/^[A-Z]:[\\\\\/]/i', $path) === 1);
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

	public function prepareResourcePermissionsConfig($config)
	{
		$res_perm_fn = function ($key, $item) {
			return ['resource' => $key, 'perm' => $item];
		};

		// Director resource permissions
		$perm = [];
		$dir_res = $this->getResources('dir');
		for ($i = 0; $i < count($dir_res); $i++) {
			$perm[$dir_res[$i]] = 'rw'; // read write is default value
		}
		if (key_exists('dir_res_perm', $config)) {
			$perm = array_merge($perm, $config['dir_res_perm']);
		}
		$dir_res_perm = array_map(
			$res_perm_fn,
			array_keys($perm),
			array_values($perm)
		);

		// Storage resource permissions
		$perm = [];
		$sd_res = $this->getResources('sd');
		for ($i = 0; $i < count($sd_res); $i++) {
			$perm[$sd_res[$i]] = 'rw'; // read write is default value
		}
		if (key_exists('sd_res_perm', $config)) {
			$perm = array_merge($perm, $config['sd_res_perm']);
		}
		$sd_res_perm = array_map(
			$res_perm_fn,
			array_keys($perm),
			array_values($perm)
		);

		// Client resource permissions
		$perm = [];
		$fd_res = $this->getResources('fd');
		for ($i = 0; $i < count($fd_res); $i++) {
			$perm[$fd_res[$i]] = 'rw'; // read write is default value
		}
		if (key_exists('fd_res_perm', $config)) {
			$perm = array_merge($perm, $config['fd_res_perm']);
		}
		$fd_res_perm = array_map(
			$res_perm_fn,
			array_keys($perm),
			array_values($perm)
		);

		// Bconsole resource permissions
		$perm = [];
		$bcons_res = $this->getResources('bcons');
		for ($i = 0; $i < count($bcons_res); $i++) {
			$perm[$bcons_res[$i]] = 'rw'; // read write is default value
		}
		if (key_exists('bcons_res_perm', $config)) {
			$perm = array_merge($perm, $config['bcons_res_perm']);
		}
		$bcons_res_perm = array_map(
			$res_perm_fn,
			array_keys($perm),
			array_values($perm)
		);
		return [
			'dir_res_perm' => $dir_res_perm,
			'sd_res_perm' => $sd_res_perm,
			'fd_res_perm' => $fd_res_perm,
			'bcons_res_perm' => $bcons_res_perm
		];
	}

	/**
	 * Sort array by given property.
	 * Supported ascending and descending sorting.
	 * Note: for many items, it can be a bit slow
	 *
	 * @param array $result array with results to sort
	 * @param string $order_by order property to sort
	 * @param string $order_type order type (asc or desc)
	 * @param int|string $key if we sort nested array, it is key that stores data to sort
	 */
	public static function sortByProperty(&$result, $order_by, $order_type, $key = null)
	{
		$order_by = strtolower($order_by);
		$order_type = strtolower($order_type);
		$sort_by_func = function ($a, $b) use ($order_by, $order_type, $key) {
			$cmp = 0;
			if (is_string($key) || is_int($key)) {
				$a = $a[$key];
				$b = $b[$key];
			}
			if ($a[$order_by] != $b[$order_by]) {
				$cmp = strnatcasecmp($a[$order_by], $b[$order_by]);
				if ($order_type === self::ORDER_DESC) {
					$cmp = -$cmp;
				}
			}
			return $cmp;
		};
		usort($result, $sort_by_func);
	}

	public function maskPassword($pwd)
	{
		return preg_replace('/./', '*', $pwd);
	}

	public function maskPasswordParams(array $params)
	{
		for ($i = 0; $i < count($params); $i++) {
			if (preg_match('/(?P<param>(pass(word|phrase)?|pwd))\s?[= ]\s?(?P<pwd>[^ ]+)/i', $params[$i], $match) == 1) {
				$pwd_mask = $this->maskPassword($match['pwd']);
				$params[$i] = str_replace($match['pwd'], $pwd_mask, $params[$i]);
			}
		}
		return $params;
	}

	/**
	 * Detect and get the current web server id.
	 *
	 * @return string $id web server identifier or empty string if detection was not possible
	 */
	public function detectWebServer(): string
	{
		$id = '';
		if (stripos($_SERVER['SERVER_SOFTWARE'], self::WEB_SERVERS['nginx']['name']) !== false) {
			$id = self::WEB_SERVERS['nginx']['id'];
		} elseif (stripos($_SERVER['SERVER_SOFTWARE'], self::WEB_SERVERS['lighttpd']['name']) !== false) {
			$id = self::WEB_SERVERS['lighttpd']['id'];
		} elseif (stripos($_SERVER['SERVER_SOFTWARE'], self::WEB_SERVERS['apache']['name']) !== false || stripos($_SERVER['SERVER_SOFTWARE'], 'httpd') !== false) {
			$id = self::WEB_SERVERS['apache']['id'];
		}
		return $id;
	}

	/**
	 * Encode base64url string.
	 *
	 * @param string $str string to encode in base64url
	 * @param bool $b64 if true, it assumes that $str is already b64 encoded and it enables to covert b64 => b64url
	 */
	public static function encodeBase64URL(string $str, bool $b64 = false): string
	{
		if (!$b64) {
			$str = base64_encode($str);
		}
		return self::base64ToBase64Url($str);
	}

	/**
	 * Decode base64url string.
	 *
	 * @param string $b64url base64url encoded string
	 * @param string decoded string
	 */
	public static function decodeBase64URL(string $b64url): string
	{
		$b64 = self::base64UrlToBase64($b64url);
		return base64_decode($b64);
	}

	/**
	 * Convert base64url string into base64.
	 *
	 * @param string $b64url base64url encoded string
	 * @return string base64 encoded string
	 */
	public static function base64URLToBase64(string $b64url): string
	{
		$b64 = preg_replace(
			['/\-/', '/_/'],
			['+', '/'],
			$b64url
		);
		while (strlen($b64) % 4 != 0) {
			$b64 .= '=';
		}
		return $b64;
	}

	/**
	 * Convert base64 string into base64url.
	 *
	 * @param string $b64 base64 encoded string
	 * @return string base64url encoded string
	 */
	public static function base64ToBase64URL(string $b64): string
	{
		$b64url = preg_replace(
			['/\+/', '/\//', '/=/'],
			['-', '_', ''],
			$b64
		);
		return $b64url;
	}

	/**
	 * Convert hexadecimal value into base64 encoded value.
	 *
	 * @param string $hex hexadecimal value
	 * @return string base64-encoded value
	 */
	public static function hexToBase64(string $hex): string
	{
		if ((strlen($hex) % 2) == 1) {
			$hex = '0' . $hex;
		}
		$ret = '';
		foreach (str_split($hex, 2) as $pair) {
			$ret .= chr(hexdec($pair));
		}
		return base64_encode($ret);
	}

	/**
	 * String slashes from quotes.
	 *
	 * @param string $value string value to strip
	 * @return string stripped string value
	 */
	public static function stripQuotes(string $value): string
	{
		return str_replace('\\"', '"', $value);
	}

	/**
	 * Search and filter simple list.
	 * Matches list items against wildcard pattern.
	 *
	 * @param array $list list to filter
	 * @param string $pattern wildcard pattern
	 */
	public static function filterList(array &$list, string $pattern): void
	{
		$filter_cb = function ($item) use ($pattern) {
			if (is_array($item)) {
				$assoc_keys = array_filter(array_keys($item), 'is_string');
				if (count($assoc_keys) > 0) {
					// associative array, not supported, skip it
					return false;
				}
				$found = false;
				for ($i = 0; $i < count($item); $i++) {
					if (fnmatch($pattern, $item[$i], FNM_NOESCAPE | FNM_CASEFOLD)) {
						$found = true;
						break;
					}
				}
				return $found;
			} else {
				return fnmatch($pattern, $item, FNM_NOESCAPE | FNM_CASEFOLD);
			}
		};

		$list = array_filter(
			$list,
			$filter_cb
		);
		$list = array_values($list);
	}

	/**
	 * Get human readable mode/attributes (ex. drwx-r-xr-x).
	 *
	 * @param int $dmode mode value in decimal LStat form
	 * @return string mode in human readable form
	 */
	public static function get_human_mode($dmode)
	{
		$ts = [
			0140000 => 'ssocket',
			0120000 => 'llink',
			0100000 => '-file',
			0060000 => 'bblock',
			0040000 => 'ddir',
			0020000 => 'cchar',
			0010000 => 'pfifo'
		];

		$p = $dmode;
		$t = decoct($dmode & 0170000); // File Encoding Bit
		$mode = (key_exists(octdec($t), $ts)) ? $ts[octdec($t)][0] : 'u';
		$mode .= (($p & 0x0100) ? 'r' : '-') . (($p & 0x0080) ? 'w' : '-');
		$mode .= (($p & 0x0040) ? (($p & 0x0800) ? 's' : 'x') : (($p & 0x0800) ? 'S' : '-'));
		$mode .= (($p & 0x0020) ? 'r' : '-') . (($p & 0x0010) ? 'w' : '-');
		$mode .= (($p & 0x0008) ? (($p & 0x0400) ? 's' : 'x') : (($p & 0x0400) ? 'S' : '-'));
		$mode .= (($p & 0x0004) ? 'r' : '-') . (($p & 0x0002) ? 'w' : '-');
		$mode .= (($p & 0x0001) ? (($p & 0x0200) ? 't' : 'x') : (($p & 0x0200) ? 'T' : '-'));
		return $mode;
	}

	/**
	 * HTML value helper.
	 * It converts special characters to HTML entities that are save
	 * to use in HTML.
	 *
	 * @param null|string $value value to Convert
	 * @return string converted value
	 */
	public static function html_value($value = '')
	{
		return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}

	/**
	 * JSON value helper.
	 * It encodes value to JSON safe to use in JavaScript context inside HTML.
	 *
	 * @param mixed $value value to encode
	 * @return string JSON encoded value
	 */
	public static function json_value($value): string
	{
		$json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
		if ($json === false) {
			$json = 'null';
		}
		return $json;
	}

	/**
	 * Sort associative array recursively.
	 *
	 * List item order is preserved.
	 *
	 * @param array $data array to sort
	 */
	public static function sortArrayRecursive(array &$data): void
	{
		foreach ($data as &$value) {
			if (is_array($value)) {
				self::sortArrayRecursive($value);
			}
		}
		unset($value);

		$is_list = empty($data) || array_keys($data) === range(0, count($data) - 1);
		if (!$is_list) {
			ksort($data);
		}
	}
}
