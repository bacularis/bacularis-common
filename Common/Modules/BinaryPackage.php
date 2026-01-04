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

/**
 * Generic module for supported binary packages.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class BinaryPackage extends CommonModule
{
	/**
	 * Debian-based binary package type.
	 * OSes: Debian/Ubuntu/others...
	 */
	public const TYPE_DEB = 'deb';

	/**
	 * RPM systems binary package type.
	 * OSes: CentOS/AlmaLinux/Rocky/OracleLinux/RHEL/SLES/Fedora/others...
	 */
	public const TYPE_RPM = 'rpm';

	/**
	 * Alpine-based binary package type.
	 * OSes: Alpine/Chimera/others...
	 */
	public const TYPE_APK = 'apk';
}
