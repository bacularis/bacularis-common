#!/bin/sh

####
# Bacularis - Bacula web interface
#
# Copyright (C) 2021-2023 Marcin Haba
#
# The main author of Bacularis is Marcin Haba, with contributors, whose
# full list can be found in the AUTHORS file.
#
# You may use this file and others of this release according to the
# license defined in the LICENSE file, which includes the Affero General
# Public License, v3.0 ("AGPLv3") and some additional permissions and
# terms pursuant to its AGPLv3 Section 7.
####

# Example shell script to set Bacula configuration file permissions.
# Note: It must be simple because it can be executed on different
# systems with different shells.

# Path to Bacula configuration files
BACULA_CONFIG_DIR="$1"

# Web server/PHP user
WEB_SERVER_USER_GROUP="$2"

# Bacula configuration directory permissions
CONFIG_DIR_PERM=775

# Bacula configuration file permissions
CONFIG_FILE_PERM=660

if [ -z "${BACULA_CONFIG_DIR}" -o -z "${WEB_SERVER_USER_GROUP}" ]
then
	echo "
	Script to set Bacula config file permissions for working with Bacularis.

	Usage: $0 <bacula_config_dir> <web_server_user_group>

	bacula_config_dir      - directory containing Bacula configuration
	web_server_user_group  - web server/PHP user group

	Example: $0 /etc/bacula nginx
"
fi

if [ -d "${BACULA_CONFIG_DIR}" ]
then
	# set dir group
	chown :${WEB_SERVER_USER_GROUP} "${BACULA_CONFIG_DIR}/${file}"
	if [ $? -ne 0 ]
	then
		echo "[ERROR] Error while setting '${WEB_SERVER_USER_GROUP}' group for '${BACULA_CONFIG_DIR}'."
		exit 1
	fi

	# set dir permissions
	chmod $CONFIG_DIR_PERM "${BACULA_CONFIG_DIR}"
	if [ $? -ne 0 ]
	then
		echo "[ERROR] Error while setting '${CONFIG_DIR_PERM}' permissions for '${BACULA_CONFIG_DIR}'."
		exit 1
	fi
fi

for file in bacula-dir.conf bacula-sd.conf bacula-fd.conf bconsole.conf
do
	if [ -e "${BACULA_CONFIG_DIR}/${file}" ]
	then
		fuser=`stat -c '%U' "${BACULA_CONFIG_DIR}/${file}"`
		fgroup=`stat -c '%G' "${BACULA_CONFIG_DIR}/${file}"`
		if  [ "$fuser" = "root" -a "$fgroup" = "bacula" ]
		then
			if [ "$file" = "bacula-dir.conf" -o "$file" = "bacula-sd.conf" ]
			then
				# Switch bacula user to be able set web server group below
				chown 'bacula' "${BACULA_CONFIG_DIR}/${file}"
			fi
		fi

		# set file group
		chown :${WEB_SERVER_USER_GROUP} "${BACULA_CONFIG_DIR}/${file}"
		if [ $? -ne 0 ]
		then
			echo "[ERROR] Error while setting '${WEB_SERVER_USER_GROUP}' group for '${BACULA_CONFIG_DIR}/${file}'."
			exit 1
		fi

		# set file permissions
		chmod $CONFIG_FILE_PERM "${BACULA_CONFIG_DIR}/${file}"
		if [ $? -ne 0 ]
		then
			echo "[ERROR] Error while setting '${CONFIG_FILE_PERM}' permissions for '${BACULA_CONFIG_DIR}/${file}'."
			exit 1
		fi
	fi
done

# Everything fine
exit 0
