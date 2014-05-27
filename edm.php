<?php
declare(ticks = 1);

require_once( __DIR__.'/autoload.class.php' );

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
		//print_r( $this->config->getArray('mq') );
		//pcntl_signal(SIGHUP, array(&$this,"restart"));
	}
	protected function msgqueue(){
		$exchangeName = 'email'; //交换机名
		$queueName = 'email'; //队列名
		$routeKey = 'email'; //路由key

		//创建连接和channel
		$connection = new AMQPConnection($this->config->getArray('mq'));

		if (!$connection->connect()) {
			die("Cannot connect to the broker!\n");
		}
		$this->channel = new AMQPChannel($connection);
		$this->exchange = new AMQPExchange($this->channel);
		$this->exchange->setName($exchangeName);
		$this->exchange->setType(AMQP_EX_TYPE_DIRECT); //direct类型
		$this->exchange->setFlags(AMQP_DURABLE); //持久化
		$this->exchange->declare();
		//echo "Exchange Status:".$this->exchange->declare()."\n";

		//创建队列
		$this->queue = new AMQPQueue($this->channel);
		$this->queue->setName($queueName);
		$this->queue->setFlags(AMQP_DURABLE); //持久化
		$this->queue->declare();
		//echo "Message Total:".$this->queue->declare()."\n";

		//绑定交换机与队列，并指定路由键
		$bind = $this->queue->bind($exchangeName, $routeKey);
		//echo 'Queue Bind: '.$bind."\n";

		//阻塞模式接收消息
		while(true){
			//$this->queue->consume('processMessage', AMQP_AUTOACK); //自动ACK应答
			$this->queue->consume(function($envelope, $queue) {
				$msg = $envelope->getBody();
				$queue->ack($envelope->getDeliveryTag()); //手动发送ACK应答
				$this->logging->info('('.'+'.')'.$msg);
				//$this->logging->debug("Message Total:".$this->queue->declare());
			});
			$this->channel->qos(0,1);
			//echo "Message Total:".$this->queue->declare()."\n";
		}
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