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
		$conf =& $auth->conf;

		$creds = $auth->getCredentials();
		if (empty($creds['UserName']) or empty($creds['Password'])) {
		    return false;
		}
		$creds['UserName'] = trim($creds['UserName']);
		$creds['Password'] = trim($creds['Password']);
		if (empty($creds['UserName']) or empty($creds['Password'])) {
		    return false;
		}

		if ( !isset($conf['ldap_host']) ) $conf['ldap_host'] = 'localhost';
		if ( !isset($conf['ldap_port']) ) $conf['ldap_port'] = null;
		if ( !isset($conf['ldap_base']) ){
			trigger_error("Please specify the LDAP basedn in the [_auth] section of the conf.ini file.", E_USER_ERROR);
		}

		if ( !function_exists('ldap_connect') ){
			trigger_error("Please install the PHP LDAP module in order to use LDAP authentication.", E_USER_ERROR);
		}

		// Build URI to avoid deprecated $port parameter in PHP 8.3+
		$ldap_uri = $conf['ldap_host'];
		if (!preg_match('#^ldaps?://#', $ldap_uri)) {
			$ldap_uri = 'ldap://' . $ldap_uri;
		}
		if (!empty($conf['ldap_port'])) {
			$ldap_uri = rtrim($ldap_uri, '/') . ':' . intval($conf['ldap_port']);
		}
		$ds = @ldap_connect($ldap_uri);
		if ( !$ds ) trigger_error("Failed to connect to LDAP server", E_USER_ERROR);

		// Protocol v3 is required by most modern directories (incl. Active
		// Directory). Default to 3 but allow override via conf.
		$ldap_version = isset($conf['ldap_version']) ? intval($conf['ldap_version']) : 3;
		ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, $ldap_version);

		// Active Directory typically needs referral chasing disabled
		// (ldap_referrals = 0) for searches to behave.
		if (isset($conf['ldap_referrals'])) {
			ldap_set_option($ds, LDAP_OPT_REFERRALS, intval($conf['ldap_referrals']));
		}

		// There are two ways to locate and verify the user:
		//
		//  (1) Direct-DN mode (default, backwards compatible): the user's DN is
		//      assumed to be "uid=<username>,<base>" and we bind to it directly.
		//      This is the original behaviour and is used when none of the
		//      search-mode options (ldap_filter, ldap_bind_dn,
		//      ldap_username_attribute) are configured.
		//
		//  (2) Search mode: optionally bind with a service account, search the
		//      directory for the user, then re-bind as the located DN to verify
		//      the password. Required for Active Directory and any directory
		//      that doesn't expose the username in the RDN or disallows
		//      anonymous search.
		// Allow the application delegate to veto or prepare for authentication
		// before we attempt to locate and bind the user. Returning boolean
		// false from beforeLdapAuthenticate() denies the login.
		if ( $this->fireDelegateHook('beforeLdapAuthenticate', array($creds['UserName'], $ds)) === false ){
			return false;
		}

		$useSearchMode = isset($conf['ldap_filter'])
			|| isset($conf['ldap_bind_dn'])
			|| isset($conf['ldap_username_attribute']);

		$entry = $useSearchMode
			? $this->bindViaSearch($ds, $creds, $conf)
			: $this->bindDirect($ds, $creds, $conf);

		if ( $entry === false ){
			return false;
		}

		// Authentication succeeded. Let the application delegate run any
		// post-authentication logic - e.g. provisioning a local user record,
		// syncing profile fields, or audit logging. The return value is ignored.
		$this->fireDelegateHook('afterLdapAuthenticate', array($creds['UserName'], $entry, $ds));

		return true;
	}

	/**
	 * Calls a hook method on the application delegate class, if defined,
	 * passing $args. Returns the delegate method's return value, or null when
	 * there is no delegate or it does not implement the method.
	 */
	private function fireDelegateHook($method, $args){
		$app =& Dataface_Application::getInstance();
		$delegate =& $app->getDelegate();
		if ( $delegate !== null && method_exists($delegate, $method) ){
			return call_user_func_array(array($delegate, $method), $args);
		}
		return null;
	}

	/**
	 * Direct-DN bind (legacy behaviour). Assumes the user DN is
	 * "uid=<username>,<base>" and binds to it directly. Returns the matched
	 * LDAP entry on success, or false on failure.
	 */
	private function bindDirect($ds, $creds, $conf){
		$dn = 'uid='.ldap_escape($creds['UserName'], '', LDAP_ESCAPE_DN).', '.$conf['ldap_base'];
		$r = @ldap_search($ds, $dn, 'objectclass=*');
		if ( $r ){
			$result = @ldap_get_entries($ds, $r);
			if ( !empty($result[0]['dn']) ){
				if ( @ldap_bind($ds, $result[0]['dn'], $creds['Password']) ){
					return $result[0];
				}
			}
		}
		return false;
	}

	/**
	 * Search-then-bind. Optionally binds with a service account
	 * (ldap_bind_dn / ldap_bind_password), searches the directory for the
	 * user using a configurable filter, then re-binds as the located DN with
	 * the supplied password. Returns the matched LDAP entry on success, or
	 * false on failure.
	 */
	private function bindViaSearch($ds, $creds, $conf){
		// Optional service/search account. Without it the search is anonymous.
		// Note: binding with a DN but an empty password is an "unauthenticated
		// bind" that many servers accept as anonymous - so require a password
		// whenever a bind DN is configured.
		if ( isset($conf['ldap_bind_dn']) ){
			$bind_pw = isset($conf['ldap_bind_password']) ? $conf['ldap_bind_password'] : '';
			if ( $bind_pw === '' || !@ldap_bind($ds, $conf['ldap_bind_dn'], $bind_pw) ){
				trigger_error("Failed to bind to LDAP with the configured service account (ldap_bind_dn).", E_USER_ERROR);
				return false;
			}
		}

		// Build the search filter. ldap_filter may contain a {username}
		// placeholder; otherwise default to "(<attr>=<username>)".
		$attr = isset($conf['ldap_username_attribute']) ? $conf['ldap_username_attribute'] : 'uid';
		$escaped_user = ldap_escape($creds['UserName'], '', LDAP_ESCAPE_FILTER);
		if ( isset($conf['ldap_filter']) ){
			$filter = str_replace('{username}', $escaped_user, $conf['ldap_filter']);
		} else {
			$filter = '('.$attr.'='.$escaped_user.')';
		}

		$r = @ldap_search($ds, $conf['ldap_base'], $filter);
		if ( !$r ) return false;

		$result = @ldap_get_entries($ds, $r);
		if ( empty($result[0]['dn']) ) return false;

		// Re-bind as the located user to verify the password.
		if ( @ldap_bind($ds, $result[0]['dn'], $creds['Password']) ){
			return $result[0];
		}

		return false;
	}
	
	
	
	
	
	

}
