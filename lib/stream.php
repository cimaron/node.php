<?php

class StreamWritable {

	public function __construct($stream) {
		$this->stream = $stream;
	}

	public function write($str) {
		fwrite($this->stream, $str);
	}

}