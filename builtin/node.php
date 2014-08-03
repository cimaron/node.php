<?php


class Node_PHP {

	public static $rewrite = array();

	public static function loop() {

		$jobs = true;
		while ($jobs) {

			$jobs = false;

			$next = Node_PHP_Timer::check();
			if ($next != -1) {
				$jobs = true;
			}

			if ($jobs) {
				//echo 'sleeping for ' . $next . 'ms';
				usleep($next * 1000);
			}
		}
	}


	public static function requireModule($path) {

		$module = new stdClass;
		$module->exports = $module;

		$code = file_get_contents($path);
		eval(self::rewrite($code));

		return $module->exports;
	}

	public static function rewrite($text) {
		foreach (self::$rewrite as $r) {
			$text = call_user_func($r, $text);
		}
		return $text;
	}


	public static function moduleRewrite($text) {

		$text = preg_replace('#^<\?php#', '', $text);

		$text = str_replace('require(', 'Node_PHP::requireModule(', $text);

		return $text;
	}

}

Node_PHP::$rewrite[] = array('Node_PHP', 'moduleRewrite');



