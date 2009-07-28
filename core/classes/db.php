<?
	/**
	 * This file holds the database access abstraction object. It closely models the functionality
	 * of PEAR::DB, but has taken on a life of its own and is NOT interchangeable with PEAR::DB.
	 * 
	 * This object has the capability to run both MySQL and MSSQL queries seamlessly.
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
	
	// Object-specific defines for current database operating mode. Supports MySQL (default) and MSSQL
	/**
	 * MySQL operation mode define
	 */
	define('AFRAME_DB_MODE_MYSQL', 0);
	
	/**
	 * MSSQL operation mode define
	 */
	define('AFRAME_DB_MODE_MSSQL', 1);
	
	/**
	 * MySQLi operation mode define
	 */
	define('AFRAME_DB_MODE_MYSQLI', 2);

	/**
	 * Database object that removes some annoying busywork from database access. Aside from function
	 * notation, closely resembles functionality of PEAR::DB. 
	 * 
	 * It supports both MySQL (default) and MSSQL database handling modes.
	 * 
	 * Also supports database replication. It will read from a slave and write to a master. It does
	 * NOT support load balancing though, so an actual load balancer must be used to split up
	 * requests to different slaves/masters. 
	 *  
	 * @package		aframe
	 * @subpackage	aframe.core
	 * @author		Andrew Lyon
	 */
	class db
	{
		/**
		 * Current DB connection
		 * @var res
		 */
		var $dbc;
		
		/**
		 * MySQL fetch mode
		 * @var int (constant)
		 */
		var $fetch_mode;
		
		/**
		 * An array containing all queries run by object in their final form
		 * @var array
		 */
		var $queries;
		
		/**
		 * Whether or not to free results directly after querying. saves memory, may slightly hit performance
		 * @var bool 
		 */
		var $free_res	=	false;
		
		/**
		 * Mode to run in (AFRAME_DB_MODE_MYSQL = mysql, AFRAME_DB_MODE_MYSQLI = mysqli, AFRAME_DB_MODE_MSSQL = mssql)
		 * @var int (constant)
		 */
		var $mode	=	AFRAME_DB_MODE_MYSQL;
		
		/**
		 * Whether or note to log queries
		 * @var bool
		 */
		var $log_queries;
		
		/**
		 * Holds our dbc connections (used for replication, mainly)
		 * @var array
		 */
		private $connections;
		
		/**
		 * Whether or not we want to support database replication
		 * @var bool
		 */
		private $replication		=	false;
		
		/**
		 * Whether or not a user manually set a connection lock (keeps db from auto-selecting master/slave depending
		 * on query type
		 * @var bool
		 */
		private $connection_lock	=	false;
		
		/**
		 * Whether or not we're in a transaction
		 * @var bool
		 */
		private $in_transaction		=	false;
		
		/**
		 * Whether or not we're connected
		 * @var bool
		 */
		private $connected			=	false;
		
		/**
		 * Textual indicator of which connection we're using
		 * @var string
		 */
		private $using;
		
		/**
		 * Holds our connection / operating parameters
		 * @var array
		 */
		private $params;
		
		/**
		 * CTOR
		 */
		function db($params)
		{
			$this->queries		=	array();
			$this->connections	=	array();
			$this->connected	=	false;
			$this->params		=	$params;
			
			// initialize our parameters
			$this->mode			=	isset($params['mode']) ? $params['mode'] : AFRAME_DB_MODE_MYSQL;
			$this->free_res		=	isset($params['free_res']) ? $params['free_res'] : false;
			$this->log_queries	=	isset($params['log_queries']) ? $params['log_queries'] : false;
			$this->replication	=	isset($params['replication']) ? $params['replication'] : false;
		}
		
		/**
		 * Abraction of connections, devised mainly for ease of replication. 
		 * 
		 * @return bool		always true
		 */
		public function connect()
		{
			if($this->connected)
			{
				return true;
			}
			
			// set some defaults
			$port		=	isset($this->params['port']) ? $this->params['port'] : '';
			$persist	=	isset($this->params['persist']) ? $this->params['persist'] : false;
			
			if($this->replication)
			{
				$mport	=	isset($this->params['master']['port']) ? $this->params['master']['port'] : '';
				$sport	=	isset($this->params['slave']['port']) ? $this->params['slave']['port'] : '';
				$this->connections[0]	=	$this->do_connect($this->params['master']['hostspec'], $this->params['username'], $this->params['password'], $this->params['database'], $mport, $persist);
				$this->connections[1]	=	$this->do_connect($this->params['slave']['hostspec'], $this->params['username'], $this->params['password'], $this->params['database'], $sport, $persist);
			}
			else
			{
				// we aren't replicating, get our single connection and set it to connection slot 0
				$this->connections[0]	=	$this->do_connect($this->params['hostspec'], $this->params['username'], $this->params['password'], $this->params['database'], $port, $persist);
				
				// immediately initialize our dbc
				$this->dbc				=	&$this->connections[0];
			}
						
			$this->connected	=	true;
			return true;
		}
		
		/**
		 * Generate and return a database connection based on the given database type.
		 * 
		 * @param string $host		hostname to conenct to
		 * @param string $username	username to connect with
		 * @param string $password	password to connect with
		 * @param string $database	database we're connecting to
		 * @param integer $port		(optional) port we're connecting on
		 * @param bool $persist		(optional) whether or not to persist this connection (default=false)
		 * @return resource			database connection resource
		 */
		private function do_connect($host, $username, $password, $database, $port = '', $persist = false)
		{
			if($this->mode == AFRAME_DB_MODE_MYSQL)
			{
				// MySQL mode
				$host	=	!empty($port) && $port != 3306 ? $host .':'. $port : $host;
				if(isset($persist) && $persist)
				{
					$dbc	=	mysql_pconnect($host, $username, $password);
				}
				else
				{
					$dbc	=	mysql_connect($host, $username, $password);
				}
				
				if(!$dbc)
				{
					trigger_error('Database connection failed: ' . mysql_error(), E_USER_ERROR);
				}
				
				mysql_select_db($database, $dbc);
				
				$this->fetch_mode	=	MYSQL_ASSOC;
			}
			else if($this->mode == AFRAME_DB_MODE_MYSQLI)
			{
				// MySQLi
				
				// check for socket
				if($host[0] == ':')
				{
					$socket	=	substr($host, 1);
					$host	=	'localhost';
				}
				else
				{
					$socket	=	null;
				}
				
				// no persistent connection allowed in MySQLi, just transparently connect the normal way
				if(!empty($port))
				{
					$dbc	=	mysqli_connect($host, $username, $password, $database, $port, $socket);
				}
				else
				{
					$dbc	=	mysqli_connect($host, $username, $password, $database, 3306, $socket);
				}
				
				if(!$dbc)
				{
					trigger_error('Database connection failed: ' . mysqli_error(), E_USER_ERROR);
				}
				
				$this->fetch_mode	=	MYSQLI_ASSOC;
			}
			else if($this->mode == AFRAME_DB_MODE_MSSQL)
			{
				// MSSQL mode, you know the rest.
				$host	=	isset($port) && $port != 1433 ? $host .':'. $port : $host;
				if(isset($persist) && $persist)
				{
					$dbc	=	mssql_pconnect($host, $username, $password);
				}
				else
				{
					$dbc	=	mssql_connect($host, $username, $password);
				}
				
				if(!$dbc)
				{
					trigger_error('Database connection failed', E_USER_ERROR);
				}
				
				mssql_select_db($database, $dbc);

				$this->fetch_mode	=	MSSQL_ASSOC;
			}
			
			return $dbc;
		}
		
		/**
		 * Disconnect from our server(s)
		 */
		public function disconnect()
		{
			if(!$this->connected)
			{
				return true;
			}
			
			if($this->replication)
			{
				$this->do_disconnect($this->connections[0]);
				$this->do_disconnect($this->connections[1]);
			}
			else
			{
				$this->do_disconnect($this->dbc);
			}
			
			$this->connected	=	false;
			
			return true;
		}
		
		/**
		 * Run a disconnect with a given connection resource
		 */
		private function do_disconnect(&$dbc)
		{
			if($this->mode == AFRAME_DB_MODE_MYSQL)
			{
				mysql_close($dbc);
			}
			else if($this->mode == AFRAME_DB_MODE_MYSQLI)
			{
				mysqli_close($dbc);
			}
			else if($this->mode == AFRAME_DB_MODE_MSSQL)
			{
				mssql_close($dbc);
			}
			
			return true;
		}
		
		/**
		 * Make sure the next query is run on the master server.
		 */
		public function use_master()
		{
			// make sure we aren't stepping on any toes
			if($this->is_locked())
			{
				return true;
			}
			
			// return the main connection (non-replicated) and/or master connection (replicated)
			$this->dbc	=	&$this->connections[0];
			
			$this->using	=	'master';
			$this->set_lock();
		}
		
		/**
		 * Make sure the next query is run on the slave server. If replication is off, it selects
		 * the main connection at slot 0
		 */
		private function use_slave()
		{
			// make sure we aren't stepping on any toes
			if($this->is_locked())
			{
				return true;
			}
			
			if($this->replication)
			{
				$this->dbc	=	&$this->connections[1];
			}
			else
			{
				// we aren't replicating, transparently just select the main connection
				$this->dbc	=	&$this->connections[0];
			}
			
			$this->using	=	'slave';
			$this->set_lock();
		}
		
		/**
		 * Lock the current connection until AFTER the next query is run. Only good for one query!
		 */
		private function set_lock()
		{
			$this->connection_lock	=	true;
		}
		
		/**
		 * Clear the lock on the connection
		 */
		private function clear_lock()
		{
			$this->connection_lock	=	false;
		}
		
		/**
		 * Test whether or not the conneciton is locked
		 */
		private function is_locked()
		{
			return $this->connection_lock;
		}
		
		/**
		 * Takes a useless jumble of SQl, ?'s, and !'s and turns it all into a magnificent
		 * query that any database can run.
		 *
		 * This function takes ?'s, replaces it with the "'" escaped param, and puts quotes
		 * (') around it. It also takes ! and replaces it with the literal form of its 
		 * corresponding param. Although literal, it DOES escape with the regex [^a-z0-9=\-],
		 * ensuring that UNLESS SPECIFIED BY $rawlit, no harmful characters can enter the
		 * query, even through literals. bitchin. Even a moron developer couldn't fuck this up.
		 * 
		 * Examples:
		 * 
		 * $params = array(10)
		 * SELECT * FROM users WHERE id = !
		 * yields:
		 * SELECT * FROM users WHERE id = 10
		 * 
		 * $params = ('asdf', 13)
		 * SELECT * FROM idiots WHERE name <> ? AND age > !
		 * yields:
		 * SELECT * FROM idiots WHERE name <> 'asdf' AND age > 13
		 * 
		 * etc, etc, etc
		 * 
		 * @param string $query		string containing our SQL to transform
		 * @param array $params		collection of parameters to replace meta chars in SQL with 
		 * @param boolean $rawlit	(optional) use this to NOT filter character in the ! literal
		 * 							DO NOT set this to TRUE without a good understanding of SQL!!
		 * @return string			our damnin hellin query...ready to run
		 */
		public function prepare($query, $params, $rawlit = false)
		{
			// init our params
			if(!is_array($params))
			{
				$params	=	array($params);
			}
			
			// initialize blank query string
			$qry	=	'';
			
			// loop over every character in our query
			for($i = 0, $p = 0, $n = strlen($query); $i < $n; $i++)
			{
				// check for meta characters
				if($query[$i] == '!' || $query[$i] == '?')
				{
					// we have a meta character!! JOY! check whether its a string or literal
					if($query[$i] == '!')
					{
						// we got a literal! if $rawlit is false (probably should be) escape the respective parameter
						if(!$rawlit && !is_numeric($params[$p]))
						{
							$params[$p]	=	preg_replace('/[^a-z0-9=\-]/i', '', $params[$p]);
						}
						
						// add our parameter to the query string
						$qry	.=	$params[$p];
					}
					elseif($query{$i} == '?')
					{
						// our parameter is a string. escape it no matter what, and.......
						$str	=	$this->escape($params[$p]);
						
						// ...add it to our query string, WITH quotes
						$qry	.=	"'" . $str . "'";
					}
					
					// since we added a parameter to the query string, increase the param number by one (so we don't keep re-using the same parameter)
					$p++;
				}
				else
				{
					// awww, no meta character, add it to our stupid query string
					$qry	.=	$query[$i];
				}
			}
			
			return $qry;
		}

		/**
		 * Run a query, grab the resource, and allow the developer to run mysql_* functions
		 * on it. 
		 * 
		 * @param string $query		un-prepared query to run
		 * @param array $params		SQL parameters
		 * @param boolean $rawlit	(optional) use this to NOT filter character in the ! literal
		 * @return resource			query resource
		 * @see						db:execute()
		 */
		public function query($query, $params = array(), $rawlit = false)
		{
			$query	=	$this->prepare($query, $params, $rawlit);
			$res	=	$this->_query($query);
			return $res;
		}
		
		/**
		 * Run a query, don't return resource.
		 * 
		 * This function identical to db::query(), except it doesn't return the query resource. 
		 * In other words, the two functions are interchangeable, unless you want to run mysql_*
		 * functions on the result...then use query().
		 * 
		 * @param string $query		un-prepared query to run
		 * @param array $params		SQL parameters
		 * @param boolean $rawlit	(optional) use this to NOT filter character in the ! literal
		 */
		public function execute($query, $params = array(), $rawlit = false)
		{
			$query	=	$this->prepare($query, $params, $rawlit);
			$res	=	$this->_query($query);
		}
		
		/**
		 * Run several queries at once. This function ONLY WORKS for AFRAME_DB_MODE_MYSQLI, using any
		 * other database mode with this function will break! We could program in support for splitting
		 * up the queries in the $query string into multiple queries, but parsing this within params
		 * would be difficult, time-wasting, and take a performance hit...don't use multi_query unless
		 * you are in AFRAME_DB_MODE_MYSQLI mode.
		 * 
		 * Also keep in mind, you MUST free the resulting resource if you are going to run any queries
		 * after using multi_query. When in doubt, use db::free($res) and make sure db::$free_res is
		 * set to true.
		 * 
		 * @param string $query		un-prepared query(s) to run
		 * @param array $params		SQL parameters
		 * @param boolean $rawlit	(optional) use this to NOT filter character in the ! literal
		 * @return resource			multi-query resource
		 */
		public function multi_query($query, $params, $rawlit = false)
		{
			$query	=	$this->prepare($query, $params, $rawlit);
			$res	=	$this->_query($query, true);
			return $res;
		}		
		
		/**
		 * Run a query, shove all resulting rows into an array
		 * 
		 * @param string $query		un-prepared query to run
		 * @param array $params		SQL parameters
		 * @param boolean $rawlit	(optional) use this to NOT filter characters in the ! literal
		 * @param boolean $multi	(optional) whether or not to run a multi query and get all results
		 * @return array			result array
		 */
		public function all($query, $params = array(), $rawlit = false, $multi = false)
		{
			$query	=	$this->prepare($query, $params, $rawlit);
			$res	=	$this->_query($query, $multi);
			$data	=	array();
			
			if($this->mode == AFRAME_DB_MODE_MYSQLI && $multi)
			{
				// we have a multi query
				do
				{
					if($res = mysqli_store_result($this->dbc))
					{
						$resdata	=	array();
						while($row = $this->fetch_row($res))
						{
							$resdata[]	=	$row;
						}
						$data[]	=	$resdata;
						$this->free($res);
					}
				} while(mysqli_next_result($this->dbc));
			}
			else
			{
				// no multi-query, just process as normal...
				if($this->num_rows($res) > 0)
				{
					while($row = $this->fetch_row($res))
					{
						$data[]	=	$row;
					}
				}
				$this->free($res);
			}
			
			return $data;
		}

		/**
		 * Run a query, get first resultant row
		 * 
		 * @param string $query		un-prepared query to run
		 * @param array $params		SQL parameters
		 * @param boolean $rawlit	(optional) use this to NOT filter characters in the ! literal
		 * @return array			result array
		 */
		public function row($query, $params = array(), $rawlit = false)
		{
			$query	=	$this->prepare($query, $params, $rawlit);
			$res	=	$this->_query($query);
			$data	=	array();
			if($this->num_rows($res) > 0)
			{
				if($this->mode == AFRAME_DB_MODE_MYSQL)
				{
					$data	=	$this->fetch_row($res, MYSQL_ASSOC);
				}
				else if($this->mode == AFRAME_DB_MODE_MYSQLI)
				{
					$data	=	$this->fetch_row($res, MYSQLI_ASSOC);
				}
				else if($this->mode == AFRAME_DB_MODE_MSSQL)
				{
					$data	=	$this->fetch_row($res, MSSQL_ASSOC);
				}
			}
			$this->free($res);
			return $data;
		}
				
		/**
		 * Run a query, return value of first column of firsth row
		 * 
		 * @param string $query		un-prepared query to run
		 * @param array $params		SQL parameters
		 * @param boolean $rawlit	(optional) use this to NOT filter characters in the ! literal
		 * @return array			result array
		 */
		public function one($query, $params = array(), $rawlit = false)
		{
			$query	=	$this->prepare($query, $params, $rawlit);
			$res	=	$this->_query($query);
			$data	=	'';
			if($this->num_rows($res) > 0)
			{
				if($this->mode == AFRAME_DB_MODE_MYSQL)
				{
					$row	=	$this->fetch_row($res, MYSQL_NUM);
				}
				else if($this->mode == AFRAME_DB_MODE_MYSQLI)
				{
					$row	=	$this->fetch_row($res, MYSQLI_NUM);
				}
				else if($this->mode == AFRAME_DB_MODE_MSSQL)
				{
					$row	=	$this->fetch_row($res, MSSQL_NUM);
				}
				$data	=	$row[0];
			}
			$this->free($res);

			return $data;
		}

		/**
		 * Alert the stupid database that we are initiating a transaction
		 */
		public function transaction()
		{
			if($this->mode == AFRAME_DB_MODE_MYSQL)
			{
				$qry	=	"start transaction";
			}
			else if($this->mode == AFRAME_DB_MODE_MYSQLI)
			{
				$qry	=	"start transaction";
			}
			else if($this->mode == AFRAME_DB_MODE_MSSQL)
			{
				$qry	=	"begin transaction";
			}
			$this->query($qry);
			
			$this->in_transaction	=	true;
		}
		
		/**
		 * Commit all the changes to the db that happened since we opened our transaction
		 * 
		 * @see db::transaction()
		 */
		public function commit()
		{
			if($this->mode == AFRAME_DB_MODE_MYSQL)
			{
				$qry	=	"commit";
			}
			else if($this->mode == AFRAME_DB_MODE_MYSQLI)
			{
				$qry	=	"commit";
			}
			else if($this->mode == AFRAME_DB_MODE_MSSQL)
			{
				$qry	=	"commit transaction";
			}
			$this->query($qry);
			
			$this->in_transaction	=	false;
		}
		
		/**
		 * HOLY SHIT!!! OMFGOSH ABORT!!
		 * 
		 * @see db::transaction()
		 */
		public function revert()
		{
			if($this->mode == AFRAME_DB_MODE_MYSQL)
			{
				$qry	=	"rollback";
			}
			else if($this->mode == AFRAME_DB_MODE_MYSQLI)
			{
				$qry	=	"rollback";
			}
			else if($this->mode == AFRAME_DB_MODE_MSSQL)
			{
				$qry	=	"rollback transaction";
			}
			$this->query($qry);
			
			$this->in_transaction	=	false;
		}
		
		/**
		 * Wrapper around db::revert()
		 * 
		 * @see db::revert()
		 */
		public function rollback()
		{
			$this->revert();
		}

		/**
		 * Get the last query run off the stack and return it. Good for debugging a wrotten query.
		 * 
		 * @return string	last query run off the stack
		 */
		public function last_query()
		{
			return $this->queries[count($this->queries) - 1];
		}
		
		/**
		 * Dump (return) all the queries run so far
		 * 
		 * @param string $sep	(optional) Separator betwee queries, defaults to '<br/>'
		 * @return string		Query dump
		 */
		public function dump_queries($sep = '<br/>')
		{
			$queries	=	implode($sep, $this->queries);
			return $queries;
		}
		
		/**
		 * Wrapper around mysql_real_escape_string in MySQL mode, replaces 's with '' in MSSQL mode.
		 * 
		 * @param string $str	string to be escaped
		 * @return string		escaped bulllllshit
		 */
		public function escape($str)
		{
			$this->connect();
			
			if($this->mode == AFRAME_DB_MODE_MYSQL)
			{
				return mysql_real_escape_string($str, $this->connections[0]);
			}
			else if($this->mode == AFRAME_DB_MODE_MYSQLI)
			{
				return mysqli_real_escape_string($this->connections[0], $str);
			}
			else if($this->mode == AFRAME_DB_MODE_MSSQL)
			{
				return str_replace("'", "''", $str);
			}
		}
		
		/**
		 * Send a prepared query to the database and record it for debugging purposes. dies on error.
		 * 
		 * Supports multi-queries, but ONLY when in AFRAME_DB_MODE_MYSQLI...dies otherwise.
		 * 
		 * Logs queries into db::$queries for later inspection (# queries run in request, cache testing, etc).
		 * 
		 * @param string $query		prepared query
		 * @param bool $multi		(optional) Whether or not we're running a multi-query
		 * @return resource			resource created by mysql_query(), on success
		 */
		public function _query($query, $multi = false)
		{
			if (defined('LOGS') && $this->log_queries) {
				$log_file = fopen(LOGS . "/query_log", "a+");
				fwrite($log_file, '['. date('Y-m-d h:i:s A') . ']' . "\r\n");
				fwrite($log_file, $query);
				fwrite($log_file, "\r\n\r\n");
				fclose($log_file);				
			}
			
			if($this->mode != AFRAME_DB_MODE_MYSQLI && $multi)
			{
				trigger_error('You are trying to run a multi-query in a database extension that does not support this feature. Please modify your code to NOT use multi_query or use MySQLi mode.', E_USER_ERROR);
			}
			
			// make sure we're connected
			$this->connect();
			
			// get first non-whitespace characters of query and test for 'SELECT'
			$qry_test	=	substr(preg_replace('/[\r\n \t]+/is', ' ', $query), 0, 16);
			if($this->in_transaction || stripos($qry_test, 'SELECT') === false)
			{
				// this is NOT a select OR we're in a transaction, use master
				$this->use_master();
			}
			else
			{
				// this is a select and we are not in a transaction, use slave
				$this->use_slave();
			}
			
			// add the query to our log
			$this->queries[]	=	($this->replication ? '('. $this->using .') ' : '') . $query;
			
			// reset connection lock
			$this->clear_lock();
			
			if($this->mode == AFRAME_DB_MODE_MYSQL)
			{
				if(!$res = mysql_query($query, $this->dbc))
				{
					trigger_error(mysql_error() . '<br/><br/>' . $query, E_USER_ERROR);
				}
			}
			else if($this->mode == AFRAME_DB_MODE_MSSQL)
			{
				if(!$res = mssql_query($query, $this->dbc))
				{
					trigger_error('Query failed: <br/><br/>' . $query, E_USER_ERROR);
				}
			}
			else if($this->mode == AFRAME_DB_MODE_MYSQLI)
			{
				if($multi)
				{
					$res	=	mysqli_multi_query($this->dbc, $query);
				}
				else
				{
					$res	=	mysqli_query($this->dbc, $query);
				}
				
				if(!$res)
				{
					trigger_error(mysqli_error($this->dbc) . '<br/><br/>' . $query, E_USER_ERROR);
				}
			}

			return $res;
		}
		
		/**
		 * HMMMM I wonder
		 * 
		 * @param resource $res		mysql query resource
		 * @return int				number of fields in result
		 */
		public function num_fields(&$res)
		{
			if($this->mode == AFRAME_DB_MODE_MYSQL)
			{
				return mysql_num_fields($res);
			}
			else if($this->mode == AFRAME_DB_MODE_MYSQLI)
			{
				return mysqli_num_fields($res);
			}
			else if($this->mode == AFRAME_DB_MODE_MSSQL)
			{
				return mssql_num_fields($res);
			}
		}
		
		/**
		 * get a row from a db resource
		 * 
		 * @param resource $res			mysql query resource
		 * @param string $fetch_mode	(optional) The fetch mode we're using
		 * @return array				data array
		 */
		public function fetch_row(&$res, $fetch_mode = '')
		{
			$fetch_mode	=	empty($fetch_mode) ? $this->fetch_mode : $fetch_mode;
			if($this->mode == AFRAME_DB_MODE_MYSQL)
			{
				$data	=	mysql_fetch_array($res, $fetch_mode);
			}
			else if($this->mode == AFRAME_DB_MODE_MYSQLI)
			{
				$data	=	mysqli_fetch_array($res, $fetch_mode);
			}
			else if($this->mode == AFRAME_DB_MODE_MSSQL)
			{
				$data	=	mssql_fetch_array($res, $fetch_mode);
			}
			return $data;
		}
		
		/**
		 * Return the number of rows in a query resource
		 * 
		 * @param resource $res		the query resource to read from
		 * @return int				number of rows in resource
		 */
		public function num_rows(&$res)
		{
			if($this->mode == AFRAME_DB_MODE_MYSQL)
			{
				$data	=	mysql_num_rows($res);
			}
			else if($this->mode == AFRAME_DB_MODE_MYSQLI)
			{
				$data	=	mysqli_num_rows($res);
			}
			else if($this->mode == AFRAME_DB_MODE_MSSQL)
			{
				$data	=	mssql_num_rows($res);
			}
			return $data;
		}
		
		/**
		 * Frees database resource. Only frees the resource if it is specified in the object parameters.
		 * 
		 * @param resource $res		Resource to free (freeee mahi-mahi, freee mahi-mahi)
		 */
		public function free($res)
		{
			if(!$this->free_res)
			{
				return;
			}
			
			if($this->mode == AFRAME_DB_MODE_MYSQL)
			{
				mysql_free_result($res);
			}
			else if($this->mode == AFRAME_DB_MODE_MYSQLI)
			{
				mysqli_free_result($res);
			}
			else if($this->mode == AFRAME_DB_MODE_MSSQL)
			{
				mssql_free_result($res);
			}
		}
	}
?>