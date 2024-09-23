<?php
/*
 * Bacularis - Bacula web interface
 *
 * Copyright (C) 2021-2024 Marcin Haba
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
 * Interface for Bacula configuration plugin type.
 *
 * @author Marcin Haba <marcin.haba@bacula.pl>
 * @category Interface
 */
interface IBacularisBaculaConfigurationPlugin extends IBacularisPlugin
{
	/**
	 * Pre-config read action.
	 *
	 * @param string $component_type Bacula component type (dir, sd, fd, bcons)
	 * @param string $resource_type Bacula resource type (Job, Fileset, Client ...etc.)
	 * @param string $resource_name Bacula resource name (MyJob, my_client-fd ...etc)
	 */
	public function preConfigRead(?string $component_type, ?string $resource_type, ?string $resource_name): void;

	/**
	 * Post-config read action.
	 * Action is called if the configuration is read successfully.
	 *
	 * @param string $component_type Bacula component type (dir, sd, fd, bcons)
	 * @param string $resource_type Bacula resource type (Job, Fileset, Client ...etc.)
	 * @param string $resource_name Bacula resource name (MyJob, my_client-fd ...etc)
	 * @param array $config Bacula configuration
	 */
	public function postConfigRead(?string $component_type, ?string $resource_type, ?string $resource_name, array $config = []): void;

	/**
	 * Pre-config create action.
	 *
	 * @param string $component_type Bacula component type (dir, sd, fd, bcons)
	 * @param string $resource_type Bacula resource type (Job, Fileset, Client ...etc.)
	 * @param string $resource_name Bacula resource name (MyJob, my_client-fd ...etc)
	 * @param array $config Bacula configuration
	 */
	public function preConfigCreate(?string $component_type, ?string $resource_type, ?string $resource_name, array $config = []): void;

	/**
	 * Post-config save action.
	 *
	 * @param string $component_type Bacula component type (dir, sd, fd, bcons)
	 * @param string $resource_type Bacula resource type (Job, Fileset, Client ...etc.)
	 * @param string $resource_name Bacula resource name (MyJob, my_client-fd ...etc)
	 * @param array $config Bacula configuration
	 */
	public function postConfigCreate(?string $component_type, ?string $resource_type, ?string $resource_name, array $config = []): void;

	/**
	 * Pre-config update action.
	 *
	 * @param string $component_type Bacula component type (dir, sd, fd, bcons)
	 * @param string $resource_type Bacula resource type (Job, Fileset, Client ...etc.)
	 * @param string $resource_name Bacula resource name (MyJob, my_client-fd ...etc)
	 * @param array $config Bacula configuration
	 */
	public function preConfigUpdate(?string $component_type, ?string $resource_type, ?string $resource_name, array $config = []): void;

	/**
	 * Post-config update action.
	 *
	 * @param string $component_type Bacula component type (dir, sd, fd, bcons)
	 * @param string $resource_type Bacula resource type (Job, Fileset, Client ...etc.)
	 * @param string $resource_name Bacula resource name (MyJob, my_client-fd ...etc)
	 * @param array $config Bacula configuration
	 */
	public function postConfigUpdate(?string $component_type, ?string $resource_type, ?string $resource_name, array $config = []): void;

	/**
	 * Pre-config delete action.
	 *
	 * @param string $component_type Bacula component type (dir, sd, fd, bcons)
	 * @param string $resource_type Bacula resource type (Job, Fileset, Client ...etc.)
	 * @param string $resource_name Bacula resource name (MyJob, my_client-fd ...etc)
	 */
	public function preConfigDelete(?string $component_type, ?string $resource_type, ?string $resource_name): void;

	/**
	 * Post-config delete action.
	 *
	 * @param string $component_type Bacula component type (dir, sd, fd, bcons)
	 * @param string $resource_type Bacula resource type (Job, Fileset, Client ...etc.)
	 * @param string $resource_name Bacula resource name (MyJob, my_client-fd ...etc)
	 */
	public function postConfigDelete(?string $component_type, ?string $resource_type, ?string $resource_name): void;

	/**
	 * Write Bacula config action.
	 *
	 * @param string $config Bacula configuration
	 */
	public function writeConfig(string $config): void;
}
