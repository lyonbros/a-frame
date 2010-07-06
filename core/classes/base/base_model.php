<?
	/**
	 * This file holds the base model, which is extended by all models to give them SUPER abilities
	 * such as automatic insert/updates, error checking, etc.
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
	 * Include the input validation class
	 */
	include_once CLASSES . '/input_validation.php';
	
	/**
	 * Basic communication layer to database. Mimics a lot of the CakePHP base model DB layer. ALSO
	 * does basic error checking with the aframe.includes.classes.input_validation class. Basically,
	 * provides an abstraction and simplification to model building, DB communication, and error
	 * checking.
	 * 
	 * @package		aframe
	 * @subpackage	aframe.core
	 * @author		Andrew Lyon
	 */

	class base_model extends base
	{
		/**
		 * What table we're operating on
		 * @var string
		 */
		var $table	=	'';

		/**
		 * our database object
		 * @var object
		 */
		var $db;
		
		/**
		 * our input validator
		 * @var object
		 */
		var $inp;
		
		/**
		 * The error var, the error var; it tells us quite a lot. It tells when there is
		 * an error, and it tells us when there's not. (form validation error)
		 * @var bool
		 */
		var $error	=	false;
		
		/**
		 * The notation to define a field in the DB usually ` or '
		 * @var string
		 */
		var $field_note;
		
		/**
		 * The function run to get the current datetime on our DB
		 * @var string
		 */
		var $now_func;
		
		/**
		 * Suppress automatic create_date / mod_date population on insert / update
		 * @var bool
		 */
		var $suppress_auto_timestamp	=	false;
		
		/**
		 * The name of the current tables primary key field. Defaults to 'id'. Used by base_model::save()
		 * @var string
		 */
		var $id_key	=	'id';
		
		
		/**
		 * Init function, calls Base::_init() and loads the DB class.
		 * 
		 * @param object &$event	Event object
		 */
		function _init(&$event)
		{
			parent::_init($event);
			
			if(DATABASE)
			{
				$this->db	=	&$event->object('db');
				if($this->db->mode == AFRAME_DB_MODE_MYSQL)
				{
					$this->field_note	=	"`";
					$this->now_func		=	"NOW()";
					$this->last_id		=	"last_insert_id()";
				}
				else if($this->db->mode == AFRAME_DB_MODE_MYSQLI)
				{
					$this->field_note	=	"`";
					$this->now_func		=	"NOW()";
					$this->last_id		=	"last_insert_id()";
				}
				else if($this->db->mode == AFRAME_DB_MODE_MSSQL)
				{
					$this->field_note	=	"";
					$this->now_func		=	"GetDate()";
					$this->last_id		=	"@@IDENTITY";
				}
			}
			
			$this->inp	=	&$event->object('input_validation', array(&$event));
		}
		
		/**
		 * Doesn't do much right now...takes a table name and applies the DB prefix to it.
		 * 
		 * @param string $tbl_name	'Key' of table to return.
		 * @return string			Table name
		 */
		function tbl($tbl_name)
		{
			$prefix	=	$this->config['db']['prefix'];
			$name	=	$prefix . $tbl_name;
			return $name;
		}

		
		/**
		 * Super smart function. Give it your data, it updates/inserts it and gives you an id.
		 * If $data[base_model::$id_key] exists, it does an update on id = !, otherwise does an insert. Both
		 * operate on $this->table, much like CakePHP's base model.
		 * 
		 * @param array $data				data to insert / update
		 * @param array $functions			(optional) array containing SQL functions to be applied to data param
		 * @param array $duplicate_check	(optional) array of fields to use for duplicate checking
		 * @param bool	$runUpdate			(optional) if duplicate is found, update it with $data?
		 * @return integer					id of updated / inserted item
		 */
		function save($data, $functions = array(), $duplicate_check = array(), $update = false, $dup_false = false)
		{
			if(!isset($data[$this->id_key]) && !empty($duplicate_check))
			{
				// If the data was a duplicate, return the id to the original
				if($id = $this->duplicate_check($data, $duplicate_check))
				{
					if(!$update && !$dup_false)
					{
						return $id;
					}
					elseif($dup_false)
					{
						return false;
					}
					else
					{
						$data[$this->id_key]	=	$id;
					}
				}
			}
			
			// check whether we should insert or update
			if(isset($data[$this->id_key]))
			{
				// we have an 'id' in the data, assume update
				$id			=	$data[$this->id_key];
				unset($data[$this->id_key]);
				$this->run_update($id, $data, $functions);
				return $id;
			}
			else
			{
				// no 'id', probably an insert
				$id	=	$this->run_insert($data, $functions);
				return $id;		
			}
		}
		
		/**
		 * Takes an array of data and uses Key => Value pairing to build an INSERT query. 
		 * 
		 * Takes a condition (usually something like "id = 5"). Also has a functions array
		 * for running functions on certain fields...uses Field => Function pairing. So if 
		 * you have a field 'pass' and you need to run the PASSWORD() function on it, pass 
		 * array('pass' => 'PASSWORD') into the functions array, which will yield a
		 * pass = PASSWORD('value') in the query.
		 * 
		 * @param array $data		data to update DB with
		 * @param string $condition	condition upon which to update
		 * @param array $functions	(optional) functions to be applied to $data
		 * @param integer $limit	(optional) number of records to update (defaults to all)
		 * @access					private
		 */
		function run_update($id, $data, $functions = array())
		{
			$fields	=	"";
			$params	=	array();
			foreach($data as $field => $value)
			{
				$field	=	str_replace('`', '', $field);
				$fields	.=	$this->field_note . $field . $this->field_note . '=';
				
				if(isset($functions[$field]))
				{
					$fields	.=	$functions[$field] . "(";
				}
				
				if(is_numeric($value) && $value{0} != '0')
				{
					$fields		.=	"!";
					$params[]	=	$value;
				}
				else
				{
					$fields		.=	"?";
					$params[]	=	$value;
				}
				
				if(isset($functions[$field]))
				{
					$fields	.=	")";
				}
				$fields	.=	",";
			}
			
			if($this->suppress_auto_timestamp)
			{
				$fields	=	substr($fields, 0, -1) ." ";
			}
			else
			{
				$fields	.=	$this->field_note . "mod_date". $this->field_note ." = ". $this->now_func ." ";
			}

			$qry	=	"
				UPDATE
					". $this->table ."
				SET
					". $fields ."
				WHERE
					". $this->id_key ." = !
				LIMIT 1
			";
			
			$params[]	=	$id;
			$this->db->execute($qry, $params);
		}
		
		/**
		 * Inserts an array of data into $this->table.
		 * 
		 * Uses Key => Value pairing from data array to generate (FIELDS) VALUES () lists. 
		 * 
		 * @param array $data		array of data to insert into database
		 * @param array $functions	(optional) array of functions to apply to $data
		 * @return integer			id of inserted data
		 * @access					private
		 */
		function run_insert($data, $functions = array())
		{
			$params	=	array();
			$fields	=	"";
			$values	=	"";
			foreach($data as $field => $value)
			{
				// build field list
				$fields	.=	$this->field_note . str_replace('`', '', $field) . $this->field_note . ",";
				
				// build value list
				if(isset($functions[$field]))
				{
					$values	.=	$functions[$field] . "(";
				}
				if(is_numeric($value) && $value{0} != '0')
				{
					$values		.=	"!";
					$params[]	=	$value;
				}
				else
				{
					$values		.=	"?";
					$params[]	=	$value;
				}
				if(isset($functions[$field]))
				{
					$values	.=	")";
				}
				$values	.=	",";
			}
			
			// check our auto timestamp updating
			if($this->suppress_auto_timestamp)
			{
				$fields	=	substr($fields, 0, -1);
				$values	=	substr($values, 0, -1);
			}
			else
			{
				$fields	.=	$this->field_note ."create_date". $this->field_note;
				$values	.=	$this->now_func;
			}
			
			// build final query
			$qry	=	"
				INSERT INTO
					". $this->table ."
					(". $fields .")
				VALUES
					(". $values .")
			";

			$this->db->execute($qry, $params);
			
			$res	=	$this->last_id();
			return $res;
		}
		
		/**
		 * Wrapper around base_model::_delete() that deletes an item with id = $id from $this->table.
		 * 
		 * @param integer $id		id of item to delete
		 * @param integer $limit 	(optional) Amount of items to delete (defaults to all)
		 */
		function delete($id, $limit = '')
		{
			if(!is_numeric($id))
			{
				$id	=	"'". $this->db->escape($id) ."'";
			}
			$this->_delete("id = ". $id, $limit);
		}
		
		/**
		 * Get the last insert ID from table in base_model::$table
		 * 
		 * @return integer	The ID of the last object inserted into base_model::$table
		 */
		function last_id()
		{
			// make SURE we get the ID from the right server
			$this->db->use_master();
			
			$qry	=	"
				SELECT
					". $this->last_id ." AS id
				FROM ". $this->table ."
			";
			$res	=	$this->db->one($qry);
			
			return $res;
		}
		
		/**
		 * Runs a delete on $this->table using given condition
		 * 
		 * @param string $condition	contains the condition upon which to delete
		 * @param integer $limit	(optional) number of total items to delete 
		 * @access					private
		 */
		function _delete($condition, $limit = '')
		{
			$qry	=	"DELETE FROM ". $this->table ." WHERE ". $condition ." " . $limit;
			$this->db->query($qry);
		}
		
		/**
		 * Check an item for duplicates before inserting/updating
		 * 
		 * @param array $item		The data we are checking duplicates on
		 * @param array $fields		Fields (array keys) within $item which we will base the check off of. All fields
		 * 							in $fields must match exactly for a duplicate to be flagged.
		 * @return integer			The idea of the duplicated data in the DB (if found), otherwise false (no match)
		 */
		function duplicate_check($item, $fields)
		{
			$where	=	"1 = 1 ";
			$params	=	array();
			
			for($i = 0, $n = count($fields); $i < $n; $i++)
			{
				if(is_numeric($item[$fields[$i]]))
				{
					$where		.=	"AND ". $fields[$i] ." = ! ";
				}
				else
				{
					$where		.=	"AND ". $fields[$i] ." = ? ";
				}
				$params[]	=	$item[$fields[$i]];
			}
			
			$qry	=	"
				SELECT
					". $this->id_key ."
				FROM
					". $this->table ."
				WHERE
					". $where ."
				LIMIT 1
			";
			$check	=	$this->db->one($qry, $params);
			
			if($check > 0)
			{
				return $check;
			}
			return false;
		}

		/**
		 * Add a form value into the validation system for checking. Allows developer
		 * to check all values at once instead of going through each one by one.
		 * 
		 * @param string $key		Array key of item. 'username' would pull up $data['username']
		 * @param string $type		One of the predefined types. See input_validation::check()
		 * @param bool $required	Is the form field required
		 * @param string $msg		Error message to spit out upon input error
		 * @param int $length		For strings...maximum length
		 * @return					Absolutely nothing
		 */
		function add_val($key, $type, $required, $msg, $length = -1)
		{
			$this->inp->add($key, $type, $required, $msg, $length); 
		}
		
		/**
		 * Check a form field for a specific input type.
		 * 
		 * @param mixed $value		Value to be checked by input validator
		 * @param string $type		Type of field being checked
		 * @return					Bool incidicating success
		 */
		function validate($data)
		{
			return $this->inp->check($data);
		}
		
		/**
		 * Add an error to the form system to let the user know of an input validation
		 * error.
		 */
		function err($msg, $field)
		{
			$this->msg->add($msg);
			$form_errors	=	$this->event->get('form_errors', array());
			$form_errors[]	=	$field;
			$this->event->set('form_errors', $form_errors);
			$this->error	=	true;
		}
		
		/**
		 * Called after the entire app is done processing. Can be used to close any
		 * 3rd party DB connections and such. Useful for general model cleanup.
		 */
		function _post()
		{
		}
	}
?>
