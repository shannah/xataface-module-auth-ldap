About
-----

This module extends Xataface to allow for LDAP authentication.

Installation
-------------

1. Download the LDAP module and extract the contents of the tarball into
    your dataface/modules directory.  You should have a directory path
	somewhat like the following:

    	%DATAFACE_PATH%/modules/Auth/ldap/...

2. Add the following section to your application's conf.ini file.
   [_auth]
	auth_type=ldap
	users_table="User"
	username_column="username"
	ldap_host = "ldap.sfu.ca"
	ldap_port = "1389"
	ldap_base = "ou=people, o=SFU, c=CA"

	Except you would enter your LDAP server's coordinates for host, port, and base.



  Please see the Getting Started with Xataface tutorial's section on permissions
     for more information about the '_auth' section of the conf.ini  file.
     (http://xataface.com/documentation/tutorial/getting_started/permissions)

Optional properties:

ldap_version : The version number of the LDAP protocol to use. Defaults to 3
    (required by most modern directories, including Active Directory).

ldap_referrals : Set to 0 to disable referral chasing. Usually required for
    Active Directory searches to behave correctly.


Search mode (Active Directory and directories without anonymous search)
-----------------------------------------------------------------------

By default the module binds directly to "uid=<username>,<base>". This works
for many OpenLDAP directories but not for Active Directory, where the username
is not part of the DN's RDN and anonymous search is usually disallowed.

Setting any of the following options switches the module into "search mode":
it (optionally) binds with a service account, searches the directory for the
user, and then re-binds as the located DN to verify the password.

ldap_username_attribute : The attribute that holds the login name. Defaults to
    "uid". Use "sAMAccountName" for Active Directory.

ldap_filter : A custom search filter. The literal "{username}" is replaced with
    the (escaped) login name. Overrides ldap_username_attribute.
    e.g. ldap_filter = "(sAMAccountName={username})"

ldap_bind_dn : DN of a service/search account used to perform the lookup before
    the user is authenticated. If omitted, the search is attempted anonymously.

ldap_bind_password : Password for ldap_bind_dn. Required whenever ldap_bind_dn
    is set.

ldap_userbind_attribute : The identity to bind as when verifying the user's
    password. Defaults to the located entry's DN. Set this to bind as another
    attribute's value instead - e.g. "userPrincipalName" or "displayName" - for
    directories that expect a non-DN bind name.

Example (Active Directory):

	[_auth]
	auth_type=ldap
	users_table="User"
	username_column="username"
	ldap_host = "ldaps://ad.example.com"
	ldap_port = "636"
	ldap_base = "dc=example,dc=com"
	ldap_referrals = "0"
	ldap_username_attribute = "sAMAccountName"
	ldap_bind_dn = "cn=svc-xataface,ou=Service Accounts,dc=example,dc=com"
	ldap_bind_password = "<service account password>"


Delegate hooks
--------------

The module calls several optional hooks on your application's delegate class,
if they are implemented. They let your application run custom logic around LDAP
authentication - e.g. provisioning a local user record on first login, syncing
profile fields, audit logging, or falling back to a local password - without
modifying the module itself.

beforeLdapAuthenticate($username, $ds)
    Called after connecting to the LDAP server but before the user is located
    and bound. Return boolean false to deny the login; any other return value
    (including none) allows it to proceed. $ds is the LDAP connection.

afterLdapAuthenticate($username, $entry, $ds, $password)
    Called only after the user has been successfully authenticated. $entry is
    the matched LDAP entry (as returned by ldap_get_entries()), so you can read
    attributes such as $entry['displayname'][0] or $entry['mail'][0]. $password
    is the password that was just verified (handle with care - e.g. do not
    log it). The return value is ignored.

ldapUserNotFound($username, $password, $ds)
    Called when the user could not be found in the directory. Return boolean
    true to authenticate the user anyway (e.g. a local-account fallback); any
    other return value leaves the login denied.

ldapInvalidCredentials($username, $password, $ds)
    Called when the user exists in the directory but the password (or account
    state) was rejected. Return boolean true to authenticate the user anyway;
    any other return value leaves the login denied. Note: a local fallback here
    can let a user in with an old password after a directory password change,
    so prefer ldapUserNotFound() for fallback unless you specifically want this.

Example (in your application's delegate class):

	function afterLdapAuthenticate($username, $entry, $ds, $password){
		// Auto-create a local user record on first login.
		$email = isset($entry['mail'][0]) ? $entry['mail'][0] : '';
		// ... INSERT/UPDATE your users table here ...
	}

	function ldapUserNotFound($username, $password, $ds){
		// Optional: authenticate local-only accounts against your own table.
		return false;
	}
