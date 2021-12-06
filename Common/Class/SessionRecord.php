<?php
/*
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
 
Prado::using('Application.Common.Class.CommonModule');
Prado::using('Application.Common.Class.Interfaces');

/**
 * Store data in session.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 * @package Baculum Common
 */
class SessionRecord extends CommonModule implements SessionItem {

	const SESS_FILE_PERM = 0600;

	private static $lock = false;
	private static $queue = 0;

	public function __construct() {
		self::restore();
	}

	public function __destruct() {
		self::store();
	}

	private static function store($wouldblock = true) {
		$c = get_called_class();
		$sessfile = $c::getSessionFile();
		if (key_exists('sess', $GLOBALS)) {
			$content = serialize($GLOBALS['sess']);
			if (file_exists($sessfile)) {
				$perm = (fileperms($sessfile) & 0777);
				if ($perm !== self::SESS_FILE_PERM) {
					// Correct permissions to more restrictive if needed
					chmod($sessfile, self::SESS_FILE_PERM);
				}
			}
			$old_umask = umask(0);
			$new_umask = (~(self::SESS_FILE_PERM) & 0777);
			umask($new_umask);
			$fp = fopen($sessfile, 'w');
			if (flock($fp, LOCK_EX, $wouldblock)) {
				fwrite($fp, $content);
				fflush($fp);
				flock($fp, LOCK_UN);
				self::forceRefresh();
			} else {
				$emsg = 'Unable to exclusive lock ' . $sessfile;
				$this->getModule('logging')->log(
					__FUNCTION__,
					$emsg,
					Logging::CATEGORY_APPLICATION,
					__FILE__,
					__LINE__
				);
			}
			fclose($fp);
			umask($old_umask);
		}
	}

	private static function restore($wouldblock = true) {
		$c = get_called_class();
		$sessfile = $c::getSessionFile();
		if (!array_key_exists('sess', $GLOBALS)) {
			if (is_readable($sessfile)) {
				$fp = fopen($sessfile, 'r');
				if (flock($fp, LOCK_SH, $wouldblock)) {
					$content = file_get_contents($sessfile);
					$GLOBALS['sess'] = unserialize($content);
					flock($fp, LOCK_UN);
				} else {
					$emsg = 'Unable to shared lock ' . $sessfile;
					$this->getModule('logging')->log(
						__FUNCTION__,
						$emsg,
						Logging::CATEGORY_APPLICATION,
						__FILE__,
						__LINE__
					);
				}
				fclose($fp);
			} else {
				$GLOBALS['sess'] = array();
			}
		}
	}

	public function save() {
		$is_saved = false;
		$is_updated = false;
		$vals =& self::get();
		$c = get_called_class();
		$primary_key = $c::getPrimaryKey();
		for ($i = 0; $i < count($vals); $i++) {
			if ($vals[$i][$primary_key] !== $this->{$primary_key}) {
				continue;
			}
			foreach ($vals[$i] as $key => $val) {
				if (!is_null($this->{$key})) {
					// update record
					$vals[$i][$key] = $this->{$key};
					$is_updated = true;
				}
			}
			if ($is_updated) {
				break;
			}
		}
		if (!$is_updated) {
			// add new record
			$vals[] = get_object_vars($this);
			$is_saved = true;
		}
		if ($is_saved || $is_updated) {
			self::store();
		}
		return ($is_saved || $is_updated);
	}

	public static function &get() {
		self::restore();
		$result = array();
		$c = get_called_class();
		$record_id = $c::getRecordId();
		if (!array_key_exists($record_id, $GLOBALS['sess'])) {
			$GLOBALS['sess'][$record_id] = array();;
		}
		return $GLOBALS['sess'][$record_id];
	}

	public static function findByPk($pk) {
		$c = get_called_class();
		$primary_key = $c::getPrimaryKey();
		$result = self::findBy($primary_key, $pk);
		return $result;
	}

	public static function findBy($field, $value) {
		self::restore();
		$result = null;
		$vals =& self::get();
		for($i = 0; $i < count($vals); $i++) {
			if ($vals[$i][$field] === $value) {
				$result = $vals[$i];
				break;
			}
		}
		return $result;
	}

	public static function deleteByPk($pk) {
		self::restore();
		$result = false;
		$c = get_called_class();
		$vals =& self::get();
		$primary_key = $c::getPrimaryKey();
		for ($i = 0; $i < count($vals); $i++) {
			if ($vals[$i][$primary_key] === $pk) {
				array_splice($vals, $i, 1);
				$result = true;
				break;
			}
		}
		return $result;
	}

	public static function forceRefresh() {
		unset($GLOBALS['sess']);
	}

	public static function getPrimaryKey() {
	}

	public static function getRecordId() {
	}

	public static function getSessionFile() {
	}
}
