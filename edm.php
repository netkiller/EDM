<?php
declare(ticks = 1);

function __autoload($className) {
	$className = strtolower($className);
    if (file_exists(__DIR__.'/'.$className . '.class.php')) {
		require_once( __DIR__.'/'.$className . '.class.php' );
	}else{
		throw new Exception('Class "' . $className . '.class.php" could not be autoloaded');
	}
}
umask(077);	

class EDM {
	protected $queue;
	public function __construct() {
		global $argc, $argv;
		$this->argc = $argc;
		$this->argv = $argv;
		$this->pidfile = $this->argv[0].".pid";
		$this->config = new Config('mq');
		$this->logging = new Logging(__DIR__.'/log/'.$this->argv[0].'.'.date('Y-m-d').'.log'); //.H:i:s
		//echo $this->config->get('host');		
		
		/*
			pcntl_signal(SIGHUP, function ($signal) {
				$this->quit = true;
				echo 'HANDLE SIGNAL ' . $signal . PHP_EOL;
			});
		*/
		pcntl_signal(SIGHUP, array(&$this,"restart"));	
	}
	protected function msgqueue(){
		$exchangeName = 'email'; //交换机名
		$queueName = 'email'; //队列名
		$key_route = 'key_1'; //路由key

		//创建连接和channel
		$conn = new AMQPConnection(array(
			'host' => '192.168.6.1',
			'port' => '5672',
			'login' => 'guest',
			'password' => 'guest',
			'vhost'=>'/'
		));

		if (!$conn->connect()) {
			die("Cannot connect to the broker!\n");
		}
		$channel = new AMQPChannel($conn);
		$ex = new AMQPExchange($channel);
		$ex->setName($exchangeName);
		$ex->setType(AMQP_EX_TYPE_DIRECT); //direct类型
		$ex->setFlags(AMQP_DURABLE); //持久化
		//echo "Exchange Status:".$ex->declare()."\n";

		//创建队列
		$this->queue = new AMQPQueue($channel);
		$this->queue->setName($queueName);
		$this->queue->setFlags(AMQP_DURABLE); //持久化
		//echo "Message Total:".$this->queue->declare()."\n";

		//绑定交换机与队列，并指定路由键
		$bind = $this->queue->bind($exchangeName, $key_route);
		//echo 'Queue Bind: '.$bind."\n";

		//阻塞模式接收消息
		//while(True){
			//$this->queue->consume('processMessage', AMQP_AUTOACK); //自动ACK应答
			$this->queue->consume(function($envelope, $queue) {
				$msg = $envelope->getBody();
				$queue->ack($envelope->getDeliveryTag()); //手动发送ACK应答
				$this->logging->info('('.'+'.')'.$msg);
				$this->logging->debug("Message Total:".$this->queue->declare());
			});
			echo "Message Total:".$this->queue->declare()."\n";
		//}
		$conn->disconnect();
	}

	protected function start(){
		if (file_exists($this->pidfile)) {
			printf("%s already running\n", $this->argv[0]);
			exit(0);
		}
		$this->logging->warning("start");
		$pid = pcntl_fork();
		if ($pid == -1) {
			die('could not fork');
		} else if ($pid) {
			//pcntl_wait($status); //等待子进程中断，防止子进程成为僵尸进程。
			exit(0);
		} else {
			posix_setsid();
			//printf("pid: %s\n", posix_getpid());
			file_put_contents($this->pidfile, posix_getpid());
			
			//posix_kill(posix_getpid(), SIGHUP);
			
			$this->msgqueue();
		}
	}
	protected function stop(){
		if (file_exists($this->pidfile)) {
			$pid = file_get_contents($this->pidfile);
			posix_kill($pid, SIGTERM);
			//posix_kill($pid, SIGKILL);
			unlink($this->pidfile);
			$this->logging->warning("stop");
		}else{
			printf("%s haven't running\n", $this->argv[0]);
		}
	}
	protected function restart(){
		$this->stop();
		$this->start();	
	}
	protected function status(){
		if (file_exists($this->pidfile)) {
			$pid = file_get_contents($this->pidfile);
			printf("%s already running, pid = %s\n", $this->argv[0], $pid);
		}else{
			printf("%s haven't running\n", $this->argv[0]);
		}
	}
	protected function usage(){
		printf("Usage: %s {start | stop | restart | status}\n", $this->argv[0]);
	}

	public function main(){
		//print_r($this->argv);
		if($this->argc != 2){
			$this->usage();
		}else{
			if($this->argv[1] == 'start'){
				$this->start();
			}else if($this->argv[1] == 'stop'){
				$this->stop();
			}else if($this->argv[1] == 'restart'){
				$this->restart();
			}else if($this->argv[1] == 'status'){
				$this->status();
			}else{
				$this->usage();
			}
		}
	}
}

$edm = New EDM();
$edm->main();