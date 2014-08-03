<?php

class Fs extends EventEmitter {

	public static function readFile($filename, $options = array(), $callback = null) {
		
		if ($callback === null) {
			$callback = $options;
			$options = array();
		}

		$fp = @fopen($filename, "r");
		$data = "";
		$err = null;
		
		if (!$fp) {
			$error = error_get_last();
			$err = new Exception($error['message'], $error['type']);
			call_user_func_array($callback, array($err, $data));
			return false;	
		}

		stream_set_blocking($fp, 0);

		$g = function() use ($fp, &$data, $callback) {

			$new = fread($fp, 65536);
			
			if ($new) {
				$data .= $new;
			}

			if (!feof($fp)) {
				setTimeout($g, 0);
			} else {
				fclose($fp);
				call_user_func_array($callback, array(null, $data));
			}
			
		};

		call_user_func($g);
	}

}

