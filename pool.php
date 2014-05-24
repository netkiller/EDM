<?php
class ExampleWorker extends Worker {

	public function __construct(Logging $logger) {
		$this->logger = $logger;
	}

	protected $logger;	
}

/* the collectable class implements machinery for Pool::collect */
class Work extends Stackable {
	public function __construct($number) {
		$this->number = $number;
	}
	public function run() {
		$this->worker
			->logger
			->log("%s executing in Thread #%lu",
				  __CLASS__, $this->worker->getThreadId());
		sleep(1);
		printf("runtime: %s, %d\n", date('Y-m-d H:i:s'), $this->number);
		$this->status = "OK";
	}
}

class Logging extends Stackable {

	protected function log($message, $args = []) {
		$args = func_get_args();	

		if (($message = array_shift($args))) {
			echo vsprintf("{$message}\n", $args);
		}
	}
}

$pool = new Pool(5, \ExampleWorker::class, [new Logging()]);

foreach (range(0, 100) as $number) {
	$pool->submit(new Work($number));
}


$pool->shutdown();

var_dump($pool);
?>
