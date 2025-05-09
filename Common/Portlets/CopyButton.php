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

namespace Bacularis\Common\Portlets;

use Bacularis\Common\Portlets\PortletTemplate;

/**
 * Copy to clipboard module.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Control
 */
class CopyButton extends PortletTemplate
{
	private const TEXT_ID = 'TextId';

	/**
	 * Set text container identifier.
	 *
	 * @param string $text_id text container identifier
	 */
	public function setTextId($text_id): void
	{
		$this->setViewState(self::TEXT_ID, $text_id);
	}

	/**
	 * Get text container identifier.
	 *
	 * @return string text container identifier
	 */
	public function getTextId(): string
	{
		return $this->getViewState(self::TEXT_ID, '');
	}
}
