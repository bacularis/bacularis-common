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

namespace Bacularis\Common\Modules\Cloud\Amazon;

/**
 * Amazon region module.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class Region
{
	/**
	 * AWS regions.
	 */
	private const AWS_REGIONS = [
		'us-east-1' => [
			'code' => 'us-east-1',
			'name' => 'US East (N. Virginia)',
			'endpoints' => [
				'ebs' => ['ipv4' => true, 'ipv46' => true, 'ipv4-fips' => true, 'ipv46-fips' => true]
			]
		],
		'us-east-2' => [
			'code' => 'us-east-2',
			'name' => 'US East (Ohio)',
			'endpoints' => [
				'ebs' => ['ipv4' => true, 'ipv46' => true, 'ipv4-fips' => true, 'ipv46-fips' => true]
			]
		],
		'us-west-1' => [
			'code' => 'us-west-1',
			'name' => 'US West (N. California)',
			'endpoints' => [
				'ebs' => ['ipv4' => true, 'ipv46' => true, 'ipv4-fips' => true, 'ipv46-fips' => true]
			]
		],
		'us-west-2' => [
			'code' => 'us-west-2',
			'name' => 'US West (Oregon)',
			'endpoints' => [
				'ebs' => ['ipv4' => true, 'ipv46' => true, 'ipv4-fips' => true, 'ipv46-fips' => true]
			]
		],
		'af-south-1' => [
			'code' => 'af-south-1',
			'name' => 'Africa (Cape Town)',
			'endpoints' => [
				'ebs' => ['ipv4' => true, 'ipv46' => true, 'ipv4-fips' => false, 'ipv46-fips' => false]
			]
		],
		'ap-east-1' => [
			'code' => 'ap-east-1',
			'name' => 'Asia Pacific (Hong Kong)',
			'endpoints' => [
				'ebs' => ['ipv4' => true, 'ipv46' => true, 'ipv4-fips' => false, 'ipv46-fips' => false]
			]
		],
		'ap-south-2' => [
			'code' => 'ap-south-2',
			'name' => 'Asia Pacific (Hyderabad)',
			'endpoints' => [
				'ebs' => ['ipv4' => true, 'ipv46' => true, 'ipv4-fips' => false, 'ipv46-fips' => false]
			]
		],
		'ap-southeast-3' => [
			'code' => 'ap-southeast-3',
			'name' => 'Asia Pacific (Jakarta)',
			'endpoints' => [
				'ebs' => ['ipv4' => true, 'ipv46' => true, 'ipv4-fips' => false, 'ipv46-fips' => false]
			]
		],
		'ap-southeast-5' => [
			'code' => 'ap-southeast-5',
			'name' => 'Asia Pacific (Malaysia)',
			'endpoints' => [
				'ebs' => ['ipv4' => true, 'ipv46' => true, 'ipv4-fips' => false, 'ipv46-fips' => false]
			]
		],
		'ap-southeast-4' => [
			'code' => 'ap-southeast-4',
			'name' => 'Asia Pacific (Melbourne)',
			'endpoints' => [
				'ebs' => ['ipv4' => true, 'ipv46' => true, 'ipv4-fips' => false, 'ipv46-fips' => false]
			]
		],
		'ap-south-1' => [
			'code' => 'ap-south-1',
			'name' => 'Asia Pacific (Mumbai)',
			'endpoints' => [
				'ebs' => ['ipv4' => true, 'ipv46' => true, 'ipv4-fips' => false, 'ipv46-fips' => false]
			]
		],
		'ap-southeast-6' => [
			'code' => 'ap-southeast-6',
			'name' => 'Asia Pacific (New Zealand)',
			'endpoints' => [
				'ebs' => ['ipv4' => true, 'ipv46' => true, 'ipv4-fips' => false, 'ipv46-fips' => false]
			]
		],
		'ap-northeast-3' => [
			'code' => 'ap-northeast-3',
			'name' => 'Asia Pacific (Osaka)',
			'endpoints' => [
				'ebs' => ['ipv4' => true, 'ipv46' => true, 'ipv4-fips' => false, 'ipv46-fips' => false]
			]
		],
		'ap-northeast-2' => [
			'code' => 'ap-northeast-2',
			'name' => 'Asia Pacific (Seoul)',
			'endpoints' => [
				'ebs' => ['ipv4' => true, 'ipv46' => true, 'ipv4-fips' => false, 'ipv46-fips' => false]
			]
		],
		'ap-southeast-1' => [
			'code' => 'ap-southeast-1',
			'name' => 'Asia Pacific (Singapore)',
			'endpoints' => [
				'ebs' => ['ipv4' => true, 'ipv46' => true, 'ipv4-fips' => false, 'ipv46-fips' => false]
			]
		],
		'ap-southeast-2' => [
			'code' => 'ap-southeast-2',
			'name' => 'Asia Pacific (Sydney)',
			'endpoints' => [
				'ebs' => ['ipv4' => true, 'ipv46' => true, 'ipv4-fips' => false, 'ipv46-fips' => false]
			]
		],
		'ap-east-2' => [
			'code' => 'ap-east-2',
			'name' => 'Asia Pacific (Taipei)',
			'endpoints' => [
				'ebs' => ['ipv4' => true, 'ipv46' => true, 'ipv4-fips' => false, 'ipv46-fips' => false]
			]
		],
		'ap-southeast-7' => [
			'code' => 'ap-southeast-7',
			'name' => 'Asia Pacific (Thailand)',
			'endpoints' => [
				'ebs' => ['ipv4' => true, 'ipv46' => true, 'ipv4-fips' => false, 'ipv46-fips' => false]
			]
		],
		'ap-northeast-1' => [
			'code' => 'ap-northeast-1',
			'name' => 'Asia Pacific (Tokyo)',
			'endpoints' => [
				'ebs' => ['ipv4' => true, 'ipv46' => true, 'ipv4-fips' => false, 'ipv46-fips' => false]
			]
		],
		'ca-central-1' => [
			'code' => 'ca-central-1',
			'name' => 'Canada (Central)',
			'endpoints' => [
				'ebs' => ['ipv4' => true, 'ipv46' => true, 'ipv4-fips' => true, 'ipv46-fips' => true]
			]
		],
		'ca-west-1' => [
			'code' => 'ca-west-1',
			'name' => 'Canada West (Calgary)',
			'endpoints' => [
				'ebs' => ['ipv4' => true, 'ipv46' => true, 'ipv4-fips' => true, 'ipv46-fips' => true]
			]
		],
		'eu-central-1' => [
			'code' => 'eu-central-1',
			'name' => 'Europe (Frankfurt)',
			'endpoints' => [
				'ebs' => ['ipv4' => true, 'ipv46' => true, 'ipv4-fips' => false, 'ipv46-fips' => false]
			]
		],
		'eu-west-1' => [
			'code' => 'eu-west-1',
			'name' => 'Europe (Ireland)',
			'endpoints' => [
				'ebs' => ['ipv4' => true, 'ipv46' => true, 'ipv4-fips' => false, 'ipv46-fips' => false]
			]
		],
		'eu-west-2' => [
			'code' => 'eu-west-2',
			'name' => 'Europe (London)',
			'endpoints' => [
				'ebs' => ['ipv4' => true, 'ipv46' => true, 'ipv4-fips' => false, 'ipv46-fips' => false]
			]
		],
		'eu-south-1' => [
			'code' => 'eu-south-1',
			'name' => 'Europe (Milan)',
			'endpoints' => [
				'ebs' => ['ipv4' => true, 'ipv46' => true, 'ipv4-fips' => false, 'ipv46-fips' => false]
			]
		],
		'eu-west-3' => [
			'code' => 'eu-west-3',
			'name' => 'Europe (Paris)',
			'endpoints' => [
				'ebs' => ['ipv4' => true, 'ipv46' => true, 'ipv4-fips' => false, 'ipv46-fips' => false]
			]
		],
		'eu-south-2' => [
			'code' => 'eu-south-2',
			'name' => 'Europe (Spain)',
			'endpoints' => [
				'ebs' => ['ipv4' => true, 'ipv46' => true, 'ipv4-fips' => false, 'ipv46-fips' => false]
			]
		],
		'eu-north-1' => [
			'code' => 'eu-north-1',
			'name' => 'Europe (Stockholm)',
			'endpoints' => [
				'ebs' => ['ipv4' => true, 'ipv46' => true, 'ipv4-fips' => false, 'ipv46-fips' => false]
			]
		],
		'eu-central-2' => [
			'code' => 'eu-central-2',
			'name' => 'Europe (Zurich)',
			'endpoints' => [
				'ebs' => ['ipv4' => true, 'ipv46' => true, 'ipv4-fips' => false, 'ipv46-fips' => false]
			]
		],
		'il-central-1' => [
			'code' => 'il-central-1',
			'name' => 'Israel (Tel Aviv)',
			'endpoints' => [
				'ebs' => ['ipv4' => true, 'ipv46' => true, 'ipv4-fips' => false, 'ipv46-fips' => false]
			]
		],
		'mx-central-1' => [
			'code' => 'mx-central-1',
			'name' => 'Mexico (Central)',
			'endpoints' => [
				'ebs' => ['ipv4' => true, 'ipv46' => true, 'ipv4-fips' => false, 'ipv46-fips' => false]
			]
		],
		'me-south-1' => [
			'code' => 'me-south-1',
			'name' => 'Middle East (Bahrain)',
			'endpoints' => [
				'ebs' => ['ipv4' => true, 'ipv46' => true, 'ipv4-fips' => false, 'ipv46-fips' => false]
			]
		],
		'me-central-1' => [
			'code' => 'me-central-1',
			'name' => 'Middle East (UAE)',
			'endpoints' => [
				'ebs' => ['ipv4' => true, 'ipv46' => true, 'ipv4-fips' => false, 'ipv46-fips' => false]
			]
		],
		'sa-east-1' => [
			'code' => 'sa-east-1',
			'name' => 'South America (São Paulo)',
			'endpoints' => [
				'ebs' => ['ipv4' => true, 'ipv46' => true, 'ipv4-fips' => false, 'ipv46-fips' => false]
			]
		],
		'us-gov-east-1' => [
			'code' => 'us-gov-east-1',
			'name' => 'AWS GovCloud (US-East)',
			'endpoints' => [
				'ebs' => ['ipv4' => true, 'ipv46' => true, 'ipv4-fips' => true, 'ipv46-fips' => true]
			]
		],
		'us-gov-west-1' => [
			'code' => 'us-gov-west-1',
			'name' => 'AWS GovCloud (US-West)',
			'endpoints' => [
				'ebs' => ['ipv4' => true, 'ipv46' => true, 'ipv4-fips' => true, 'ipv46-fips' => true]
			]
		]
	];

	/**
	 * Get region list.
	 *
	 * @return list of Amazon regions ['region code' => 'region name', ...]
	 */
	public static function getRegions(): array
	{
		$region_codes = array_keys(self::AWS_REGIONS);
		$region_names = array_map(
			fn ($item) => "{$item['name']} – {$item['code']}",
			self::AWS_REGIONS
		);
		$regions = array_combine($region_codes, $region_names);
		return $regions;
	}

	/**
	 * Get details about given region by region code.
	 *
	 * @param string $region region code (ex: eu-west-1)
	 * @return array details about given region
	 */
	public static function getRegionDetails(string $region): array
	{
		$details = [];
		if (key_exists($region, self::AWS_REGIONS)) {
			$details = self::AWS_REGIONS[$region];
		}
		return $details;
	}
}
