<?
	/**
	 * This file holds the MongoDB database abstraction object. It's a simple layer over
	 * Mongo that aims to be very loosely compatible with the the db_sql library.
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
	 * MongoDB operation mode define
	 */
	if(!defined('AFRAME_DB_MODE_MONGODB'))
	{
		define('AFRAME_DB_MODE_MONGODB', 3);
	}

	/**
	 * Very tiny layer over native Mongo object for PHP. Somewhat compatible with A-Frame's
	 * db_sql (at least for connect/disconnect/constructors).
	 *  
	 * @package		aframe
	 * @subpackage	aframe.core
	 * @author		Andrew Lyon
	 */
	class db
	{
		/**
		 * Current DB connection
		 * @var Mongo
		 */
		public $dbc	=	false;

		/**
		 * Current DB
		 * @var MongoDB
		 */
		public $db	=	false;
		
		/**
		 * CTOR
		 * 
		 * @param array $params		DB params to use when connecting
		 * @return bool				true
		 */
		public function db($params)
		{
			$hostspec		=	isset($params['hostspec']) ? $params['hostspec'] : 'mongodb://127.0.0.1:27017';
			$database		=	isset($params['database']) ? $params['database'] : 'test';
			$replicate		=	isset($params['replicate']) ? $params['replicate'] : false;
			$connect		=	isset($params['connect']) ? $params['connect'] : true;
			$persist		=	isset($params['persist']) ? $params['persist'] : false;
			$this->params	=	$params;

			$this->connect($hostspec, $database, $replicate, $persist, $connect);
			return true;
		}
		
		/**
		 * Abraction of connection.
		 * 
		 * @param string $hostspec		connection string to use
		 * @param string $database		database name
		 * @param bool $replicate		whether or not we're using replica sets
		 * @param bool $persist			persist the connect? (bad idea)
		 * @param bool $connect			whether to connect right away
		 * @return object				MongoDB object pointing to $database
		 */
		public function connect($hostspec, $database, $replicate = false, $persist = false, $connect = true)
		{
			if($this->dbc)
			{
				return $this->db;
			}

			$this->dbc	=	new Mongo(
				$hostspec,
				array(
					'connect' 		=>	$connect,
					'persist' 		=>	$persist,
					'replicaSet'	=>	$replicate
				)
			);
			$this->db	=	$this->dbc->$database;

			return $this->db;
		}

		/**
		 * Computer, disconnect database. "DISCONNECTING!!!!!" *BEEP* *BOOP* *BOOP*
		 */
		public function disconnect()
		{
			if(!$this->dbc)
			{
				return;
			}
			$this->dbc->close();
			$this->dbc	=	false;
			$this->db	=	false;
		}
	}
?>
