<?
	/**
	 * System defines. To be copied to "local.php"
	 * 
	 * This is a local configuration (specific the app's dev environment) and should NOT be checked in to SCM.
	 * All modifications to this file should be made to the template (local.default.php) so other developers
	 * on the project can get those changes and make them to their local configuration.
	 * 
	 * These are app-specific settings but required by the framework itself and should not be removed or renamed.
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
	 * Framework defines. Do not remove!
	 */
	define('WEBROOT', '');						// if under a subdirectory...usually blank
	define('SITE', 'website.com');				// base website address (without http://)
	define('DEBUG', false);						// debug mode...displays extended debug info for each request
	define('CACHING', true);					// app-based caching. can be Memcached (default) or a-frame's file-based caching
	define('DATABASE', false);					// does the app use a database?
	define('ERROR_LEVEL', E_ALL | E_NOTICE);	// our default app PHP error level
	define('PATH_DISPATCHER', true);			// true = /index.php/framework_request, false = /index.php?url=framework_request
	define('APP_ERROR_HANDLING', true);			// true = application handles its own errors in controllers/error_handler.php, false = PHP does error handling
	
	/**
	 * App defines.
	 * 
	 * A spot for users to declare defines specific to the application
	 */
	define('USER_DEFINE', 12345);

	/**
	 * Cache configuration.
	 */
	$config['cache']['options']	=	array(
		'caching'		=> 	CACHING,			// Enable/disable caching?
		'type'			=>	'memcache',			// (memcache|apc|filecache)
		'key_prefix'	=>	'aframe:',
		'servers'		=>	array(				// servers, each is
			array('127.0.0.1')					// array([server],[port=11211],[persist=true],[weight=10])
		),
		'compression'	=>	false,				//MEMCACHE_COMPRESSED,
		'ttl'			=>	300					// default TTL (in seconds)
	);
?>