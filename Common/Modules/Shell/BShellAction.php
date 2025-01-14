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

namespace Bacularis\Common\Modules\Shell;

use Prado\Shell\TShellAction;
use Prado\Shell\TShellWriter;

/**
 * Bacularis shell action module.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Module
 */
class BShellAction extends TShellAction
{
	/**
	 * Stores shell writer object.
	 */
	protected $output_writer;

	/**
	 * Get shell writer object.
	 *
	 * @return TShellWriter output writer object
	 */
	public function getWriter(): TShellWriter
	{
		return $this->output_writer;
	}

	/**
	 * Set shell writer object.
	 *
	 * @param TShellWriter $writer output writer object
	 */
	public function setWriter(TShellWriter $writer)
	{
		$this->output_writer = $writer;
	}

	/**
	 * Get module.
	 *
	 * @param string $id module identifier.
	 * @return object module instance
	 */
	protected function getModule($id)
	{
		return $this->getApplication()->getModule($id);
	}
}
