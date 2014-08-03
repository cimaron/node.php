<?php

setTimeout(function() {
	Console::log('First');
}, 1000);


setTimeout(function() {
	Console::log('Second');
}, 1050);

setTimeout(function() {
	Console::log('Third');
}, 2500);


class Test extends EventEmitter {

	public function __construct() {
		$this->addListener('test', function() {
			setTimeout(function() {
				Console::log('test called');
			}, 100);
		});
	}

	public function test() {
		$this->emit('test');
	}
	
	public function read() {
		$obj = $this;
		Fs::readFile('test.php', function($err, $data) use ($obj) {
			Console::log("test: %s", $data);
			echo Util::inspect($obj, array('colors' => true));
			Console::log($err);
		});
	}
}

$a = new Test();
$a->test();
$a->read();