
Hello Community,

We are pleased to announce the release of Bacularis ``6.4.0``. This release
includes new features, improvements to existing functionality, and bug fixes.
Let us take a closer look at the most important changes introduced
in version ``6.4.0``.

### Simple restore mode

The most significant new feature is the simple restore mode. Alongside
the existing BVFS-based mode, a new simple restore mode based on the standard
Bconsole restore interface is now available.

It provides an alternative that can be particularly useful in environments
containing millions of files and directories, where preparing and browsing
BVFS data may take longer. Both modes - BVFS restore and simple restore using
Bconsole - can be selected in the restore wizard. This allows users to choose
the method that best matches the size and characteristics of their environment.

### Job log toolbar

Another new feature is a toolbar added everywhere the Bacula job log
is displayed: on the job list, in job reports, and on the job details page.

The idea for this toolbar came from a community user report concerning
the lack of pagination in the job log:

[Bacularis App Issue 130](https://github.com/bacularis/bacularis-app/issues/130)

The toolbar provides the following features:

 * Bacula job log pagination
 * setting the offset and the number of entries displayed per page
 * copying the log to the clipboard
 * saving the log to a file
 * refreshing the log
 * changing the entry sort order
 * displaying an icon representing the current job status

### Module modernization

In addition to introducing new features, we modernized several internal
modules responsible for managing Bacularis sessions. We also rewrote
the API client and updated the restore wizard code.

These changes are not directly visible in the interface, but they bring
the code in line with newer standards and make further project development
easier. The new simple restore mode is the first feature built on this
modernized foundation.

### Easier use of PHP CLI scripts

We have made it easier to use the PHP CLI scripts included with Bacularis,
such as ``task``, which is used to automate tasks - for example, renewing
SSL certificates - and ``plugin``, which is intended for managing Bacularis
plugins.

The scripts now also provide more detailed help messages describing
required and optional parameters, together with examples showing how
to use them.

### New API endpoints

For Bacularis API users, we have introduced new endpoints for managing
simple restore sessions. Full details are available in the API documentation.

We have also added new parameters to existing endpoints, including pagination
and sorting support for the job log endpoint. The job list endpoint now allows
multiple values to be provided for the job level and job type parameters,
instead of accepting only a single value.

### Bug fixes

Bacularis ``6.4.0`` also includes numerous bug fixes. One worth mentioning
resolves an issue reported by a community user concerning the display of tapes
in an autochanger when the ``Volume Retention`` directive was set to a very
large value:

[Bacularis API issue 9](https://github.com/bacularis/bacularis-api/issues/9)

We wish everyone smooth installations and upgrades.

The Bacularis Team

**Bacularis Common**

 * Add Bacula console management with session support
 * Add delete session record
 * Add current script name to shell action help messages
 * Add restorejob parameter to restore action start
 * Add console session idle time out
 * Add debug parameter to all Bacula console actions
 * Add new HTTP method module
 * Improve plugin and task interfaces
 * Modernize session record module
 * Use case-insensitive patterns in bacula console module
 * Unify module names
 * Narrow down command file privileges
 * Use flexbox in file browser loader
 * Change API authentication error message
 * Improve help messages for create SSL certificate task action
 * Extend bconsole support error codes
 * Update application settings
 * Enable save debug messages to dedicated file
 * Remove unused shell module
 * Fix description for certificate task action
 * Fix save plugin debug and metadata if SELinux is enabled
 * Fix PHP error if command action does not provide parameters

