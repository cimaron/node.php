#!/usr/bin/php
<?php
require 'builtin/node.php';
require 'builtin/date.php';
require 'builtin/time.php';
require 'lib/console.php';
require 'lib/events.php';
require 'lib/stream.php';
//require 'builtin/bind.php';
require 'builtin/process.php';
require 'modules/fs.php';

$stdout = new StreamWritable(STDOUT);
$stderr = new StreamWritable(STDERR);

new Console($stdout, $stderr);


$script = $_SERVER['argv'][1];
//echo getcwd() . '/' . $script;
if (file_exists(getcwd() . '/' . $script)) {
	Node_PHP::requireModule($script);
}

Node_PHP::loop();
