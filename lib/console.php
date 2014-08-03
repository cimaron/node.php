<?php
// Copyright Joyent, Inc. and other Node contributors.
//
// Permission is hereby granted, free of charge, to any person obtaining a
// copy of this software and associated documentation files (the
// "Software"), to deal in the Software without restriction, including
// without limitation the rights to use, copy, modify, merge, publish,
// distribute, sublicense, and/or sell copies of the Software, and to permit
// persons to whom the Software is furnished to do so, subject to the
// following conditions:
//
// The above copyright notice and this permission notice shall be included
// in all copies or substantial portions of the Software.
//
// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
// OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
// MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN
// NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM,
// DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR
// OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE
// USE OR OTHER DEALINGS IN THE SOFTWARE.

require('modules/util.php');
//require('modules/assert.php');

class Console {

	public static function __invoke($stdout = null, $stderr = null) {
		return new Console($stdout, $stderr);
	}

	public static $instance;

	public function __construct($stdout = null, $stderr = null) {
		
		if (!$stdout || (is_object($stdout) && !method_exists($stdout, 'write'))) {
			throw new Exception('Console expectes a writable stream instance');
		}
		
		if (!$stderr) {
			$stderr = $stdout;
		}
		
		$this->_stdout = $stdout;
		$this->_stderr = $stderr;
		$this->_times = array();
		
		if (!self::$instance) {
			self::$instance = $this;
		}		
	}

	public function log() {
		$This = (isset($this) ? $this : self::$instance);
		$This->_stdout->write(call_user_func_array(array('Util', 'format'), func_get_args()) . "\n");	
	}
	
	public function info() {
		return call_user_func_array(array(isset($this) ? $this : self::$instance, 'log'), func_get_args());
	}

	public function warn() {
		$This = (isset($this) ? $this : self::$instance);
		$This->_stderr->write(call_user_func_array(array('Util', 'format'), func_get_args()) . "\n");
	}

	public function error() {
		return call_user_func_array(array(isset($this) ? $this : self::$instance, 'warn'), func_get_args());		
	}

	public function dir($object) {
		$This = (isset($this) ? $this : self::$instance);
		$This->_stdout->write(Util::inspect($object, array('customInspect' => false)) . "\n");
	}

	public function time($label) {
		$This = (isset($this) ? $this : self::$instance);
		$This->_times[$label] = Date::now();
	}

	public function timeEnd($label) {
		$This = (isset($this) ? $this : self::$instance);
		$time = $This->_times[$label];
		if (!$time) {
			throw new Exception('No such label: ' . $label);
		}
		$duration = Date::now() - $time;
		$This->log('%s: %dms', $label, $duration);
	}

	public function trace() {
		$bt = debug_backtrace();

		$err = new stdClass;
		$err->name = 'Trace';
		$err->message = call_user_func_array(array('Util', 'format'), func_get_args());
		Error::captureStackTrace($err, $bt);
		$This = (isset($this) ? $this : self::$instance);
		$This->error($err->stack);
	}

	public function assert($expression) {
		if (!$expression) {
			$arr = array_slice(func_get_args(), 1);
			Assert::ok(false, call_user_func_array(array('Util', 'format'), $arr));
		}
	}


	/*
	protected static function prepareKey($key) {

		if (!preg_match('#^[a-zA-Z0-9_]+$#', $key)) {
			$key = "'" . str_replace(array("'", "\n", "\t"), array("\\'", "\\n", "\\t"), $key) . '"';
		}

		return $key;
	}

	protected static function prepare($var, $depth = 0) {

		if (is_bool($var)) {
			return $var ? 'true' : 'false';
		}

		if (is_numeric($var)) {
			return $var;
		}

		if (is_string($var)) {
			return "'" . $var . "'";
		}

		if (is_array($var)) {
			$ret = array();
			if (!empty($var)) {
				$max = max(array_keys($var));
				for ($i = 0; $i < $max + 1; $i++) {
					$ret[] = isset($var[$i]) ? self::prepare($var[$i], $depth + 1) : '';
				}
			}
			$implode = count($ret) <= 7 ? ', ' : ",\n" . str_repeat("  ", $depth + 1);
			return '[ ' . implode($implode, $ret) . ' ]';
		}
		
		if (is_object($var)) {
			$ret = array();
			foreach ($var as $key => $value) {
				$ret[] = self::prepareKey($key) . ': ' . self::prepare($value, $depth + 1);
			}
			$implode = count($ret) <= 7 ? ', ' : ",\n" . str_repeat("  ", $depth + 1);
			return '{ ' . implode($implode, $ret) . ' }';
		}

		return (string)$var;
	}

	public static function moduleRewrite($text) {

		//$text = str_replace('$console->', 'Console::', $text);
		
		return $text;
	}
	*/
}

