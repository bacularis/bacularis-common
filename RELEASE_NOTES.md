
This is new function and bug fix release. We added a new function to save Bacula
component configuration to pattern and configs. This enables new capabilities
such as:

 * creating configs and pattern from an existing Bacula component configuration,
 * coping the same configuration parts to multiple hosts
 * creating backup of the current Bacula component configuration
 * saving a "snapshot" of the Bacula configuration so that can be restored at any time

In Bacularis 5.1.0 we also added new functions to the Bacula messages log window.
They are filters (info/warning/error) and search field to quick search in all message
logs.

On the bug fixes side, we made enabling the TOTP 2FA feature working back and we fixed
bugs that we found during various manual tests.

**Bacularis Common changes**

 * Improve Bacula resource permission settings table view
 * Remove old user manager interface

