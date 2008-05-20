Xataface Web Application Framework: LDAP Module
Copyright (C) 2005-2008  Steve Hannah (shannah@sfu.ca)

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.


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

 	
   
  Please see the Getting Started with Dataface tutorial's section on permissions
     for more information about the '_auth' section of the conf.ini  file. 
     (http://xataface.com/documentation/tutorial/getting_started/permissions)
