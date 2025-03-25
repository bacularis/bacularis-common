<?php
/*
 * Bacularis - Bacula web interface
 *
 * Copyright (C) 2021-2025 Marcin Haba
 *
 * The main author of Bacularis is Marcin Haba, with contributors, whose
 * full list can be found in the AUTHORS file.
 *
 * You may use this file and others of this release according to the
 * license defined in the LICENSE file, which includes the Affero General
 * Public License, v3.0 ("AGPLv3") and some additional permissions and
 * terms pursuant to its AGPLv3 Section 7.
 */

namespace Bacularis\Common\Modules\Protocol\WebAuthn;

use Bacularis\Common\Modules\CommonModule;
use Bacularis\Common\Modules\Miscellaneous;

/**
 * Base WebAuthn protocol function module.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class Base extends CommonModule
{
	/**
	 * Relaying party name.
	 */
	public const RP_NAME = 'Bacularis web interface';

	/**
	 * Challenge string length.
	 */
	private const CHALLENGE_SIZE = 32;

	/**
	 * Supported public key algorithm types.
	 * Values are described in the COSE registry.
	 * @see https://www.iana.org/assignments/cose/cose.xhtml#algorithms
	 */
	public const PUBLIC_KEY_ALG_TYPES = [
		-7,    // (ES256)
		-257   // (RS256)
	];

	/**
	 * Get new random challenge.
	 * @see https://w3c.github.io/webauthn/#dom-publickeycredentialcreationoptions-challenge
	 *
	 * @return string challenge base64url encoded string
	 */
	public static function getChallenge(): string
	{
		$challenge = random_bytes(self::CHALLENGE_SIZE);
		return base64_encode($challenge);
	}
}
