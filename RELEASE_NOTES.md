
This is a new function release. This time we focused on new usability functions and improvements.

The first new function is tagging. This feature enables to tag almost all resource types available
in the tables. There can be tagged Bacula resources (jobs, clients, volumes...) and Bacularis
resources (users, roles, API hosts...). Everywhere the tag icon is available, there is possible to
tag elements.

Second usability function is data views for the Bacula configuration resources. Data views allow to
arrange in groups the resources by defined criterias. So far, the data views were available for only
a few resources. Now there is possible to use it also with the Bacula config resources both for
Director, Storage, Client and Console components configuration. For example users can group in
logical sets the FileSets or Clients or Jobs or all other configuration resources.

In addition to these two major features, we have also made some smaller improvements to the overall
experience of the web interface.


**Changes**

 * Add clear button to search fields
 * Add tag styles
 * Fix reloading web server config after certificate installation if SELinux is enabled

