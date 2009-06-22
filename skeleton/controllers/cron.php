<?
	/**
	 * A-Frame has built-in support for application cron jobs that can be run through the command
	 * line. Any file which has define('CRON_JOB', true) will activate the cron support (NOT
	 * advisable to have any such file in the webroot).
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
	 * App controller class.
	 * 
	 * Abstracts out application-specific functionality from the base controller.
	 * 
	 * @package		aframe
	 * @subpackage	aframe.skeleton
	 */
	class cron extends app_controller
	{
		/**
		 * Runs before any other action is called, in this case to make sure we don't display
		 * a layout (no point in putting HTML into the command-line).
		 */
		function init()
		{
			// should always be called in init
			parent::init();
			
			// let's a-frame know we don't want to show a layout for any cron output
			$this->layout(null);
		}
	}
?>