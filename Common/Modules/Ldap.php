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

/**
 * The module supports basic operations on LDAP server.
 * To work it uses php-ldap module.
 * @see https://www.php.net/manual/en/book.ldap.php
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class Ldap extends CommonModule
{
	/**
	 * Authentication methods.
	 */
	public const AUTH_METHOD_ANON = 'anonymous';
	public const AUTH_METHOD_SIMPLE = 'simple';

	/**
	 * LDAP protocol types.
	 */
	public const PROTOCOL_PLAIN = 'ldap';
	public const PROTOCOL_SSL = 'ldaps';

	/**
	 * LDAP attributes.
	 */
	public const DN_ATTR = 'dn';

	/**
	 * Stores LDAP connection parameters.
	 */
	private $params = [];

	/**
	 * Stores last error message if error occurs.
	 */
	private $error = '';

	/**
	 * Stores obligatory (required) parameters.
	 */
	private $req_params = [
		'address',
		'port',
		'protocol_ver',
		'auth_method'
	];

	/**
	 * Stores LDAP connection resource.
	 */
	private $conn;

	/**
	 * Connect to LDAP server.
	 * Note, it is used to initialize connection parameters, but itself it
	 * doesn't open any LDAP conection.
	 *
	 * @return false|resource LDAP connection resource or false if any error occurs
	 */
	public function connect()
	{
		$ldapuri = $this->getLdapUri();
		$conn = @ldap_connect($ldapuri);
		$this->conn = $conn;
		if ($conn) {
			$this->setConnectionOpts();
		} else {
			$this->setLdapError();
		}
		return $conn;
	}

	/**
	 * Binds to LDAP directory.
	 * Supported are anonymous bind and bind with credentials (RDN/password).
	 * Note, if an error occurs, the error message is available in
	 * the error property.
	 *
	 * @param string $rdn distinguished name
	 * @param string $password user password
	 * @return bool true on successfull connection, false otherwise
	 */
	public function bind($rdn = null, $password = null)
	{
		$success = false;
		if ($this->conn) {
			$success = @ldap_bind($this->conn, $rdn, $password);
		}
		if (!$success) {
			$this->setLdapError();
		}
		return $success;
	}

	/**
	 * Set LDAP parameters (both connection and attributes' parameters).
	 * Parameters are set after successful validation.
	 *
	 * @param array $param parameters to work with LDAP
	 * @param array $params
	 */
	public function setParams(array $params)
	{
		if ($this->validateConnectionParams($params)) {
			$this->params = $params;
		}
	}

	/**
	 * Set LDAP connection specific options.
	 *
	 */
	public function setConnectionOpts()
	{
		ldap_set_option($this->conn, LDAP_OPT_PROTOCOL_VERSION, $this->params['protocol_ver']);
		ldap_set_option($this->conn, LDAP_OPT_REFERRALS, 0);
		if (key_exists('starttls', $this->params) && $this->params['starttls'] == 1) {
			@ldap_start_tls($this->conn);
		}
	}

	/**
	 * Administrative bind action.
	 * This method should be used always to connect to LDAP (anonymous or with credentials).
	 * Note, if an error occurs, the error message is available in
	 * the error property.
	 *
	 * @return bool true on successfull connection, false otherwise
	 */
	public function adminBind()
	{
		$success = false;
		if ($this->connect()) {
			if ($this->params['auth_method'] === self::AUTH_METHOD_ANON) {
				$success = $this->bind();
			} elseif ($this->params['auth_method'] === self::AUTH_METHOD_SIMPLE) {
				$success = $this->bind($this->params['bind_dn'], $this->params['bind_password']);
			}
		}
		return $success;
	}

	/**
	 * Login with username and password.
	 * Note, if an error occurs, the error message is available in
	 * the error property.
	 *
	 * @param string $username username to login
	 * @param string $password password to login
	 * @return bool true if login finished successfully, false otherwise
	 */
	public function login($username, $password)
	{
		$success = false;
		if ($this->adminBind() && $password !== '') {
			$filter = $this->getFilter($this->params['user_attr'], $username);
			if (key_exists('filters', $this->params) && !empty($this->params['filters'])) {
				$filter = $this->joinFiltersAnd([$this->params['filters'], $filter]);
			}
			$user = $this->search($this->params['base_dn'], $filter);
			$cnt = ldap_count_entries($this->conn, $user);
			if ($cnt > 0) {
				$entry = ldap_first_entry($this->conn, $user);
				if ($entry) {
					$user_dn = ldap_get_dn($this->conn, $entry);
					$success = $this->bind($user_dn, $password);
				}
			}
		}
		return $success;
	}

	/**
	 * Search in LDAP directory using filters.
	 * Note, if an error occurs, the error message is available in
	 * the error property.
	 *
	 * @param string $base_dn base distinguished name
	 * @param string $filter the search filter, ex. '(objectClass=inetOrgPerson)'
	 * @param array $attr required object attributes
	 * @return resource search result identifier or false otherwise
	 */
	public function search($base_dn, $filter, $attr = ['*'])
	{
		$search = @ldap_search($this->conn, $base_dn, $filter, $attr, 0, 0, 0);
		if (!$search) {
			$this->setLdapError();
		}
		return $search;
	}

	/**
	 * Find and get users by filter and attributes.
	 * Note, if an error occurs, the error message is available in
	 * the error property.
	 *
	 * @param string $filter the search filter, ex. '(objectClass=inetOrgPerson)'
	 * @param array $attr required object attributes
	 */
	public function findUserAttr($filter, $attr = ['*'])
	{
		$user_attr = [];
		if ($this->adminBind()) {
			if (key_exists('filters', $this->params) && !empty($this->params['filters'])) {
				$filter = $this->joinFiltersAnd([$this->params['filters'], $filter]);
			}
			$search = $this->search($this->params['base_dn'], $filter, $attr);
			if ($search) {
				$user_attr = ldap_get_entries($this->conn, $search);
			}
		}
		return $user_attr;
	}

	/**
	 * Get LDAP URI used to connecting to LDAP server.
	 *
	 * @return string full LDAP URI (ex. ldap://host:port)
	 */
	public function getLdapUri()
	{
		$protocol = self::PROTOCOL_PLAIN;
		if (key_exists('ldaps', $this->params) && $this->params['ldaps'] == 1) {
			$protocol = self::PROTOCOL_SSL;
		}
		return sprintf(
			'%s://%s:%d',
			$protocol,
			$this->params['address'],
			$this->params['port']
		);
	}

	/**
	 * Set LDAP errors in error property.
	 * Used to get the most information from LDAP server.
	 *
	 */
	public function setLdapError()
	{
		ldap_get_option($this->conn, LDAP_OPT_DIAGNOSTIC_MESSAGE, $error);
		$emsg = sprintf(
			'Error: %d: %s. %s',
			ldap_errno($this->conn),
			ldap_error($this->conn),
			$error
		);
		$this->error = $emsg;
		Logging::log(
			Logging::CATEGORY_EXTERNAL,
			$emsg
		);
	}

	/**
	 * Get LDAP error message.
	 * No error is represented by empty string.
	 *
	 * @return string error message
	 */
	public function getLdapError()
	{
		return $this->error;
	}

	/**
	 * Validate LDAP connection parameters.
	 *
	 * @param array $params connection parameters
	 * @return false if any of parameters is invalid
	 */
	public function validateConnectionParams(array $params)
	{
		$valid = true;
		for ($i = 0; $i < count($this->req_params); $i++) {
			if (!key_exists($this->req_params[$i], $params)) {
				$valid = false;
			}
		}
		return $valid;
	}

	/**
	 * Get simple key/value filter.
	 * Filters are used to search on LDAP server.
	 *
	 * @param string $key search key
	 * @param string $value search value
	 * @return formatted string in search filter
	 */
	public function getFilter($key, $value)
	{
		return sprintf('(%s=%s)', $key, $value);
	}

	/**
	 * Join filters using AND operator.
	 * Filters are used to search on LDAP server.
	 *
	 * @param array $filters filters to join
	 * @return formatted string with joined filters
	 */
	public function joinFiltersAnd(array $filters): string
	{
		$filters = implode('', $filters);
		return sprintf('(&%s)', $filters);
	}
}
