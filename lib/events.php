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

require_once('modules/util.php');

class EventEmitter {

	protected static $usingDomains = false;
	
	protected $domain;
	protected $_events;
	protected $_maxListeners;

	// By default EventEmitters will print a warning if more than 10 listeners are
	// added to it. This is a useful default which helps finding memory leaks.
	protected static $defaultMaxListeners = 10;
	

	public function __construct() {
		
		if (self::usingDomains) {
			//@todo
		}
		
		$this->_events = array();		
	}
	
	/**
	 * By default EventEmitters will print a warning if more than 10 listeners are added for a particular event.
	 * This is a useful default which helps finding memory leaks.
	 * Obviously not all Emitters should be limited to 10. This function allows that to be increased.
	 * Set to zero for unlimited.
	 *
	 * @param   int   $n
	 */
	public function setMaxListeners($n) {
		if (!Util::isNumber($n) || $n < 0) {
			throw new Exception('n must be a positive number');
		}
		$this->_maxListeners = $n;
		return $this;
	}
	

	public function emit($type) {
		
		$arguments = func_get_args();
		
		if (!isset($this->_events)) {
			$this->_events = array();
		}
		
		// If there is no 'error' event listener then throw.
		if ($type === 'error' && !isset($this->_events['error'])) {
			
			$er = $arguments[1];
			
			if ($this->domain) {
				if (!$er) {
					$er = new Exception('Uncaught, unspecified "error" event.');
				}
				$er->domainEmitter = $this;
				$er->domain = $this->domain;
				$er->domainThrown = false;
				$this->domain->emit('error', $er);
			} else if ($er instanceof Exception) {
				throw $er; // Unhandeled 'error' event
			} else {
				throw new Exception('Uncaught, unspecified "error" event.');
			}

			return false;
		}
		
		$handler = $this->_events[$type];
		
		if (!isset($handler)) {
			return false;
		}
		
		global $process;
		if ($this->domain && $this !== $process) {
			$this->domain->enter();
		}
		
		if (Util::isFunction($handler)) {
			switch (count($arguments)) {
				// fast cases
				case 1:
					call_user_func($handler);
					break;
				case 2:
					call_user_func($handler, $arguments[1]);
					break;
				case 3:
					call_user_func($handler, $arguments[1], $arguments[2]);
					break;
				// slower
				default:
					$len = count($arguments);
					$args = array();
					for ($i = 1; $i < $len; $i++) {
						$args[$i - 1] = $arguments[$i];
					}
					call_user_func_array($handler, $args);
			}
		} else if (Util::isObject($handler)) {
			$len = count($arguments);
			$args = array();
			for ($i = 1; $i < $len; $i++) {
				$args[$i - 1] = $arguments[$i];
			}
			
			$listeners = array_slice($handlers, 0);
			$len = count($listeners);
			for ($i = 0; $i < $len; $i++) {
				call_user_func_array($listeners[$i], $args);
			}
		}
		
		if ($this->domain && $this !== $process) {
			$this->domain->exit();
		}
		
		return true;
	}

	/**
	 * Adds a listener to the end of the listeners array for the specified event. 
	 *
	 * @param   string     $type
	 * @param   callback   $listener
	 */
	public function addListener($type, $listener) {

		if (!Util::isFunction($listener)) {
			throw new Error("listener must be a function");
		}
		
		if (!isset($this->_events)) {
			$this->_events = array();
		}

		// To avoid recursion in the case that $type === "newListener"! Before
		// adding it to the listeners, first emit "newListener".
  		if (isset($this->_events['newListener'])) {
			$this->emit('newListener', $type,
			            Util::isFunction($listener->listener) ? $listener->listener : $listener);
		}

		if (!isset($this->_events[$type])) {
			// Optimize the case of one listener. Don't need the extra array object.
			$this->_events[$type] = $listener;
		} else if (Util::isArray($this->_events[$type])) {
			// If we've already got an array, just append.
			$this->_events[$type][] = $listener;
		} else {
			// Adding the second element, need to change to array.
			$this->_events[$type] = array($this->_events[$type], $listener);
		}
		
		// Check for listener leak
		if (Util::isArray($this->_events[$type]) && !isset($this->_events[$type]['warned'])) {
			if (isset($this->_maxListeners)) {
				$m = $this->_maxListeners;
			} else {
				$m = EventEmitter::defaultMaxListeners;
			}

			if ($m && $m > 0 && count($this->_events[$type]) > $m) {
				$this->_events[$type]['warned'] = true;
				Console::error('(node) warning: possible EventEmitter memory ' .
				               'leak detected. %d listeners added. ' .
							   'Use $emitter->setMaxListeners() to increase limit.',
							   count($this->_events[$type]));
				Console::trace();
			}
		}
		
		return $this;
	}

	/**
	 * Alias for addListener
	 */
	public function on($type, $listener) {
		return $this->addListener($type, $listener);
	}

	/**
	 * Adds a one time listener for the event. This listener is invoked only the next time the event is fired, after which it is removed. 
	 *
	 * @param   string     $type
	 * @param   callback   $listener
	 */
	public function once($type, $listener) {

		if (!Util::isFunction($listener)) {
			throw new Exception("listener must be a function");
		}

		$fired = false;

		$g = function() use (&$fired, $g) {

			$arguments = func_get_args();

			$this->removeListener($type, $g);
			
			if (!$fired) {
				$fired = true;
				call_user_func_array($listener, $arguments);
			}
			
		};

		$g->listener = $listener;
		$this->on($type, $g);

		return $this;
	}

	/**
	 * Remove a listener from the listener array for the specified event. 
	 * Caution: changes array indices in the listener array behind the listener. 
	 *
	 * @param   string     $type
	 * @param   callback   $listener
	 */
	public function removeListener($type, $listener) {

		if (!Util::isFunction($listener)) {
			throw new Exception("listener must be a function");
		}

		if (empty($this->_events) || !isset($this->_events[$type]) || empty($this->_events[$type])) {
			return $this;
		}

		$list = $this->_events[$type];
		$length = count($list);
		$position = -1;
		
		if ($list === $listener ||
		    (Util::isObject($list) && Util::isFunction($list->listener) && $list->listener === $listener)) {
			unset($this->_events[$type]);
			if (isset($this->_events['removeListener'])) {
				$this->emit('removeListener', $type, $listener);
			}
		} else if (Util::isArray($list)) {
			for ($i = $length; $i-- > 0;) {
				if ($list[$i] === $listener ||
				    (isset($list[$i]->listener) && $list[$i]->listener === $listener)) {
					$position = $i;
					break;
				}
			}
		}
		
		if ($position < 0) {
			return $this;
		}

		if (count($list) === 1) {
			unset($this->_events[$type]);
		} else {
			array_splice($list, $position, 1);
		}

		if (isset($this->_events['removeListener'])) {
			$this->emit('removeListener', $type, $listener);
		}

		return $this;
	}

	/**
	 * Removes all listeners, or those of the specified event.
	 * It's not a good idea to remove listeners that were added elsewhere in the code,
	 * especially when it's on an emitter that you didn't create (e.g. sockets or file streams). 
	 *
	 * @param   string   [$type]   Optional
	 */
	public function removeAllListeners($type = null) {
		
		if (!isset($this->_events)) {
			return $this;
		}

 		// not listening for removeListener, no need to emit
 		if (!isset($this->_events['removeListener'])) {
			if (func_num_args() === 0) {
				$this->_events = array();
			} else if ($this->_events[$type]) {
				unset($this->_events[$type]);
			}
			return $this;
		}

		// emit removeListener for all listeners on all events
		if (func_num_args() === 0) {
			foreach ($this->_events as $key => $val) {
				if ($key === 'removeListener') {
					continue;
				}
				$this->removeAllListeners($key);
			}
			$this->removeAllListeners('removeListener');
			$this->_events = array();
			return $this;
		}

		$listeners = $this->_events[$type];
		
		if (Util::isFunction($listeners)) {
			$this->removeListener($type, $listeners);
		} else if (is_array($listeners)) {
			// LIFO order
			while (count($listeners)) {
				$this->removeListener($type, $listeners[count($listeners) - 1]);
			}
		}
		unset($this->_events[$type]);

		return $this;
	}

	/**
	 * Returns an array of listeners for the specified event.
	 *
	 * @return  array
	 */
	public function listeners($type) {
		
		if (!isset($this->_events) || !isset($this->_events[$type])) {
			$ret = array();
		} else if (Util::isFunction($this->_events[$type])) {
			$ret = array($this->_events[$type]);
		} else {
			$ret = array_slice($this->_events[$type], 0);
		}
		
		return $ret;
	}
	
	/**
	 * Return the number of listeners for a given event.
	 *
	 * @param   EventEmitter   $emitter
	 * @param   string         $event
	 *
	 * @param   int
	 */
	public static function listenerCount($emitter, $type) {
		
		if (!isset($emitter->_events) || !isset($emitter->_events[$type])) {
			$ret = 0;
		} else if (Util::isFunction($emitter->_events[$type])) {
			$ret = 1;
		} else {
			$ret = count($emitter->_events[$type]);
		}
		
		return $ret;
	}
	
}
