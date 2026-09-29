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
 * Interface for verification checker plugins using historical data.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Interface
 */
interface IBacularisVerificationDataPlugin extends IBacularisPlugin
{
	/**
	 * Set previous checker result data.
	 * This is for analyse and compare current results with historical data.
	 *
	 * @param array $history previous checker result data
	 */
	public static function setHistory(array $history): void;

	/**
	 * Get current checker result data.
	 *
	 * @param array $result current checker result data
	 */
	public static function getState(array $result): array;

	/**
	 * Get maximum history size of the checker result data.
	 *
	 * @return int maximum historical data items
	 */
	public static function getHistorySize(): int;

	/**
	 * Get checker configuration properties that have meaning
	 * for keeping history.
	 *
	 * @return array checker configuration properties that impact on history
	 */
	public static function getHistoryConfig(): array;
}
