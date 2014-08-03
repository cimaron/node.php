<?php

class Date {

	public static function now() {
		return floor(microtime(true) * 1000 * 1000);
	}

	public function __construct($when = NULL) {
		if ($when === null) {
			$this->time = Date::now();
		} elseif (is_numeric($when)) {
			$this->time = $when;
		} else {
			$this->time = (strtotime($when) * 1000 * 1000);
		}
	}

	public function getTime() {
		return $this->time;
	}

}


