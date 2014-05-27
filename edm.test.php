<?php
require_once( __DIR__.'/autoload.class.php' );

$exchangeName = 'email';
$queueName = 'email';
$routeKey = 'email';
$message = empty($argv[1]) ? 'Hello World!' : ' '.$argv[1];
 
$connection = new AMQPConnection(array(
	'host' => '192.168.6.1', 
	'port' => '5672', 
	'vhost' => '/', 
	'login' => 'guest', 
	'password' => 'guest'
	));
$connection->connect() or die("Cannot connect to the broker!\n");
 
$channel = new AMQPChannel($connection);
$exchange = new AMQPExchange($channel);
$exchange->setName($exchangeName);
$queue = new AMQPQueue($channel);
$queue->setName($queueName);
$queue->setFlags(AMQP_DURABLE);
$queue->declare();
$exchange->publish($message, $routeKey);
var_dump("[x] Sent $message");
$connection->disconnect();

