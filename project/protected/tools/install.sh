#!/bin/bash

####
# Bacularis - Bacula web interface
#
# Copyright (C) 2021-2025 Marcin Haba
#
# The main author of Bacularis is Marcin Haba, with contributors, whose
# full list can be found in the AUTHORS file.
#
# You may use this file and others of this release according to the
# license defined in the LICENSE file, which includes the Affero General
# Public License, v3.0 ("AGPLv3") and some additional permissions and
# terms pursuant to its AGPLv3 Section 7.
####

echo ""
echo "+===================================================+"
echo "|      Welcome in the Bacularis install script      |"
echo "+---------------------------------------------------+"
echo "|  This script will help you to adjust privileges   |"
echo "|  for Bacularis files and it will prepare          |"
echo "|  configuration files for popular web servers.     |"
echo "+---------------------------------------------------+"
echo ""
echo ""

# Paths
TOOLDIR="`dirname $(readlink -f "$0")`"
PROTDIR="`dirname ${TOOLDIR}`"
WEBDIR="`dirname ${PROTDIR}`"

# Supported web servers' definition
WEB_SERVERS=(Apache Nginx Lighttpd Other)

# Protected directories
PROT_DIRS=(
	"${PROTDIR}/vendor/bacularis/bacularis-api/API/"{Logs,Config}
	"${PROTDIR}/vendor/bacularis/bacularis-web/Web/"{Logs,Config}
	"${PROTDIR}/vendor/bacularis/bacularis-common/Common/Working"
	"${PROTDIR}/runtime"
)

# Public directories
PUB_DIRS=(
	"${WEBDIR}/htdocs/assets"
)

# Protected files
PROT_FILES=(
	"${PROTDIR}/vendor/bacularis/bacularis-api/API/Config/bacularis.users"
	"${PROTDIR}/vendor/bacularis/bacularis-web/Web/Config/bacularis.users"
)

WEB_CFG_APACHE_SAMPLE="${PROTDIR}/samples/webserver/bacularis-apache.conf"
WEB_CFG_NGINX_SAMPLE="${PROTDIR}/samples/webserver/bacularis-nginx.conf"
WEB_CFG_LIGHTTPD_SAMPLE="${PROTDIR}/samples/webserver/bacularis-lighttpd.conf"

# Default values
DEFAULT_WEB_SERVER_IDX=1 # Apache
DEFAULT_WEB_USER='www-data'
DEFAULT_PHP_SOCK='/run/php-fpm/www.sock'

SILENT_MODE=0

# Print message with given type
# Params:
#  integer $1 message type: 0 - info, 1 - warning, 2 - error, any other - unknown
#  string  $2 message body
function msg()
{
	local -r msg_type=$1
	local -r msg_body="$2"
	local type
	case $msg_type in
		0)
			type='[INFO]'
			;;
		1)
			type='[WARNING]'
			;;
		2)
			type='[ERROR]'
			;;
		*)
			type='[UNKNOWN]'
			;;
	esac
	echo "$type $msg_body"

	# Exit after displaying error type message
	if [ x$msg_type == x2 ]
	then
		exit 1
	fi
}

# Check if script is running by superuser (root)
# Returns: integer 1 if user is correct (superuser), otherwise 0.
function check_user()
{
	local ret=1
	if [ $(id -u) != 0 ]
	then
		msg 2 "You are not root. Please run this script as the root user to be able to set permissions or use -n parameter."
		ret=0
	fi
	return $ret
}

# Get from stdin web server type (Apache, Nginx, Lighttpd...)
function get_web_server()
{
	local web_server_idx=-1

	if [ $SILENT_MODE -eq 0 ]
	then
		echo "" >&2
		echo "What is your web server type?" >&2
		local default=''
		for ((i=0; i<${#WEB_SERVERS[@]}; i++))
		do
			if [ $i -eq $((DEFAULT_WEB_SERVER_IDX-1)) ]
			then
				default='(default)'
			else
				default=''
			fi
			echo "$((i+1)) ${WEB_SERVERS[$i]} $default" >&2
		done
		echo -n "Please type number between 1-${#WEB_SERVERS[@]} [$DEFAULT_WEB_SERVER_IDX]: " >&2
		read web_server_idx
	fi
	local web_server=`get_web_server_by_idx $web_server_idx`
	echo $web_server
}

# Get web server type by type index
# Params:
#  integer $1 web server type index
function get_web_server_by_idx
{
	local web_server_idx="$1"
	local web_server=''

	case $web_server_idx in
		1) web_server='apache'
		;;
		2) web_server='nginx'
		;;
		3) web_server='lighttpd'
		;;
		4) web_server=''
		;;
		*) web_server=`get_web_server_by_idx $DEFAULT_WEB_SERVER_IDX`
	esac
	echo $web_server;
}

# Get web server user.
# Reads user from input or uses default user if silent mode is used.
function get_web_user()
{
	local web_user=''

	if [ $SILENT_MODE -eq 0 ]
	then
		echo "" >&2
		echo -n "What is your web server user? [$DEFAULT_WEB_USER]: " >&2
		read web_user
	fi
	if [ -z "$web_user" ]
	then
		web_user=$DEFAULT_WEB_USER
	fi
	echo $web_user
}

# Set directory permissions
# Params:
#  string $1 web server user
function set_dir_ownership()
{
	local web_user="$1"

	chown -R $web_user ${PROT_DIRS[@]} ${PUB_DIRS[@]}
	if [ $? -ne 0 ]
	then
		msg 2 "Error while setting directory ownership"
	fi
}

# Set directory permissions
function set_dir_perms()
{
	chmod 700 ${PROT_DIRS[@]}
	if [ $? -ne 0 ]
	then
		msg 2 "Error while setting directory permissions"
	fi
}

# Set file permissions
function set_file_perms()
{
	chmod 600 ${PROT_FILES[@]}
	if [ $? -ne 0 ]
	then
		msg 2 "Error while setting file permissions"
	fi
}

# Prepare PHP connection for web server
# Params:
#  string $1 web server type (apache, nginx, lighttpd...)
#  string $2 PHP-FPM unix socket path
#  string $3 PHP-FPM listen address and port
function get_php_con()
{
	local web_server="$1"
	local php_sock="$2"
	local php_listen="$3"

	if [ -z "$php_sock" -a -z "$php_listen" ]
	then
		php_sock="${DEFAULT_PHP_SOCK}"
	fi

	local php_con='';
	if [ ! -z "$php_sock" ]
	then
		if [ x$web_server == xnginx ]
		then
			php_con="unix:${php_sock}"
		elif [ x$web_server == xlighttpd ]
		then
			php_con="\"socket\" => \"$php_sock\""
		else
			php_con="$php_sock"
		fi
	elif [ ! -z "$php_listen" ]
	then
		local php_con_arr=(${php_listen//:/ })
		if [ x$web_server == xlighttpd ]
		then
			php_con="\"host\" => \"${php_con_arr[0]}\", \"port\" => ${php_con_arr[1]}"
		else
			php_con="$php_listen"
		fi
	fi
	echo $php_con
}

# Prepare web server configuration file
# Params:
#  string $1 web server type (apache, nginx, lighttpd...)
#  string $2 web server config directory
#  string $3 web root directory
#  string $4 web server user
#  string $5 PHP-FPM unix socket path
#  string $6 PHP-FPM listen address and port
function prepare_web_server_cfg()
{
	local web_server="$1"
	local web_server_cfg_dir="$2"
	local web_root="$3"
	local web_user="$4"
	local php_sock="$5"
	local php_listen="$6"

	local server_file=''
	case $web_server in
		apache) server_file=$WEB_CFG_APACHE_SAMPLE
		;;
		nginx) server_file=$WEB_CFG_NGINX_SAMPLE
		;;
		lighttpd) server_file=$WEB_CFG_LIGHTTPD_SAMPLE
		;;
	esac

	local php_con=`get_php_con "${web_server}" "${php_sock}" "${php_listen}"`

	if [ ! -z "$server_file" ]
	then
		local ws_file=`basename $server_file`
		local ws_cfg_dest=''
		if [ ! -z "$web_server_cfg_dir" ]
		then
			# web server config destination path given
			ws_cfg_dest="${web_server_cfg_dir}/$ws_file"
		else
			# default web server config destination
			ws_cfg_dest="${WEBDIR}/$ws_file"
		fi
		msg 0 "Web server config file you can find in $ws_cfg_dest"
		if [ -z "$web_server_cfg_dir" ]
		then
			msg 0 "Please move it to appropriate location."
		fi
		cat "$server_file" | sed \
			-e "s!###WEBUSER###!${web_user}!g" \
			-e "s!###WEBROOT###!${web_root}!g" \
			-e "s!###PHPCON###!${php_con}!g" \
			> "$ws_cfg_dest"
		if [ $? -ne 0 ]
		then
			msg 2 "Error while preparing web server config ${WEBDIR}/$ws_file"
		fi
	else
		msg 1 "No config sample for given web server"
	fi
}

function usage()
{
	echo "$0 [-u web_user] [-w web_server] [-c web_server_cfg_dir] [-d web_root_dir] [-n] [-p php_sock_path | -l php_listen_addr]  [-s] [-h]:
		-u WEB_USER		web server user
		-w WEB_SERVER		web server type (apache, nginx or lighttpd)
					parameter possible to use multiple times
		-c WEB_SERVER_CFG_DIR   web server config directory (default: WEB_ROOT_DIR/../)
		-d WEB_ROOT_DIR		web server document root directory (web root)
		-n			don't set directory ownership and permissions
		-p PHP_SOCK_PATH	PHP-FPM unix socket path
		-l PHP_LISTEN_ADDR:PORT	PHP-FPM listen address with port
		-s			silent mode
					don't ask about anything
		-h, --help		display this message
"
	exit 0
}

function main()
{
	local web_user=''
	local web_servers=''
	local web_root=''
	local web_server_cfg_dir=''
	local php_sock=''
	local php_listen=''
	local no_perm=0

	if [ "$1" == '--help' ]
	then
		usage
	fi

	while getopts "d:np:l:su:w:c:h" opt
	do
		case $opt in
			d)
				web_root="$OPTARG"
				;;
			u)
				web_user="$OPTARG"
				;;
			w)
				web_servers="$web_servers $OPTARG"
				;;
			c)
				web_server_cfg_dir="$OPTARG"
				;;
			n)
				no_perm=1
				;;
			p)
				php_sock="$OPTARG"
				;;
			l)
				php_listen="$OPTARG"
				;;
			s)
				SILENT_MODE=1
				;;
			h|*)
				usage
				;;
		esac
	done

	if [ $no_perm -eq 0 ]
	then
		check_user
	fi

	if [ -z "$web_root" ]
	then
		web_root="${WEBDIR}/htdocs"
	fi

	if [ -z "$web_servers" ]
	then
		web_servers="`get_web_server`"
		if [ -z "$web_servers" ]
		then
			msg 1 'Unknown web server. You need to prepare web server configuration self.'
		fi
	fi

	if [ -z "$web_user" ]
	then
		web_user="`get_web_user`"
	fi

	if [ $no_perm -eq 0 ]
	then
		if [ ! -z "$web_user" ]
		then
			set_dir_ownership "$web_user"
		fi
		set_dir_perms
		set_file_perms
	fi

	for ws in $web_servers
	do
		prepare_web_server_cfg "$ws" "$web_server_cfg_dir" "$web_root" "$web_user" "$php_sock" "$php_listen"
	done
}

main "$@"

msg 0 'End.'
exit 0
