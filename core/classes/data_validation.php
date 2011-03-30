<?
	/**
	 * Holds aframe's data validation class, mainly used by models for data validation.
	 * For the most part, replaces the input_validation class.
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
	 * Used by models for validating data structures recursively. Has the ability to remove
	 * unwanted data and also validate passed in data using a colleciton of simple types 
	 * (string, integer, array w/ sub-objects) OR specify callbacks for validation
	 * 
	 * Callbacks MUST return boolean true/false.
	 * 
	 * @package		aframe
	 * @subpackage	aframe.core
	 * @author		Andrew Lyon
	 */
	class data_validation extends base
	{
		public static $fake_types	=	array(
			'number',
			'date'
		);

		public static $numeric_types	=	array(
			'int',
			'integer',
			'float',
			'double',
			'real'
		);

		public function validate(&$data, $format, $remove_extra_data, $cast_data = true, $breadcrumbs = '')
		{
			$errors	=	array();

			// if asked of us, remove items in the passed-in data that are not present as
			// items in the $format array
			if($remove_extra_data && !empty($data))
			{
				foreach($data as $key => $validate)
				{
					if(!isset($format[$key]))
					{
						if(is_object($data))
						{
							unset($data->$key);
						}
						else
						{
							unset($data[$key]);
						}
					}
				}
			}

			// loop over the data validation and apply the comparisons/transformations to our data
			foreach($format as $key => $validate)
			{
				// pull out some default params
				$required	=	isset($validate['required']) ? $validate['required'] : false;
				$type		=	isset($validate['type']) ? $validate['type'] : 'string';
				$message	=	isset($validate['message']) ? $validate['message'] : '';

				// the breadcrumb keeps track of how deep the rabbit hole goes
				$breadcrumb	=	empty($breadcrumbs) ? $key : $breadcrumbs . ':' . $key;

				// if required and not found, add it to errors
				if((is_object($data) && !isset($data->$key)) || (is_array($data) && !isset($data[$key])))
				{
					if($required)
					{
						$errors[]	=	data_validation::error($breadcrumb, 'missing', $message);
					}

					// not found, keep going
					continue;
				}

				if(is_object($data))
				{
					$value	=	&$data->$key;
				}
				else
				{
					$value	=	&$data[$key];
				}

				// cast our types
				if($cast_data && !in_array($type, data_validation::$fake_types))
				{
					settype($value, $type);
				}

				// process any transformations (typical things would be strtolower() or uhh hmm, strotoupper()
				if(isset($validate['transform']) && !empty($validate['transform']) && (!is_string($validate['transform']) || function_exists($validate['transform'])))
				{
					$transform	=	$validate['transform'];
					$value		=	call_user_func_array($transform, array($value));
				}

				// validate the type. especially useful if $cast_data is false
				if($type == 'number' || !in_array($type, data_validation::$fake_types))
				{
					$type_fn	=	'is_' . $type;
					if($type == 'number')
					{
						$type_fn	=	'is_numeric';
					}

					// if they passed "array" as a type, it has to be an ordered collection. assoc arrays don't
					// work. pass type "object" instead
					$array_check	=	$type != 'array' || ($type == 'array' && isset($value[0]));

					if(!$type_fn($value) || !$array_check)
					{
						$errors[]	=	data_validation::error($breadcrumb, 'not_' . $type, $message);
						continue;
					}
				}

				// save our errors. if type checking increases the number of errors (ie we had failures), then we
				// continue; the main loop directly after (effectively skipping callbacks). normally we could
				// "continue;" within the switch() statement, but it only works for within the switch. lame.
				$errcount	=	count($errors);

				// do advanced type checking beyond just the normal "is_[type]()" functions
				switch($type)
				{
					// purposely ignore bool
					case 'date':
					case 'string':
					case 'int':
					case 'float':
					case 'double':
					case 'real':
					case 'number':
						$fn	=	'validate_' . $type;

						// we really only need one number validation function, so if we get ANY numbers, pass them
						// along to it. this especially true since all of the type checking has already happened
						// above.
						if(in_array($type, data_validation::$numeric_types))
						{
							$fn	=	'validate_number';
						}

						if(($error = data_validation::$fn($value, $validate)) !== true)
						{
							$errstr		=	$type;
							if(is_string($error))
							{
								$errstr	.=	':'.$error;
							}
							$errors[]	=	data_validation::error($breadcrumb, $errstr, $message);
						}
						break;
					case 'object':
					case 'array':
						// we're going to have to check recursively
						if(isset($validate['format']))
						{
							if($type == 'object')
							{
								// recurse one layer down, save errors into $err
								$err	=	data_validation::validate($value, $validate['format'], $remove_extra_data, $cast_data, $breadcrumb);
							}
							else
							{
								$err	=	array();
								for($i = 0, $n = count($value); $i < $n; $i++)
								{
									$breadcrumb_a	=	$breadcrumb . ':'.$i;
									$error_a		=	data_validation::validate($value[$i], $validate['format'], $remove_extra_data, $cast_data, $breadcrumb_a);
									if(!empty($error_a))
									{
										// only add it to our local errors of we got an error (not just an empty array)
										$err	=	array_merge($err, $error_a);
									}
								}
							}

							if(!empty($err))
							{
								// only merge in our errors if we got any =]
								$errors	=	array_merge($errors, $err);
							}
						}
						break;
				}

				// do an error check to make sure we got no new errors when type checking. if we got new errors,
				// skip callback processing since it's generally expensive.
				if(count($errors) > $errcount)
				{
					continue;
				}

				// process our callback, if it exists. we do this last because generally a callback is a call
				// to a database or api somewhere. this makes it generally the most expensive, meaning we don't
				// want to take two whole seconds to process the callback just to find out that the string we're
				// using is invalid. better to validate all the easy shit first, THEN run the callback.
				if(isset($validate['callback']) && !empty($validate['callback']))
				{
					$check	=	call_user_func_array(
						$validate['callback'],
						array(
							$data[$key],
							$validate
						)
					);

					// if callback failed, format a string to send back detailing what happened
					if(!$check || is_string($check))
					{
						$callback	=	$validate['callback'];
						if(is_array($callback) && is_object($callback[0]))
						{
							if(is_object($callback[0]))
							{
								$callback[0]	=	get_class($callback[0]);
							}
						}

						// allow the callback to pass a custom message back to the app
						if(is_string($check))
						{
							$message	=	$check;
						}

						$errors[]	=	data_validation::error(
							$breadcrumb,
							'callback_failed:'. $callback[0] . '->' . $callback[1] .'('. print_r($data[$key], true) .')',
							$message
						);
					}
				}
			}

			return $errors;
		}

		public function validate_string($value, $validate)
		{
			// process length meta language
			if(isset($validate['length']) && preg_match('/^([><]=?)?[0-9]+$/', $validate['length']))
			{
				$length		=	$validate['length'];
				$compare	=	$length[0];
				$len		=	strlen($value);
				if(is_numeric($compare))
				{
					$compare	=	(int)$length;
					if($len < $compare)
					{
						return 'length-short';
					}
					else if($len > $compare)
					{
						return 'length-long';
					}
				}
				else
				{
					$equal		=	$length[1];
					if($equal == '=')
					{
						$length		=	(int)substr($length, 2);

						// note the sign reversla in the following ifs...because we're testing for a NOT match
						if($compare == '<' && $len > $length)
						{
							return 'length-long';
						}
						else if($compare == '>' && $len < $length)
						{
							return 'length-short';
						}
					}
					else
					{
						$length		=	(int)substr($length, 1);

						// note the sign reversla in the following ifs...because we're testing for a NOT match
						if($compare == '<' && $len >= $length)
						{
							return 'length-long';
						}
						else if($compare == '>' && $len <= $length)
						{
							return 'length-short';
						}
					}
				}
			}

			// process regex patterns
			if(isset($validate['pattern']) && !empty($validate['pattern']))
			{
				if(!preg_match($validate['pattern'], $value))
				{
					return 'pattern';
				}
			}

			// YESSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSSsssSSsssSSSssSSs!!!
			return true;
		}

		public function validate_number($value, $validate)
		{
			if(isset($validate['min']) && $value < $validate['min'])
			{
				return 'min';
			}

			if(isset($validate['max']) && $value > $validate['max'])
			{
				return 'max';
			}
			return true;
		}

		public function validate_date($value, $validate)
		{
			if(strtotime($value) === false)
			{
				return false;
			}
			return true;
		}

		public function error($key, $type, $message = '')
		{
			if(empty($message))
			{
				$message	=	$type;
			}
			return array('key' => $key, 'type' => $type, 'message' => $message);
		}
	}
?>
