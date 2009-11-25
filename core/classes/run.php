<?
	/**
	 * Holds THE aframe class. Let me repeat. THE class. run runs the whole framework.
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
	 * Main framework class in charge of processing requests, loading controller classes,
	 * displaying layouts, and running cleanup. 
	 * 
	 * Really "ties the room together"
	 * 
	 * @package		aframe
	 * @subpackage	aframe.core
	 * @author		Andrew Lyon
	 */
	class run extends base
	{
		/**
		 * Our slappy constructor.
		 * 
		 * @param object &$event	Event object. Holds all other objects. One object to rule them all.
		 */
		function run(&$event)
		{
			$this->_init($event);
		}
		
		/**
		 * Main framework function, does most of the loading and running.
		 * 
		 * Splits up the URL, checks if it has any routes. If so, loads the route and
		 * the assocciated controller/action, otherwise it loads the controller/action
		 * based on the URL params (/controller/action/args).
		 * 
		 * Also checks if the page is allowed to be in HTTPS. If the URL is https:// but
		 * it's not allowed, user will be forwarded to http://
		 * 
		 * Loads the layout (unless otherwise specified) and displays final contents.
		 * 
		 * This function could be split up into 300 different classes, but the beauty of
		 * it is its speed...so suck my balls, OOP!
		 */
		function parse()
		{
			$event	=	&$this->event;
			$error	=	&$event->object('error');
			
			if(APP_ERROR_HANDLING)
			{
				// set our application's error handler. 
				$error_handler	=	&$this->event->controller('error_handler', array(&$this->event), false, false);
				set_error_handler(array($error_handler, 'app_error'), E_ALL);
			}
			
			// Load and proccess our URL
			if(PATH_DISPATCHER)
			{
				$url	=	isset($_SERVER['REQUEST_URI']) ? preg_replace('/(\?|\&).*/', '', $_SERVER['REQUEST_URI']) : '';
			}
			else
			{
				$url	=	isset($_GET['url']) ? $_GET['url'] : '';
			}
			
			// get our request method (GET/POST/PUT/DELETE)
			$request_method	=	$_SERVER['REQUEST_METHOD'];
			$event->set('_method', $request_method);
			
			// remove leading/trailing slash
			$url	=	preg_replace('/(^\/|\/$)/', '', $url);
			
			// explode URL into workable arguments
			$args	=	explode('/', $url);
			if(isset($args[0]) && $args[0] == '')
			{
				$args	=	array();
			}
			$arg_count	=	count($args);
			
			// Set some defaults we may/may not override
			$this->controller	=	'main';
			$this->action		=	'index';
			$this->params		=	array();
			
			// run our routing. started getting pretty hairy and warranted its own method.
			$this->route($url, $args, $request_method, (defined('ADVANCED_ROUTING') && ADVANCED_ROUTING));
			
			// do our HTTPS checking
			if(!$this->ssl_check())
			{
				// User is in HTTPS on a no-ssl-allowed section of site...redirect to HTTP
				$redir	=	'http://'. SITE . $_SERVER['REQUEST_URI'];
				header('HTTP/1.1 301 Moved Permanently');
				header('Location: '. $redir);
				die();
			}
			
			if(CRON_JOB)
			{
				$argv	=	$GLOBALS['_argv'];		// pull from argv we stored during bootup (index.php)
				if(!isset($argv[1]))
				{
					print('cron action not specified');
					die(1);
				}
				
				$this->controller	=	CRON_CONTROLLER;
				$this->action		=	$argv[1];
				$this->params		=	array_slice($argv, 2);
				$url				=	'/'. CRON_CONTROLLER .'/'. $this->action;
			}
			
			// create a function-name safe variable for calling the action (actual action var will be left untouched)
			$action_name		=	preg_replace('/[^a-z0-9\_]/i', '_', $this->action);
			$controller_name	=	preg_replace('/[^a-z0-9\_]/i', '_', $this->controller);
			
			// Set some globals to help us out later on
			$event->set('_controller', $this->controller);
			$event->set('_action', $this->action);
			$event->set('_arguments', $this->params);
			$event->set('_url', $url);
			
			// open our templating object
			$template	=	&$event->object('template');
			
			// assign the view helper into the template for the views to use. does form building
			// and automated pagination 
			$template->assign('helper', $event->object('view_helper', array(&$event)));
			
			// start an output buffer to capture framework output for later manipulation 
			// such as HTML compression (if desired)
			ob_start();
			
			// Load and run our controller->action(args)
			if($controller = &$event->controller($controller_name, array(&$event)))
			{
				if(method_exists($controller, $this->action))
				{
					// our action exists in our controller - load it
					call_user_func_array(
						array(
							$controller,
							$action_name
						),
						$this->params
					);
				}
				elseif(isset($controller->catch_all) && method_exists($controller, $controller->catch_all))
				{
					// If variable $catch_all is found the controller, and the specified action doesn't exist,
					// we call this "catch-all" method and pass in all of the URL parameters after the controller name
					call_user_func_array(
						array(
							$controller,
							$controller->catch_all
						), 
						array_merge(
							array($this->action), 
							array($this->params)
						)
					);
				}
				else
				{
					// booo our action doesnt exist, and we dont have a catchAll...load the missing_action error.
					$error->err('missing_action', array($this->action));
				}
			}
			else
			{
				// The specified controller doesn't exist. Call the error object. Any pre-error app-specific code
				// can be run in the 'error_handler' class in the app's /controllers directory
				$error->err('missing_controller', array($this->controller));
			}

			// Call any finishing we need to do before rendering
			$controller->finish();
			
			// do we want to display the main layout?
			$show_layout	=	$event->get('show_layout', true);
			
			if($show_layout)
			{
				// Load our layout
				$content	=	$template->show_layout();
				echo $content;
			}
			else
			{
				// no layout, just echo the main contents of the rendered view
				$content	=	$template->get_template_vars('main_content');
				echo $content;
			}
			
			// get our completed output and... 
			$content	=	ob_get_contents();
			ob_end_clean();
			
			// ...REGURGITATE!
			echo $content;
			
			// any cleaning up we need to do
			$controller->post();
		}
		
		function route($url, $args, $request_method, $advanced = false)
		{
			// default to false
			$route_found	=	false;
			$arg_count		=	count($args);
			
			// get our routes
			$routes			=	$this->event->get('routes', array());
			
			if($advanced)
			{
				// advanced routing, looop through routes treating each key as a regex, until a match is found.
				// very flexible, very slow. may want to inbreastigate adding caching.
				foreach($routes as $pattern => $route)
				{
					// init empty matches array for regex to pull args out of
					$matches	=	array();
					
					$use_method	=	strpos($pattern, ':');
					$rurl		=	'/' . $url;
					$pattern	=	str_replace('/', '\/', $pattern);
					
					if($use_method)
					{
						$rurl	=	$request_method . ': ' . $rurl;
					}
					
					if(preg_match('/^'. $pattern .'/', $rurl, $matches))
					{
						array_shift($matches);
						$this->controller	=	$route['controller'];
						$this->action		=	$route['action'];
						$this->params		=	$matches;
						
						$route_found	=	true;
						
						break;
					}
				}
			}
			else
			{
				// less advanced routing, uses array hash lookups which is a TON faster, but less flexible
				
				// create a URL for checking our route against (not an exact match of the current url,
				// for ex if we go to /events/view/16, our route url will be /events/view. This gives
				// us a LOT more flexibility with our routes.
				$rurl				=	'/';
				$rurl_1up			=	'//';
				$route_arg_count	=	0;
				if($arg_count > 0)
				{
					$rurl				.=	$args[0];
					$route_arg_count	=	1;
					if($arg_count > 1)
					{
						$rurl				.=	'/' . $args[1];
						$rurl_1up			=	'/' . $args[0] . '/*';
						$route_arg_count	=	2;
					}
				}
				
				// catch a route if we have one... prefers exact matches first, but also accepts "one level up"
				// routes...for ex: if the url is /pages/view, it will check for /pages/view first. If it doesn't
				// find a route for /pages/view, it will look for one for /pages
				if(isset($routes[$rurl]) || isset($routes[$rurl_1up]) || isset($routes[$request_method . ': ' . $rurl]) || isset($routes[$request_method . ': ' . $rurl_1up]))
				{
					// We have a route! Load it, checking our request methods first (most specific -> least specific)
					if(isset($routes[$request_method . ': ' . $rurl]))
					{
						$route	=	$routes[$request_method . ': ' . $rurl];
					}
					else if(isset($routes[$request_method . ': ' . $rurl_1up]))
					{
						$route	=	$routes[$request_method . ': ' . $rurl_1up];
						$route_arg_count	=	1;
					}
					else if(isset($routes[$rurl]))
					{
						$route	=	$routes[$rurl];
					}
					else
					{
						$route	=	$routes[$rurl_1up];
						$route_arg_count	=	1;
					}
					
					if(!empty($route['controller']))
					{
						$this->controller	=	$route['controller'];
					}
					
					if(!empty($route['action']))
					{
						$this->action		=	$route['action'];
					}
					
					// saves ALL our items in the URL after the route match as params
					if($arg_count > $route_arg_count)
					{
						$this->params		=	array_slice($args, $route_arg_count);
					}
					
					$route_found	=	true;
				}
				elseif(isset($routes['*']))
				{
					$route	=	$routes['*'];
					
					if(!empty($route['controller']))
					{
						$this->controller	=	$route['controller'];
					}
					
					if(!empty($route['action']))
					{
						$this->action		=	$route['action'];
					}
					
					$route_found	=	true;
				}
			}
			
			if(!$route_found)
			{
				// No route specified, run as normal /controller/action/args
				if($arg_count > 0)
				{
					$this->controller	=	$args[0];
				}
				if($arg_count > 1)
				{
					$this->action		=	$args[1];
				}
				if($arg_count > 2)
				{
					$this->params		=	array_slice($args, 2);
				}
			}
			
			return true;
		}
		
		/**
		 * Close anything that needs closing. Disconnect anything that needs disconnecting. Our objects are tired after a long
		 * request's work and need some rest.
		 */
		function cleanup()
		{
			$cache	=	&$this->event->object('cache');
			$cache->close();
			if(DATABASE)
			{
				$db	=	&$this->event->object('db');
				$db->disconnect();
			}
		}
		
		/**
		 * If not using HTTPS, returns true. If using HTTPS and current controller/action is in allowed https list, returns true.
		 * Otherwise, returns false.
		 * 
		 * @return bool		Whether or not HTTPS is allowed for the current URL (only applicable if IN HTTPS ;))
		 */
		private function ssl_check()
		{
			if(!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] != 'on')
			{
				return true;
			}
			
			$https	=	$this->event->get('https', array());
			
			$url	=	'/'.$this->controller;
			if($this->action != 'index')
			{
				$url	.=	'/'.$this->action;
			}
			
			if(isset($https[$url]))
			{
				return true;
			}
			
			return false;
		}
	}
?>