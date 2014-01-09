/**
 * A distribution `Lock` translated from `redis-py`
 * @author Bill Lue
 * @email chinalu@gmail.com
 */
class Lock {
	
	protected $LOCK_FOREVER = 2147483649;
	
	public function __construct($redis, $name, $timeout = null, $sleep = 0.1) {
		$this->redis = $redis;
		$this->name = $name;
		$this->timeout = $timeout;
		$this->sleep = $sleep;
		$this->acquired_until = null;

		if ($timeout && $sleep > $timeout) {
			throw new Exception("'sleep' must be less than 'timeout'");
		}

	}

	public function aquire($blocking = true) {
		$sleep = $this->sleep;
		$timeout = $this->timeout;
		$redis = $this->redis;
		while (true) {
			$unixtime = time();
			if ($timeout) {
				$timeout_at = $unixtime + $timeout;
			} else {
				$timeout_at = $this->LOCK_FOREVER;
			}
			$timeout_at = floatval($timeout_at);
				
			if ($redis->setnx($this->name, $timeout_at)) {
				$this->acquired_until = $timeout_at;
				return true;
			}
				
			$existing = $redis->get($this->name);
			if (!$existing) $existing = 1;
			if ($existing < $unixtime) {
				$existing = $redis->getset($this->name, $timeout_at);
				if (!$existing) $existing = 1;
				
				if ($existing < $unixtime) {
					$this->acquired_until = $timeout_at;
					return true;
				}
			}
			if (!$blocking) {
				return false;
			}
			time.sleep($this->sleep);
		}
	}

	public function release() {
		if (is_null($this->acquired_until)) {
			throw new Exception("Cannot release an unlocked lock");
		}
		$existing = $this->redis->get($this->name);
		if (!$existing) $existing = 1;
		$delete_lock = ($existing >= $this->acquired_until);
		if ($delete_lock) {
			$this->redis->del($this->name);
		}
	}
}