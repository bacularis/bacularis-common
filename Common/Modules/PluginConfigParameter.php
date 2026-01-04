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
 * Plugin configuration parameter module.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class PluginConfigParameter extends CommonModule
{
	/**
	 * Plugin parameter types.
	 */
	public const TYPE_STRING = 'string';
	public const TYPE_STRING_LONG = 'string_long';
	public const TYPE_INTEGER = 'integer';
	public const TYPE_BOOLEAN = 'boolean';
	public const TYPE_ARRAY = 'array';
	public const TYPE_ARRAY_MULTIPLE = 'array_multiple';
	public const TYPE_ARRAY_MULTIPLE_ORDERED = 'array_multiple_ordered';
}
