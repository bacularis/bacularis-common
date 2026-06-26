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

/**
 * Store data in session.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
abstract class SessionRecord extends CommonModule implements ISessionItem
{
	/**
	 * Session data main key.
	 */
	private const SESSION_KEY = 'sess';

	/**
	 * Session file permissions.
	 */
	private const SESSION_FILE_PERM = 0600;

	public function __construct()
	{
		// Load session data
		self::restore();
	}

	/**
	 * Store all session data in session file.
	 */
	private static function store(): void
	{
		if (!key_exists(self::SESSION_KEY, $GLOBALS)) {
			// nothing to store in file, end
			return;
		}

		$sessfile = static::getSessionFile();
		$content = serialize($GLOBALS[self::SESSION_KEY]);
		if (file_exists($sessfile)) {
			$perm = (fileperms($sessfile) & 0777);
			if ($perm !== self::SESSION_FILE_PERM) {
				// Correct permissions to more restrictive if needed
				chmod($sessfile, self::SESSION_FILE_PERM);
			}
		}
		// Remember original umask
		$old_umask = umask(0);

		// Prepare new umask
		$new_umask = (~(self::SESSION_FILE_PERM) & 0777);
		umask($new_umask);

		// Store session data in file
		$fp = fopen($sessfile, 'w');
		if (flock($fp, LOCK_EX)) {
			// Lock set - write file
			fwrite($fp, $content);
			fflush($fp);
			flock($fp, LOCK_UN);
			self::forceRefresh();
		} else {
			// Lock not set - error
			$emsg = 'Unable to set exclusive lock on file: ' . $sessfile;
			Logging::log(
				Logging::CATEGORY_APPLICATION,
				$emsg
			);
		}
		fclose($fp);

		// Revert original umask
		umask($old_umask);
	}

	/**
	 * Restore session data from session file.
	 */
	private static function restore(): void
	{
		if (key_exists(self::SESSION_KEY, $GLOBALS)) {
			// Session data already loaded, nothing to do.
			return;
		}
		$sessfile = static::getSessionFile();
		if (is_readable($sessfile)) {
			// Session file exists and is readable.
			$fp = fopen($sessfile, 'r');

			if (flock($fp, LOCK_SH)) {
				// Lock is set - read file
				$content = file_get_contents($sessfile);
				$ucont = unserialize($content);
				$GLOBALS[self::SESSION_KEY] = is_array($ucont) ? $ucont : [];
				flock($fp, LOCK_UN);
			} else {
				// Lock not set -error
				$emsg = 'Unable to set shared lock on file: ' . $sessfile;
				Logging::log(
					Logging::CATEGORY_APPLICATION,
					$emsg
				);
			}
			fclose($fp);
		} else {
			// Session file does not exist, initialize new session
			$GLOBALS[self::SESSION_KEY] = [];
		}
	}

	/**
	 * Save session data.
	 *
	 * @param bool true if saved successfully, false otherwise
	 */
	public function save(): bool
	{
		$is_saved = false;
		$is_updated = false;
		$vals = &self::get();
		$primary_key = static::getPrimaryKey();
		for ($i = 0; $i < count($vals); $i++) {
			if ($vals[$i][$primary_key] !== $this->{$primary_key}) {
				// This is not record that we search for - skip it
				continue;
			}
			foreach ($vals[$i] as $key => $val) {
				if (!is_null($this->{$key})) {
					// Update record
					$vals[$i][$key] = $this->{$key};
					$is_updated = true;
				}
			}
			if ($is_updated) {
				// Record updated - stop
				break;
			}
		}
		if (!$is_updated) {
			// Record does not exist yet in session - add new record
			$vals[] = get_object_vars($this);
			$is_saved = true;
		}
		if ($is_saved || $is_updated) {
			// Record added or updated - store data in file
			self::store();
		}
		return ($is_saved || $is_updated);
	}

	/**
	 * Get current class record.
	 *
	 * @return array record data container
	 */
	public static function &get(): array
	{
		self::restore();
		$record_id = static::getRecordId();
		if (!key_exists($record_id, $GLOBALS[self::SESSION_KEY])) {
			// Record does not exists in session - initialize record data container
			$GLOBALS[self::SESSION_KEY][$record_id] = [];
		}
		return $GLOBALS[self::SESSION_KEY][$record_id];
	}

	/**
	 * Find record by primary key.
	 *
	 * @param string $pk primary key value
	 * @return array record data
	 */
	public static function findByPk(string $pk): ?array
	{
		$primary_key = static::getPrimaryKey();
		return self::findBy($primary_key, $pk);
	}

	/**
	 * Find record by field with given value.
	 *
	 * @param string $field field to find
	 * @param mixed $value value to find
	 * @return null|array record data or null if record not found
	 */
	public static function findBy(string $field, $value): ?array
	{
		self::restore();
		$result = null;
		$vals = &self::get();
		for ($i = 0; $i < count($vals); $i++) {
			if ($vals[$i][$field] === $value) {
				$result = $vals[$i];
				break;
			}
		}
		return $result;
	}

	/**
	 * Delete data record by primary key.
	 *
	 * @param string $pk primary key
	 * @return bool true on success, false otherwise
	 */
	public static function deleteByPk(string $pk): bool
	{
		self::restore();
		$result = false;
		$vals = &static::get();
		$primary_key = static::getPrimaryKey();
		for ($i = 0; $i < count($vals); $i++) {
			if ($vals[$i][$primary_key] === $pk) {
				array_splice($vals, $i, 1);
				$result = true;
				break;
			}
		}
		if ($result) {
			self::store();
		}
		return $result;
	}

	/**
	 * Delete session record.
	 *
	 * @param string $record session record to delete
	 * @return bool true on success, false otherwise
	 */
	public static function deleteByRecord(array $record): bool
	{
		$primary_key = static::getPrimaryKey();
		$pk = $record[$primary_key] ?? null;
		$result = false;
		if (is_string($pk)) {
			$result = self::deleteByPk($pk);
		}
		return $result;
	}

	/**
	 * Force refresh session data.
	 * It removes the current local session container.
	 */
	public static function forceRefresh(): void
	{
		unset($GLOBALS[self::SESSION_KEY]);
	}

	/**
	 * Get primary key name.
	 *
	 * @return string primary key name
	 */
	abstract public static function getPrimaryKey(): string;

	/**
	 * Get record identifier.
	 *
	 * @return string record identifier
	 */
	abstract public static function getRecordId(): string;

	/**
	 * Get full session file path.
	 *
	 * @param string session file path
	 */
	abstract public static function getSessionFile(): string;
}
