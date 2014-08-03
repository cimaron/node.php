<?php



function setTimeout($func, $timeout) {
	$timer = new Node_PHP_Timer($func, $timeout);
	return $timer->id;
}


class Node_PHP_Timer {

	public static $timers = array();
	public static $timer = 0;

	public static function check() {
		
		$next = -1;

		for ($i = 0; $i < count(self::$timers); $i++) {
			$timer = self::$timers[$i];
			
			$left = $timer->getRemainingTime();
//			echo "$left left\n";
			if ($left <= 0) {
				$timer->trigger();
				array_splice(self::$timers, $i, 1);
//				Node_PHP_Console::log(self::$timers);
				$i--;
			} else {
				if ($next == -1) {
					$next = $left;
				} else {
					$next = min($next, $left);
				}
			}
			
		}

		return $next;		
	}



	public $id;
	public $func;
	public $when;

	public function __construct($func, $timeout) {
		$this->id = self::$timer++;
		$this->func = $func;
		$now = new Date();
		$this->when = new Date($now->getTime() + (int)$timeout);
		
		self::$timers[] = $this;
	}

	protected function getRemainingTime() {
		$now = new Date();
		return $this->when->getTime() - $now->getTime();
	}

	protected function trigger() {
		return call_user_func($this->func);
	}
}
