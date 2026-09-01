
Hello Community,

We are happy to announce the release of Bacularis 6.5.0. The most important
new feature in this release is Restore Verification, a new feature for
automated restore testing. This version also brings support for Bacula 17.0,
an update to the French translation, new API capabilities, and other improvements.

### Restore Verification

Restore testing is an important part of data protection. However, performing such
tests manually on a regular basis can be time-consuming, tedious, and add extra
workload for administrators. The new Restore Verification feature makes it possible
to automate this process and perform restore tests according to defined criteria.

Restore Verification can use both verification methods provided by Bacularis and
the native Bacula Verify Job. It provides a flexible framework for creating
different automated restore testing scenarios.

You can learn more about this feature in the dedicated Restore Verification section
of the documentation:

https://bacularis.app/doc/restore-verification/general.html

You will also find a video guide there showing Restore Verification in practice.

### French translation update

Thanks to the work and involvement of our community member Philippe Ramage,
the French translation of Bacularis has been updated in version 6.5.0. The update
covers both the web interface and the API administration panel.

### Bacula 17.0 support

In version 6.5.0, we have prepared support for the upcoming Bacula 17.0 release.
All required changes have been implemented and tested with Bacula 17.0.0 beta,
making Bacularis ready for the new major Bacula release.

### New columns in the job table

We have added two new schedule-related columns to the job table: **Next run**
and **Starts in**. They show the date and time of the next scheduled job run and
the time remaining until it starts. This makes it easier to quickly check when
a job is going to be started by the scheduler.

### New API endpoints

On the API side, we have added several new endpoints and extended the capabilities
of several existing ones. The main changes include:

* New endpoints for Restore Verification
* New search parameters for existing endpoints
* New grouping parameters for existing endpoints
* New time-range parameters for job endpoints
* New ``comment`` parameter for the run job endpoint

### Bug fixes

This release also includes bug fixes, including fixes for the Simple Restore feature,
as well as a number of smaller fixes in other areas of Bacularis.

### SELinux policy module update

We have also updated the SELinux policy module to accommodate changes introduced
in the latest versions of Bacularis.

Thank you for supporting the project in any way. It is very important to us.

We wish you smooth Bacularis installations and upgrades.

See you around.

The Bacularis Team

**Bacularis Common**

* Add new restore command interface
* Add restore verification checker plugins
* Add restore verification modules
* Add comment parameter to console restore action
* Add options parameter to HTTP client
* Update SELinux policy module
* Extend misc module for job type and file mode methods
* Extend list filtering to support array list items
* Fix command identifier in simple restore
* Fix selecting submenu item in simple restore

