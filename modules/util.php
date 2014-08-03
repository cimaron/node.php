<?php

class Util {

	/**
	 * Returns a formatted string using the first argument as a printf-like format.
	 *
	 * The first argument is a string that contains zero or more placeholders.
	 * Each placeholder is replaced with the converted value from its corresponding argument.
	 * Supported placeholders are:
	 *    %s - String.
	 *    %d - Number (both integer and float).
	 *    %j - JSON.
	 *    % - single percent sign ('%'). This does not consume an argument.
	 */
	public static function format($f) {

		$arguments = func_get_args();
		$formatRegExp = '/%[sdj%]/';

		if (!is_string($f)) {
			$objects = array();
			for ($i = 0; $i < count($arguments); $i++) {
				$objects[] = self::inspect($arguments[$i]);
			}
			return implode(' ', $arguments);
		}
		
		$i = 1;
		$args = $arguments;
		$len = count($args);
		
		$str = preg_replace_callback($formatRegExp, function($x) use (&$i, $len, $args) {

			$x = $x[0];
		
			if ($x == '%%') {
				return '%';
			}
			if ($i >= $len) {
				return $x;
			}
			switch ($x) {
				case '%s':
					return (string)$args[$i++];
				case '%d':
					return (float)$args[$i++];
				case '%j':
					try {
						json_encode($args[$i++]);
					} catch (Exception $e) {
						return '[Circular]';
					}
				default:
					return $x;
			}		
		}, $f);
		
		if ($i < $len) {
			for ($x = $args[$i]; $i < $len; $x = $args[++$i]) {
				if ($x === null || !(is_object($x) || is_array($x))) {
					$str += ' ' . $x;
				} else {
					$str += ' ' + self::inspect($x);
				}
			}
		}
		return $str;
	}

	/**
	 * Echos the value of a value. Trys to print the value out
	 * in the best way possible given the different types.
	 *
	 * @param {Object} obj The object to print out.
	 * @param {Object} opts Optional options object that alters the output.
	 */
	/* legacy: obj, showHidden, depth, colors*/
	public static function inspect($obj, $opts = null) {
		
		$arguments = func_get_args();
		
		// default options
		$ctx = array(
			'seen' => array(),
			'stylize' => array('Util', 'stylizeNoColor'),
		);
		
 		// legacy...
 		if (count($arguments) >= 3) {
			$ctx['depth'] = $arguments[2];
		}
		
		if (count($arguments) >= 4) {
			$ctx['colors'] = $arguments[3];
		}
		
		if (is_bool($opts)) {
			//Legacy
			$ctx['showHidden'] = $opts;
		} else if (is_array($opts) || is_object($opts)) {
 			// got an "options" object
 			$ctx = array_merge($ctx, (array)$opts);
		}
		// set default options
		if (!isset($ctx['showHidden'])) {
			$ctx['showHidden'] = false;
		}

		if (!isset($ctx['depth'])) {
			$ctx['depth'] = 2;
		}

		if (!isset($ctx['colors'])) {
			$ctx['colors'] = false;
		}

		if (!isset($ctx['customInspect'])) {
			$ctx['customInspect'] = true;
		}

		if ($ctx['colors']) {
			$ctx['stylize'] = array('Util', 'stylizeWithColor');
		}

		return self::formatValue($ctx, $obj, $ctx['depth']);
	}

	public static $inspect = array(
		'colors' => array(
			'bold' => array(1, 22),
			'italic' => array(3, 23),
			'underline' => array(4, 24),
			'inverse' => array(7, 27),
			'white' => array(37, 39),
			'grey' => array(90, 39),
			'black' => array(30, 39),
			'blue' => array(34, 39),
			'cyan' => array(36, 39),
			'green' => array(32, 39),
			'magenta' => array(35, 39),
			'red' => array(31, 39),
			'yellow' => array(33, 39)
		),
		'styles' => array(
			'special' => 'cyan',
			'number' => 'yellow',
			'boolean' => 'yellow',
			'undefined' => 'grey',
			'null' => 'bold',
			'string' => 'green',
			'date' => 'magenta',
			// "name" => intentionally not styling
			'regexp' => 'red'
		),
	);

	protected static function stylizeWithColor($str, $styleType) {
		
		$style = self::$inspect['styles'][$styleType];
		
		if ($style) {
			return json_decode('"\u001b"') . "[" . self::$inspect['colors'][$style][0] . "m" . $str .
			       json_decode('"\u001b"') . "[" . self::$inspect['colors'][$style][1] . "m";
		} else {
			return $str;
		}
	}

	protected static function stylizeNoColor($str, $styleType) {
		return $str;
	}

	protected static function formatValue($ctx, $value, $recurseTimes) {
		
		// Provide a hook for user-specified inspect functions.
		// Check that value is an object with an inspect function on it
		if ($ctx['customInspect'] &&
		    $value &&
			(isset($value->inspect) && self::isFunction($value->inspect)) &&
			// Filter out the util module, it's inspect function is special
			$value->inspect !== array('Util', 'inspect') &&
			// Also filter out any prototype objects using the circular check
			true // N/A
			) {
			$ret = $value->inspect($recurseTimes, $ctx);
			if (!is_string($ret)) {
				$ret = self::formatValue($ctx, $ret, $recursdTimes);
			}
			return $ret;
		}
		
		// Primitive types cannot have properties
		$primitive = self::formatPrimitive($ctx, $value);
		if ($primitive) {
			return $primitive;
		}

		// Look up keys of the object
		$keys = array_keys(is_array($value) ? $value : get_object_vars($value));
		//$visibleKeys = self::arrayToHash($keys);
		$visibleKeys = $keys; 
		
		if ($ctx['showHidden']) {
			//N/A
		}
		
		// This could be a boxed primitive (new String(), etc.), check valueOf()
		// NOTE: Avoid calling `valueOf` on `Date` instance because it will return
		// a number which, when object has some additional user-stored `keys`,
		// will be printed out.		
		$raw = $value;
		
		//...
		
		if (count($keys) === 0) {
			
			if (self::isFunction($value)) {
				$name = is_array($value) ? ': ' . $value[1] : (is_string($value) ? ': ' . $value : "");
				return call_user_func($ctx['stylize'], '[Function' . $name . ']', 'special');
			}
			
			if (self::isRegExp($value)) {
				return call_user_func($ctx['stylize'], $value, 'regexp');
			}
			/*
			if (self::isError($value)) {
				return self::formatError($value);
			}
			*/
			
			if (is_string($raw)) {
				$formatted = self::formatPrimitiveNoColor($ctx, $raw);
				return call_user_func($ctx['stylize'], '[String: ' . $formatted . ']', 'string');
			}
		}
		
		return '';
		exit;
		
	}
	
	/**
	 *
	 */
	protected function formatPrimitive($ctx, $value) {
		if ($value === null) {
			//return call_user_func($ctx['stylize'], 'undefined', 'undefined');
		}
		if (is_string($value)) {
			$simple = "'" . preg_replace('/^"|"$/', '', 
			                preg_replace("/'/", "\\'",
							preg_replace('/\\"/', '"', json_encode($value)))) . "'";
			return call_user_func($ctx['stylize'], $simple, 'string');
		}
		if (is_numeric($value)) {
    		// Format -0 as '-0'. Strict equality won't distinguish 0 from -0,
    		// so instead we use the fact that 1 / -0 < 0 whereas 1 / 0 > 0 .
			// N/A in php
			return call_user_func($ctx['stylize'], (string)$value, 'number');
		}
		if (is_bool($value)) {
			return call_user_func($ctx['stylize'], (string)$value, 'boolean');
		}
		if (is_null($value)) {
			return call_user_func($ctx['stylize'], 'null', 'null');
		}		
	}



	public static function isRegExp($re) {
		return is_string($re) && preg_match('#^/.*/[imsxe]*$', $re);
	}








	public static function isFunction($func) {
		return is_callable($func);
	}

	public static function isObject($obj) {
		return is_object($obj);
	}
	
	public static function isDate($obj) {
		return $obj instanceof Date;
	}
	
	public static function isArray($arr) {
		return is_array($arr);
	}

}

