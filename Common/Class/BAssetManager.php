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

Prado::using('System.Web.TAssetManager');

/**
 * Baculum asset manager.
 * This additional asset manager layer is required to avoid issue with updating
 * asset directory. It bases on two framework methods responsible for copying
 * files and directories which instead of checking if:
 *
 *   asset file MTIME < original file MTIME
 *
 * here do copying assets everytime if:
 *
 *   asset file MTIME != original file MTIME
 *
 * This behavior is specially useful for upgrades when can be case for which:
 *
 *   asset file MTIME > original file MTIME
 *
 * and in real the original file MTIME has newer content than that one in
 * asset directory. It is because copy() changes MTIME of destination file.
 *
 * @category Client Script
 * @package Baculum Common
 */
class BAssetManager extends TAssetManager {

	protected function copyFile($src, $dst) {
		if (!is_dir($dst)) {
			@mkdir($dst);
			@chmod($dst, PRADO_CHMOD);
		}
		$dst_file = $dst . DIRECTORY_SEPARATOR . basename($src);
		$src_mtime = @filemtime($src);
		if (@filemtime($dst_file) !== $src_mtime) {
			@copy($src, $dst_file);
			if ($src_mtime !== false) {
				@touch($dst_file, $src_mtime);
			}
		}
	}

	public function copyDirectory($src, $dst) {
		if (!is_dir($dst)) {
			@mkdir($dst);
			@chmod($dst, PRADO_CHMOD);
		}
		if ($folder = @opendir($src)) {
			while ($file = @readdir($folder)) {
				$src_file = $src . DIRECTORY_SEPARATOR . $file;
				$dst_file = $dst . DIRECTORY_SEPARATOR . $file;
				if ($file === '.' || $file === '..') {
					continue;
				} elseif (is_file($src_file)) {
					$src_mtime = @filemtime($src_file);
					if (@filemtime($dst_file) !== $src_mtime) {
						@copy($src_file, $dst_file);
						if ($src_mtime !== false) {
							@touch($dst_file, $src_mtime);
						}
						@chmod($dst_file, PRADO_CHMOD);
					}
				} else {
					$this->copyDirectory($src_file, $dst_file);
				}
			}
			closedir($folder);
		} else {
			throw new TInvalidDataValueException('assetmanager_source_directory_invalid', $src);
		}
	}
}
?>
