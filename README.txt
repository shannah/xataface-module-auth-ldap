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

ldap_version : The version number of the LDAP protocol to use (e.g. 3)
