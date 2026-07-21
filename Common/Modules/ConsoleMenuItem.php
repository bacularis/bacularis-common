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
 * Manage console menu items.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class ConsoleMenuItem extends CommonModule
{
	/**
	 * Console item types.
	 */
	public const ITEM_TYPE_INPUT = 'input';     // type a value
	public const ITEM_TYPE_SELECT = 'select';   // select value from list
	public const ITEM_TYPE_SUBMENU = 'submenu'; // item provides submenu
	public const ITEM_TYPE_OPTION = 'option';   // select item number from list
}
