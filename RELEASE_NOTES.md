
This is a new major Bacularis release. We did improvements for new changes in
the install wizard to install Bacula. Now default HTTP request timeout in
the web server config is 300 seconds. We also fixed bugs reported by
the Community.

## Recreating assets cache

One of the bugs is about default wrong permissions set in the Bacularis assets
cache directory. We recommend to clear the assets directory by removing content
of this directory (with keeping this directory empty). This cache will be
re-created automatically with correct permissions.

For installation using binary packages the assets directory is in path:

```
/var/cache/bacularis
```

For manual installation the assets directory is here:

```
[project_dir]/htdocs/assets
```

The bug report is here:
https://github.com/bacularis/bacularis-web/issues/6

**Changes**
 - Add timeout directives to web server config files
 - Add new system modules
 - Update SELinux module
 - Improve set permission script
 - Fix missing end-of-line character in last line of user config file
 - Fix wrong dir/file permissions in cache directory
 - Fix default parameter value

