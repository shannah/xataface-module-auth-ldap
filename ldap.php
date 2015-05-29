<?php
/*-------------------------------------------------------------------------------
 * Xataface Web Application Framework
 * Copyright (C) 2005-2008  Steve Hannah (shannah@sfu.ca)
 * 
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *-------------------------------------------------------------------------------
 */

/**
 *<p>This module extends Dataface to allow LDAP authentication</p>
 * 
 *
 * @author Steve Hannah (shannah@sfu.ca)
 * @created May 20, 2008
 * @version 0.1
 */     
class dataface_modules_ldap {

	/**
	 * Implementation of checkCredentials() hook.  This checks the 
	 * credentials to see if the username/password combination are
	 * correct.
	 */
	function checkCredentials(){
		$auth =& Dataface_AuthenticationTool::getInstance();
		$app =& Dataface_Application::getInstance();
		
		$creds = $auth->getCredentials();
		$creds['UserName'] = trim($creds['UserName']);
		$creds['Password'] = trim($creds['Password']);
		if (empty($creds['UserName']) or empty($creds['Password'])) {
		    return false;
		}
		
		if ( !isset($auth->conf['ldap_host']) ) $auth->conf['ldap_host'] = 'localhost';
		if ( !isset($auth->conf['ldap_port']) ) $auth->conf['ldap_port'] = null;
		if ( !isset($auth->conf['ldap_base']) ){
			trigger_error("Please specify the LDAP basedn in the [_auth] section of the conf.ini file.", E_USER_ERROR);
		}
		
		if ( !function_exists('ldap_connect') ){
			trigger_error("Please install the PHP LDAP module in order to user LDAP authentication.", E_USER_ERROR);
		}
		$ds = ldap_connect($auth->conf['ldap_host'], $auth->conf['ldap_port']);
		if ( !$ds ) trigger_error("Failed to connect to LDAP server", E_USER_ERROR);
		
		if (isset($auth->conf['ldap_version'])) {
		    ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, intval($auth->conf['ldap_version']));
		}
		
		$r = @ldap_search($ds, 'uid='.$creds['UserName'].', '.$auth->conf['ldap_base'],'objectclass=*' );
		if ( $r ){

			$result = @ldap_get_entries($ds, $r);
			//print_r($result);exit;
			if ( $result[0] ){
				if ( @ldap_bind( $ds, $result[0]['dn'], $creds['Password']) ){
					return true;
				}
			}
		}
		
		return false;
	}
	
	
	
	
	
	

}
