<?php

class Process extends EventEmitter {

	public function _exit($code = 0) {
		
		$this->emit('exit', array($code));

		exit($code);
	}

}

