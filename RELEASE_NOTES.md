
This is a bug fix release. We fixed bugs reported by Community. Apart from that
we prepared changes to support openSUSE / SLES binary packages. At the end
we did improvements in the deployment function and we updated the SELinux policy
module.

**Changes**

 * Add PHP listen address parameter to install script
 * Add follow symlinks option to Apache config
 * Update SELinux policy module
 * Update web server config templates
 * Fix logout button that in some cases could direct to localhost instead to current address
 * Fix PHP warning if login form is sent with empty user and password
 * Disable PHP-CS-Fixer rule for blank lines

