#!/bin/sh

####
# Bacularis - Bacula web interface
#
# Copyright (C) 2021-2024 Marcin Haba
#
# The main author of Bacularis is Marcin Haba, with contributors, whose
# full list can be found in the AUTHORS file.
#
# You may use this file and others of this release according to the
# license defined in the LICENSE file, which includes the Affero General
# Public License, v3.0 ("AGPLv3") and some additional permissions and
# terms pursuant to its AGPLv3 Section 7.
####

# Example shell script to setup the Bacula catalog databasee.
# Note: It must be simple because it can be executed on different
# systems with different shells.

# Script path where are located the Bacula catalog scripts
BACULA_CATALOG_SCRIPT_PATH=$1

# Type of the database (postgresql or mysql)
BACULA_CATALOG_TYPE=$2

# Catalog database name
BACULA_CATALOG_DB_NAME=${db_name:-bacula}

# Catalog database user
BACULA_CATALOG_DB_USER=${db_user:-bacula}

# Catalog database password
randpass=`tr -dc _A-Za-z0-9 </dev/urandom 2>/dev/null | head -c33`
BACULA_CATALOG_DB_PASSWORD=${db_password:-$randpass}

# Bacula Director configuration directory path
BACULA_CONFIG_DIR_PATH=$3

# Help message
help() {
	echo "
	Script to setup the Bacula catalog database for working with Bacularis.

	Usage: $0 <bacula_script_path> <db_type> <bacula_conf_dir>

	bacula_script_path     - Bacula script path with the catalog scripts
	db_type                - database type (postgresql or mysql)
	bacula_conf_dir        - Bacula configuration files directory

	Example: $0 /usr/share/bacula-director postgresql /etc/bacula
"
	exit 1
}

# Error message
error() {
	msg=$1
	echo "[ERROR] $msg"
}

# Info message
info() {
	msg=$1
	echo "[INFO] $msg"
}

# Get pre command
getprecmd() {
	local pre_cmd=''
	if [ "$BACULA_CATALOG_TYPE" = 'postgresql' ]
	then
		pre_cmd='su - postgres -c '
	fi
	echo $pre_cmd
}

initdb() {
	# So far is supported PostgreSQL only
	initdb_pg
}

# Initialize database
initdb_pg() {
	local run_cmd="`getprecmd`"
	local datadir=''
	local cmd=''
	local pg_hba=''
	local pg_conf=''
	if which pg_config
	then
		# DEB-based systems
		pg_ver_full=`pg_config --version`
		pg_ver=`echo $pg_ver_full |  awk '{print int($2)}'`
		if [ $pg_ver -lt 10 ]
		then
			pg_ver=`echo $pg_ver_full | awk '{print $2}'`
		fi
		cmd="pg_createcluster $pg_ver main"
		datadir=`pg_conftool show -s data_directory`
		pg_hba=`pg_conftool show -s hba_file`
		pg_conf="`dirname ${pg_hba}`/postgresql.conf"
	elif which postgresql-setup
	then
		# RPM-based systems
		cmd="postgresql-setup --initdb"
		datadir=`${run_cmd} "echo \\$PGDATA"`
		pg_hba="${datadir}/pg_hba.conf"
		pg_conf="${datadir}/postgresql.conf"
	fi
	if [ ! -z "$cmd" ]
	then
		if [ ! -d "$datadir/base" ]
		then
			if ! ${run_cmd} "$cmd"
			then
				error "Error while initializing Bacula database."
				exit 1
			fi
		else
			info "PostgreSQL database directory not empty. Skip initialization"
		fi
		
		if ! sed -i -E "0,/^host\s+.*/s/^host\s+.*/host    $BACULA_CATALOG_DB_NAME    $BACULA_CATALOG_DB_USER    127.0.0.1\/32    scram-sha-256\n&/" $pg_hba
		then
			error "Error while setting Bacula database access on localhost."
			exit 1
		fi
		
		if ! sed -i -E 's/^#?(password_encryption\s*=\s*)md5/\1scram-sha-256/i' $pg_conf
		then
			error "Error while setting scram-sha-256 encryption password algorithm."
			exit 1
		fi

		if ! sed -i -E "/^Catalog/,/\}/ { s/((db)?password\s*=\s*)(\"?.*\"?)/\1\"${BACULA_CATALOG_DB_PASSWORD}\"/i }" "${BACULA_CONFIG_DIR_PATH}/bacula-dir.conf"
		then
			error "Error while setting Bacula database password in Bacula Director configuration file."
			exit 1
		fi

		if ! systemctl enable postgresql
		then
			error "Error while enabling PostgreSQL server."
			exit 1
		fi

		if ! systemctl start postgresql
		then
			error "Error while starting PostgreSQL server."
			exit 1
		fi

		if ! ${run_cmd} "psql -lqt | awk '{print \$1}' | grep -qw '${BACULA_CATALOG_DB_NAME}'"
		then
			info "Initialize Bacula Catalog database"
			initcatalog
		elif ! ${run_cmd} "psql -c \"ALTER USER ${BACULA_CATALOG_DB_USER} WITH PASSWORD '${BACULA_CATALOG_DB_PASSWORD}'\""
		then
			error "Error while setting password for Bacula Catalog user."
			exit 1
		fi

		if ! systemctl restart bacula-dir
		then
			error "Error while restarting Bacula Director."
		fi
	else
		error "Unknown system. Unable to initialize Bacula database."
		exit 1
	fi
}

initcatalog() {
	local run_cmd="`getprecmd`"
	# prepare variables
	vars="db_name=$BACULA_CATALOG_DB_NAME db_user=$BACULA_CATALOG_DB_USER db_password=$BACULA_CATALOG_DB_PASSWORD "

	if [ ! -d "${BACULA_CATALOG_SCRIPT_PATH}" ]
	then
		error "Bacula catalog script directory does not exist."
		exit 1
	fi

	${run_cmd} "${vars} ${BACULA_CATALOG_SCRIPT_PATH}/create_${BACULA_CATALOG_TYPE}_database"
	if [ $? -ne 0 ]
	then
		error "Error while creating Bacula database."
		exit 1
	fi

	${run_cmd} "${vars} ${BACULA_CATALOG_SCRIPT_PATH}/make_${BACULA_CATALOG_TYPE}_tables"
	if [ $? -ne 0 ]
	then
		error "Error while creating Bacula tables."
		exit 1
	fi

	# In the grant privileges script is a bug with empty db_password variable
	# that does not allow to provide password in environment variable.
	# Here is a try to fix it.
	if ! sed -i '/^db_password=$/d' "${BACULA_CATALOG_SCRIPT_PATH}/grant_${BACULA_CATALOG_TYPE}_privileges"
	then
		error "Error while modifying grant privilates script."
		exit 1
	fi

	${run_cmd} "${vars} ${BACULA_CATALOG_SCRIPT_PATH}/grant_${BACULA_CATALOG_TYPE}_privileges"
	if [ $? -ne 0 ]
	then
		error "Error while granting Bacula database privileges."
		exit 1
	fi
}


if [ -z "${BACULA_CATALOG_TYPE}" -o -z "${BACULA_CATALOG_SCRIPT_PATH}" ]
then
	help
fi

initdb

exit 0
