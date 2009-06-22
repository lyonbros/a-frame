<?
	/**
	 * Database configuration. To be copied to "database.php"
	 * 
	 * This is a local configuration (specific the app's dev environment) and should NOT be checked in to SCM.
	 * All modifications to this file should be made to the template (database.default.php) so other developers
	 * on the project can get those changes and make them to their local configuration.
	 * 
	 * 
	 * Copyright (c) 2009, Lyon Bros Enterprises, LLC. (http://www.lyonbros.com)
	 * 
	 * Licensed under The MIT License. 
	 * Redistributions of files must retain the above copyright notice.
	 * 
	 * @copyright	Copyright (c) 2009, Lyon Bros Enterprises, LLC. (http://www.lyonbros.com)
	 * @package		aframe
	 * @subpackage	aframe.skeleton
	 * @license		http://www.opensource.org/licenses/mit-license.php
	 */
	
	/**
	 * Standard PEAR-like database configuration array, although the system has it's own DB abstraction layer 
	 * and does NOT use PEAR.
	 */
	$config['db']['dsn']	=	array(
	    'hostspec'  => '127.0.0.1',				// the hostname / socket we're connecting to. if a unix socket, be sure to use proper mysql_connect() notation for sockets
		'port'		=> '3306',					// port the DB server lives on
	    'username'  => 'username',				// connect as username
	    'password'  => 'password',				// using password...
	    'database'  => 'db_name',				// database to connect to
	    'persist'	=>	false,					// whether or not connections persist. 
	    'mode'		=> AFRAME_DB_MODE_MYSQL		// MYSQL, MYSQLI, MSSQL
	);
	
	/**
	 * Database table prefix.
	 */
	$config['db']['prefix']	=	'prfx_';		// table prefix of your app
?>