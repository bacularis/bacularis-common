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

namespace Bacularis\Common\Modules\Cloud\Amazon\EBS;

use Bacularis\Common\Modules\CommonModule;

/**
 * Generic Amazon EBS (Amazon Elastic Block Store) module.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class EBS extends CommonModule
{
	/**
	 * Supported AWS services
	 */
	public const SERVICE_NAME = 'EBS';

	/**
	 * Amzon EBS endpoint types.
	 */
	private const ENDPOINTS = [
		'ipv4' => [
			'addr_pattern' => 'https://ebs.%region.amazonaws.com',
			'name' => 'IPv4 endpoints'
		],
		'ipv46' => [
			'addr_pattern' => 'https://ebs.%region.api.aws',
			'name' => 'Dual-stack IPv4 and IPv6 endpoints'
		],
		'ipv4-fips' => [
			'addr_pattern' => 'https://ebs-fips.%region.amazonaws.com',
			'name' => 'IPv4 FIPS endpoints'
		],
		'ipv46-fips' => [
			'addr_pattern' => 'https://ebs-fips.%region.api.aws',
			'name' => 'Dual-stack IPv4 and IPv6 FIPS endpoints'
		]
	];

	/**
	 * Used to mark empty or unavailable properties in AWS resource objects.
	 */
	public const EMPTY_VALUE_MARK = '-';

	/**
	 * Get service endpoint types for EBS API.
	 *
	 * @param null|string $ptype get filtered list with given property
	 * @return list of EBS API endpoint types ['ipv4' => 'IPv4 endpoints', ...]
	 */
	public static function getEndpoints(?string $ptype = null): array
	{
		$endpoints = [];
		if ($ptype) {
			foreach (self::ENDPOINTS as $type => $props) {
				if (!key_exists($ptype, $props)) {
					continue;
				}
				$endpoints[$type] = $props[$ptype];
			}
		} else {
			$endpoints = self::ENDPOINTS;
		}
		return $endpoints;
	}
}
