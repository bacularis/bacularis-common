
This is new feature and bug fix release that brings many new changes.
The biggest change is add-on support. We prepared a new Bacularis Add-ons
web service where you can download plugins, themes and language packs
for Bacularis. We encourage to visit this website because we will regularly
add there new add-ons. For now we share there a few resources but we have
plenty of ideas for new add-ons, so more should come soon. You can find
there for example: plugins, various coloured themes and translations into
new languages (German, Spanish, Italian). The Bacularis Add-ons web service
is available here: https://addons.bacularis.app

On the plugin side, we prepared changes for a new plugin types. They are
the action plugins. The most interesting is that the action plugins are
not linked to any place in Bacularis. They provide functions that can be
attached to different actions in system. For example, a job action plugin
can be attached to post-create job event, but the same it can be attached
to run-job event or others. This change introduces a lot of flexibility
in using plugins, that can be executed on many actions in Bacularis.

On the Community request we prepared a new API host job access plugin
that is the action plugin and which enables easier managing restricted
access for users to Bacula resources. Besides that, we created a job
action plugin to work with job plugins.

Thanks to the Community activity we fixed a couple of bugs in the web
interface and API parts.

**Bacularis Common changes**

 * Add action and run action plugin interfaces
 * Add new functions to plugin manager
 * Add array multiple ordered values control
 * Add backup plugin type
 * Add end-of-line to direct log messages
 * Adapt styles to support themes
 * Use table page length field without aditional description
 * Misc improvements to plugin setting list
 * Remove unused plugin extra parameters
 * Add Beata to AUTHORS
 * Fix typo error in job resource dependencies

