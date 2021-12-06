<?php
/*
 * Bacula(R) - The Network Backup Solution
 * Baculum   - Bacula web interface
 *
 * Copyright (C) 2013-2021 Kern Sibbald
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

Prado::using('Application.Common.Class.CommonModule');

/**
 * Generic OAuth2 abstraction.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 * @package Baculum Common
 */
abstract class OAuth2 extends CommonModule {

	/**
	 * RFC6749 standard does not include information about allowed characters
	 * in client identifier field. Here is client identifier that may contain
	 * alphanumeric strings, hyphens and underscores. The client_id field have
	 * to length equal 32 characters.
	 * 
	 * @see http://tools.ietf.org/html/rfc6749#section-2.2
	 */
	const CLIENT_ID_PATTERN = '^[a-zA-Z0-9\-_]{32}$';

	/**
	 * Client secret may contain any character that is not a whitespace character.
	 * Secret length range is between 6 and 50 characters.
	 */
	const CLIENT_SECRET_PATTERN = '^\S{6,50}$';

	/**
	 * Very basic redirect URI validation pattern.
	 *
	 * @see https://tools.ietf.org/html/rfc3986#appendix-B
	 */
	const REDIRECT_URI_PATTERN = '^(([^:\/?#]+):)?(\/\/([^\/?#]*))?([^?#]*)(\?([^#]*))?(#(.*))?$';

	/**
	 * Authorization ID (known also as 'authorization_code') regular expression pattern
	 * allow to set hexadecimal value of the authorization ID with length equal 40 chars.
	 * 
	 * @see http://tools.ietf.org/html/rfc6749#section-1.3.1
	 */
	const AUTHORIZATION_ID_PATTERN = '^[a-fA-F0-9]{40}$';

	/**
	 * Pattern for access token.
	 */
	const ACCESS_TOKEN_PATTERN = '^[a-fA-F0-9]{40}$';

	/**
	 * Pattern for refresh token.
	 */
	const REFRESH_TOKEN_PATTERN = '^[a-fA-F0-9]{40}$';

	/**
	 * Expiration time in seconds for authorization identifier.
	 * Maximum expiration time should be smaller than 10 minutes.
	 * 
	 * Temportary set to 7 seconds for tests purposes.
	 * In production the value SHOULD BE changed.
	 */
	const AUTHORIZATION_ID_EXPIRES_TIME = 7;

	/**
	 * Expiration time in seconds for access token.
	 * 
	 * It is 1 hour by default.
	 * In production the value can be changed.
	 */
	const ACCESS_TOKEN_EXPIRES_TIME = 3600;

	/**
	 * Scope pattern.
	 */
	const SCOPE_PATTERN = '^[a-zA-Z0-9:]+$';

	/**
	 * State pattern.
	 */
	const STATE_PATTERN = '^[a-zA-Z0-9]{16}$';

	/**
	 * HTTP headers.
	 */
	const HEADER_HTTP_FOUND = 'HTTP/1.1 302 Found';
	const HEADER_BAD_REQUEST = 'HTTP/1.1 400 Bad Request';
	const HEADER_UNAUTHORIZED = 'HTTP/1.1 401 Unauthorized';

	/**
	 * Possible authorization errors.
	 * 
	 * @see http://tools.ietf.org/html/rfc6749#section-4.1.2.1
	 */
	const AUTHORIZATION_ERROR_ACCESS_DENIED = 'access_denied';
	const AUTHORIZATION_ERROR_INVALID_REQUEST = 'invalid_request';
	const AUTHORIZATION_ERROR_INVALID_CLIENT = 'invalid_client';
	const AUTHORIZATION_ERROR_INVALID_GRANT = 'invalid_grant';
	const AUTHORIZATION_ERROR_INVALID_SCOPE = 'invalid_scope';
	const AUTHORIZATION_ERROR_SERVER_ERROR = 'server_error';
	const AUTHORIZATION_ERROR_TEMPORARILY_UNAVAILABLE = 'temporarily_unavailable';
	const AUTHORIZATION_ERROR_UNAUTHORIZED_CLIENT = 'unauthorized_client';
	const AUTHORIZATION_ERROR_UNSUPPORTED_RESPONSE_TYPE = 'unsupported_response_type';
	const AUTHORIZATION_ERROR_UNSUPPORTED_GRANT_TYPE = 'unsupported_grant_type';

	/**
	 * Validate client's identifier syntax.
	 * 
	 * @final
	 * @access public
	 * @param string $client_id client identifier
	 * @return true if client's identifier is valid, otherwise false
	 */
	final public function validateClientId($client_id) {
		return (preg_match('/' . self::CLIENT_ID_PATTERN . '/', $client_id) === 1);
	}

	/**
	 * Validate client's secret syntax.
	 * 
	 * @access public
	 * @param string $secret secret value
	 * @return boolean true if secret is valid, otherwise false
	 */
	final public function validateClientSecret($secret) {
		return (preg_match('/' . self::CLIENT_SECRET_PATTERN . '/', $secret) === 1);
	}

	/**
	 * Validate redirect URI syntax.
	 *
	 * @access public
	 * @param string $redirect_uri redirect URI value
	 * @return boolean true if redirect URI is valid, otherwise false
	 */
	final public function validateRedirectUri($redirect_uri) {
		return (preg_match('/' . self::REDIRECT_URI_PATTERN . '/', $redirect_uri) === 1);
	}

	/**
	 * Generate (pseudo-)random authorization identifier.
	 * 
	 * @see http://tools.ietf.org/html/rfc6749#section-4.1.2
	 * 
	 * @access public
	 * @return string authorization identifier expressed by hexadecimal 40 characters.
	 */
	final public function generateAuthId() {
		return sha1(base64_encode(pack('N6', mt_rand(), mt_rand(), mt_rand(), mt_rand(), mt_rand(), uniqid())));
	}

	/**
	 * Validate authorization identifier syntax (known also as authorization code).
	 * 
	 * @access public
	 * @param string $auth_id authorization identifier
	 * @return true if authorization identifier is valid, otherwise false
	 */
	final public function validateAuthId($auth_id) {
		return (preg_match('/' . self::AUTHORIZATION_ID_PATTERN . '/', $auth_id) === 1);
	}

	/**
	 * Set authorization identifier.
	 * 
	 * NOTE!
	 * It should be used before releasing autorization identifier to client, not after releasing.
	 * 
	 * @abstract
	 * @return true if authorization identifier set successfully, otherwise false
	 */
	abstract public function setAuthId($auth_id, $client_id, $redirect_uri, $scope);

	/**
	 * Generate (pseudo-)random access token.
	 * 
	 * @access public
	 * @return string access token expressed by hexadecimal 40 characters.
	 */
	final public function generateAccessToken() {
		return sha1(base64_encode(pack('N6', mt_rand(), mt_rand(), mt_rand(), mt_rand(), mt_rand(), uniqid())));
	}

	/**
	 * Generate (pseudo-)random refresh token.
	 * 
	 * @access public
	 * @return string refresh token expressed by hexadecimal 40 characters.
	 */
	final public function generateRefreshToken() {
		return sha1(base64_encode(pack('N6', mt_rand(), mt_rand(), mt_rand(), mt_rand(), mt_rand(), uniqid())));
	}

	/**
	 * Validate access token syntax.
	 * 
	 * @access public
	 * @param string $token access token value
	 * @return true if access token is valid, otherwise false
	 */
	final public static function validateAccessToken($token) {
		return (preg_match('/' . self::ACCESS_TOKEN_PATTERN . '/', $token) === 1);
	}

	/**
	 * Validating refresh token syntax.
	 * 
	 * @access public
	 * @param string $token refresh token value
	 * @return true if refresh token is valid, otherwise false
	 */
	final public static function validateRefreshToken($token) {
		return (preg_match('/' . self::REFRESH_TOKEN_PATTERN . '/', $token) === 1);
	}

	/**
	 * Tokens setting.
	 * 
	 * @access public
	 * @param string $access_token access token value
	 * @param string $refresh_token refresh token value
	 * @param string $client_id client identifier
	 * @param string $expires tokens expiration time
	 * @param string $scope scope assigned to tokens
	 * @param string $bconsole_cfg_path dedicated bconsole config file path
	 * @return true if tokens set properly, otherwise false
	 */
	abstract public function setTokens($access_token, $refresh_token, $client_id, $expires, $scope, $bconsole_cfg_path);

	/**
	 * Validate scope syntax.
	 * 
	 * @param integer $scope single scope
	 * @return true if scope is valid, otherwise false
	 */
	final public function validateScope($scope) {
		return (preg_match('/' . self::SCOPE_PATTERN . '/', $scope) === 1);
	}

	/**
	 * Validate scopes.
	 * 
	 * @param integer $scope all scope value
	 * @return true if scopes are valid, otherwise false
	 */
	final public function validateScopes($scope) {
		$result = true;
		$scopes = explode(' ', $scope);
		for ($i = 0; $i < count($scopes); $i++) {
			if ($this->validateScope($scopes[$i]) === false) {
				$result = false;
				break;
			}
		}
		return $result;
	}

	/**
	 * Validate state syntax.
	 * 
	 * @param integer $state request state
	 * @return true if state is valid, otherwise false
	 */
	final public function validateState($state) {
		return (preg_match('/' . self::STATE_PATTERN . '/', $state) === 1);
	}
}
?>
