

We are pleased to announce the new version of Bacularis 5.4.0. This is a really
big release with many new changes and features. These changes may be particularly
useful for medium and large companies that want to adapt Bacularis to identity
and access management (IAM) systems, but not only.

First of all, we have added support for single sign-on (SSO). Bacularis can be
integrated with a wide range of identity providers (Keycloak, Okta ...etc.).
Bacularis is able to work with identity providers compatible with the OpenID Connect
protocol. There can be configured one or many identity providers from different domains.

Second new feature is support for organizations. Using organizations allows to
assign users to named groups. This can be useful e.g. in companies with multiple
departments and wherever users are divided into groups. Each organization can have
its own identity provider configured, meaning that users from each organization
can authenticate to different IdP. Bacularis accounts can also be transferred
between user federations from one organization to another.

We have also added a new feature - user provisioning. This saves administrator
time because, once provisioning is enabled, Bacularis accounts can be created
dynamically the first time a user logs in. The administrator can define default
account properties (permissions, roles, API hosts and organization).

Next part of new changes concerns logging in using social media credentials.
We have added ability to log in using Google and Facebook social media accounts.
We also plan to add support for more social media services. Very soon on the
Bacularis User Group should be available a poll, where you can vote and report
what other services you would like to see on supported social login list.

For the rest, we prepared new API changes requested by the Community such as
a new update volume endpoint, we slightly reworked the Bacularis login page to
make it look better, and made other improvements and fixes.

Finally, it is worth mentioning the new chapter of the Bacularis documentation
dedicated to authentication, where you can find detailed information about these
new features (SSO, identity providers...). You can also find the video guides
there showing how to configure the new authentication functions.

We wish you easy installations and upgrades. Have a good using Bacula with Bacularis.


## Main changes

**Bacularis Common**

 * Add new PKCE security extension to OAuth2 protocol
 * Add generic HTTP client
 * Add flush logs before redirecing page
 * Add support for passing multiple criterias to bulk action command
 * Add JWT type token decoder
 * Add methods to get public key from certificate
 * Add function to match colors
 * Add new styles for color picker
 * Enable HttpOnly cookie flag
 * Update SELinux policy module
 * Adapt WebAuthn protocol modules to support organizations
 * Move send authentication header
 * Prase max age from cache control header
 * Include response headers in HTTP client result
 * Prepare public key using modulus and exponent values
 * Fix file name prefix in crypto keys module
 * Fix error in audit log if user is not logged in

