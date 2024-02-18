<?php
/*
 * Bacularis - Bacula web interface
 *
 * Copyright (C) 2021-2024 Marcin Haba
 *
 * The main author of Bacularis is Marcin Haba, with contributors, whose
 * full list can be found in the AUTHORS file.
 *
 * You may use this file and others of this release according to the
 * license defined in the LICENSE file, which includes the Affero General
 * Public License, v3.0 ("AGPLv3") and some additional permissions and
 * terms pursuant to its AGPLv3 Section 7.
 */

use Bacularis\Common\Portlets\PortletTemplate;

/**
 * Bacula resource permissions control.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Control
 */
class BaculaResourcePermissions extends PortletTemplate
{
	public function getPermissions()
	{
		$permissions = [
			'dir' => $this->DirPermSettings->Value,
			'sd' => $this->SdPermSettings->Value,
			'fd' => $this->FdPermSettings->Value,
			'bcons' => $this->BconsPermSettings->Value
		];
		$perms = [];
		foreach ($permissions as $component => $value) {
			$key = $component . '_res_perm';
			$perms[$key] = [];
			$vals = explode(' ', $value);
			$vals = array_filter($vals);
			if (count($vals) > 0) {
				for ($i = 0; $i < count($vals); $i++) {
					[$resource, $perm] = explode(':', $vals[$i], 2);
					if ($perm === 'rw') {
						// read-write access is default value
						continue;
					}
					$perms[$key][$resource] = $perm;
				}
			}
		}
		return $perms;
	}
}
