#!/bin/bash

####
# Bacularis - Bacula web interface
#
# Copyright (C) 2021-2026 Marcin Haba
#
# The main author of Bacularis is Marcin Haba, with contributors, whose
# full list can be found in the AUTHORS file.
#
# You may use this file and others of this release according to the
# license defined in the LICENSE file, which includes the Affero General
# Public License, v3.0 ("AGPLv3") and some additional permissions and
# terms pursuant to its AGPLv3 Section 7.
####

# Prepare Bacularis project files to use.

# Paths
PROJDIR="`readlink -f $(dirname ${0})/../../../../../../..`"

DOCROOT='htdocs'

# Files/dirs to copy recurively
COPY_FILES_RS=(
	"protected/vendor/bacularis/bacularis-common/project/.|./"
	"protected/vendor/npm-asset/fortawesome--fontawesome-free/webfonts/.|htdocs/themes/Baculum-v2/fonts/webfonts/"
	"protected/vendor/npm-asset/fontsource--inter/files/.|htdocs/themes/Baculum-v2/fonts/webfonts/"
)

# Files to copy
COPY_FILES=(
	"protected/vendor/bacularis/bacularis-common/project/protected/samples/webserver/bacularis.users.sample|protected/vendor/bacularis/bacularis-api/API/Config/bacularis.users"
	"protected/vendor/bacularis/bacularis-common/project/protected/samples/webserver/bacularis.users.sample|protected/vendor/bacularis/bacularis-web/Web/Config/bacularis.users"
	"protected/vendor/npm-asset/fortawesome--fontawesome-free/css/all.min.css|htdocs/themes/Baculum-v2/fonts/css/fontawesome-all.min.css"
)

# Symbolic links to create
SYMLINK_DIRS=(
	"vendor/bacularis/bacularis-common/Common|protected/Common"
	"vendor/bacularis/bacularis-api/API|protected/API"
	"vendor/bacularis/bacularis-web/Web|protected/Web"
)

# Prepare required project files
function set_up_project()
{

	# Copy multiple-files recursively
	for ((i = 0; i < ${#COPY_FILES_RS[@]}; i++)); do
		src="`echo ${COPY_FILES_RS[$i]} | awk -F '|' '{print $1}'`"
		dest="`echo ${COPY_FILES_RS[$i]} | awk -F '|' '{print $2}'`"
		if [ -d $dest ]
		then
			if ! \cp -rf "$src" "$dest"
			then
				echo "ERROR: Error while recursive copying $src to $dest"
				exit 1
			fi
		fi
	done

	# Copy single files
	for ((i = 0; i < ${#COPY_FILES[@]}; i++)); do
		src="`echo ${COPY_FILES[$i]} | awk -F '|' '{print $1}'`"
		dest="`echo ${COPY_FILES[$i]} | awk -F '|' '{print $2}'`"
		if [ ! -e $dest ]
		then
			if ! cp "$src" "$dest"
			then
				echo "ERROR: Error while copying $src to $dest"
				exit 1
			fi
		fi
	done

	# Create symbolic links
	for ((i = 0; i < ${#SYMLINK_DIRS[@]}; i++)); do
		src="`echo ${SYMLINK_DIRS[$i]} | awk -F '|' '{print $1}'`"
		dest="`echo ${SYMLINK_DIRS[$i]} | awk -F '|' '{print $2}'`"
		if [ ! -L $dest ]
		then
			if ! ln -s "$src" "$dest"
			then
				echo "ERROR: Error while creating symbolic link $src to $dest"
				exit 1
			fi
		fi
	done
}

cd "$PROJDIR"

if [ -d $DOCROOT ]
then
	set_up_project
else
	echo "ERROR: Wrong project directory path $PROJDIR"
	exit 1
fi

exit 0
