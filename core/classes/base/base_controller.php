<?
	/**
	 * This file holds the base controller, which holds all controller-specific methods.
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
	 * The base_controller class is extended (hopefully) by all controllers.
	 * 
	 * Holds some basic functionality that is easy to reproduce, but obnoxious to do
	 * so. In essence, makes the developer's life a lot easier. It's mainly known for
	 * loading the controller's corresponding model.
	 * 
	 * @package		aframe
	 * @subpackage	aframe.core
	 * @author		Andrew Lyon
	 */
	class base_controller extends base
	{
		/**
		 * Holds our model (if we have one)
		 * @var object
		 */
		var $model;
		
		/**
		 * Sets the "catch-all" function. Function in controller called when a specified action doesn't exist
		 * @var string
		 */
		var $catch_all	=	null;
		
		/**
		 * Holds the template object
		 * @var object
		 */
		var $template;
		
		/**
		 * String holding the name of the current controller
		 * @var string
		 */
		var $controller;
		
		/**
		 * String holding the name of the current action
		 * @var string
		 */
		var $action;
		
		/**
		 * Array that holds the URL parameters (/controller/action/param1/param1/etc
		 * @var array
		 */
		var $args;
		
		/**
		 * Init our controller-specific objects
		 * 
		 * @param object &$event	Event object
		 */
		function _init(&$event)
		{
			// call base::_init()
			parent::_init($event);
			
			if($event instanceof event)
			{
				// get the template object
				$this->template		=	&$event->return_object('template');
				
				// set some useful vars
				$this->controller	=	$event->get('_controller');
				$this->action		=	$event->get('_action');
				$this->args			=	$event->get('_arguments');
			}
		}
		
		/**
		 * Main controller init() function...called by run::parse() on controller load.
		 * 
		 * Auto-loads the model and any other classes that need it.
		 */
		function init()
		{
			parent::init();
			if(file_exists(MODELS . '/' . $this->controller .'.php'))
			{
				// our controller has a model! automatically load it.
				// this is one of the few object automations in aframe
				$this->model	=	&$this->event->model($this->controller, array(&$this->event));
			}
		}
		
		/**
		 * Placeholder, called by run::parse() after main function call.
		 */
		function finish()
		{
		}
		
		/**
		 * Wraps around template::fetch() for the most part, but does some more app-specific
		 * stuff (such as setting the 'main_content' variable).
		 * 
		 * @param string $view	Name of view to render
		 * @return string		Holds rendered view 
		 */
		function render($view)
		{
			$this->set('_controller', $this->controller);
			$this->set('_action', $this->action);
			
			$content	=	$this->template->fetch($view);

			$this->set('main_content', $content);
			return $content;
		}
		
		/**
		 * Set the template object's layout. The default is 'default' (hehe), but this provides
		 * a convenient and easy to remember wrapper function.
		 * 
		 * This has no bearing on the event variable 'show_layout'. If it is set to false, the
		 * layout is not shown no matter what.
		 */
		function layout($layout)
		{
			if($layout === null || $layout === false)
			{
				$this->event->set('show_layout', false);
			}
			else
			{
				$this->template->set_layout($layout);
			}
		}
		
		/**
		 * Wraps template::assign()
		 */
		function set($key, $value)
		{
			$this->template->assign($key, $value);
		}
		
		/**
		 * Wraps template::assign_by_ref()
		 */
		function set_ref($key, &$value)
		{
			$this->template->assign_by_ref($key, $value);
		}
		
		/**
		 * Does header redirect and dies...simple. Supports different types (dump redirect
		 * or 301). 
		 * 
		 * @param string $url		URL to redirect to
		 * @param string $type		blank for dumb redirect, 301 for 301 redirect
		 */
		function redirect($url, $type = '')
		{
			// commit our sessions before redirecting.
			if(session_id())
			{
				session_write_close();
			}
			
			switch($type)
			{
				case '301'	:
					header( "HTTP/1.1 301 Moved Permanently" );
					break;
				default:
					break; 
			}
			
			// send the header, kill the app from doing further processing.
			header('Location: '. $url);
			die();
		}
		
		/**
		 * Place holder. base_controller::post() gets called after all framework processing (generally right before
		 * run::cleanup()).
		 */
		function post()
		{
			session_write_close();
		}
	}
?>