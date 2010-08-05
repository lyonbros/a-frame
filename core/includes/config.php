<?
	/**
	 * includes/config.php
	 *
	 * This page does the hard-lifting of starting up a-frame. It includes and instantiates all our main classes, loads configuration data from
	 * the app includes, and defines a few constants.
	 * 
	 * The framework is set up so that hopefully you'll never have to touch this file (hint, hint).
	 * 
	 * 
	 * Copyright (c) 2009, Lyon Bros Enterprises, LLC. (http://www.lyonbros.com)
	 * 
	 * Licensed under The MIT License. 
	 * Redistributions of files must retain the above copyright notice.
	 * 
	 * @copyright	Copyright (c) 2009, Lyon Bros Enterprises, LLC. (http://www.lyonbros.com)
	 * @package		aframe
	 * @subpackage	aframe.core
	 * @license		http://www.opensource.org/licenses/mit-license.php
	 */
	
	/**
	 * aframe base define (folder a-frame lives)
	 */
	define('CORE_BASE', $core_base);
	
	/**
	 * aframe includes define (directory config lives)
	 */
	define('CORE_INCLUDES', CORE_BASE .'/core/includes');
	
	/**
	 * aframe classes define (folder that holds all the classes)
	 */
	define('CLASSES', CORE_BASE . '/core/classes');

	/**
	 * application's includes directory (holds the application's configuration)
	 */
	define('INCLUDES', BASE . '/includes');
	
	// if CRON_JOB isn't defined, make sure it's false
	if(!defined('CRON_JOB')) define('CRON_JOB', false);
	
	// annoyances squelched
	define('DS', DIRECTORY_SEPARATOR);
	define('PS', PATH_SEPARATOR);

	$routes	=	array();
	$https	=	array();
	
	include_once INCLUDES .'/local.php';
	include_once INCLUDES .'/routes.php';
	include_once INCLUDES .'/https.php';
	
	include_once CLASSES .'/base/base.php';
	include_once CLASSES .'/base/base_controller.php';
	include_once CLASSES .'/base/base_model.php';
	include_once CLASSES .'/event.php';
	include_once CLASSES .'/msg.php';
	include_once CONTROLLERS . '/app_controller.php';
	include_once MODELS . '/app_model.php';
	
	// another assumption squeltched
	if(!defined('APP_ERROR_HANDLING')) define('APP_ERROR_HANDLING', false);
	
	$event	=	new event();				// start up our event object...stores GET/POST info, and holds all other objects!
	$event->populate();						// fill event object with GET/POST/COOKIE data
	$event->set_ref('config', $config);		// put the config array into the event object so its accessible to all
	$event->set('routes', $routes);			// put the routes array into the event object so its accessible to all
	$event->set('https', $https);			// put the https array into the event object so its accessible to all

	// load some system objects
	$cache		=	&$event->object('classes/cache', array($config['cache']['options']));			// our application's cache...a must have!
	$run		=	&$event->object('classes/run', array(&$event));									// the brain and brawn of a-frame
	$error		=	&$event->object('classes/error', array(&$event));								// probably wont need, but accidents happen
	$template	=	&$event->object('classes/template', array(&$event, VIEWS, VIEWS . '/layouts'));	// needed for, you know, templating (loading and populating views)
	
	if(DATABASE)
	{
		// we DO use a database in this app. load our DB object, and get our app database settings
		// NOTE: we don't actually connect to DB until first query is run
		include_once CLASSES . '/db.php';
		include_once INCLUDES .'/database.php';
		$db		=	&$event->object('db', array($config['db']['dsn']));
	}
?>