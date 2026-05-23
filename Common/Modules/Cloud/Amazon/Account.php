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

use Bacularis\Common\Modules\CommonModule;

/**
 * Amazon account module.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class Account extends CommonModule
{
	/**
	 * Account access method types.
	 */
	public const ACCOUNT_ACCESS_METHOD_STATIC_CREDENTIALS = 'static-credentials';
	public const ACCOUNT_ACCESS_METHOD_ASSUME_ROLE = 'assume-role';

	/**
	 * Account assume role access types.
	 */
	public const ACCOUNT_CREDENTIAL_SOURCE_ROLE = 'role';
	public const ACCOUNT_CREDENTIAL_SOURCE_SERVICE = 'service';

	/**
	 * Credential sources
	 */
	public const CREDENTIAL_SOURCE_EC2_METADATA = 'Ec2InstanceMetadata';

	/**
	 * AWS account name regexp pattern.
	 */
	public const ACCOUNT_NAME_PATTERN = '[A-Za-z0-9_\-.]+';
}
