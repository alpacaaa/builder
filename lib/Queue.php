<?php

/*
	Forked from: http://github.com/isofarro/php5-queue/
	Original Author: Mike Davies

*/


class QueueItem {

	const PRIORITY_HIGH 	= 100;
	const PRIORITY_MEDIUM	= 10;
	const PRIORITY_LOW		= 1;
	
	const STATUS_SUCCESS	= 5;
	const STATUS_FAILED		= 6;
	const STATUS_PROCESSING	= 7;
	const STATUS_PENDING	= 8;

	protected $obj;
	
	protected $created;
	protected $started;
	protected $status;
	protected $priority;
	
	public function __construct($obj, $priority = self::PRIORITY_MEDIUM) {
		$this->obj       = $obj;
		$this->priority  = $priority;
		$this->created   = time();
		$this->started   = 0;
		$this->status    = self::STATUS_PENDING;
	}
	
	public function getObject() {
		return $this->obj;
	}
	
	public function getCreated() {
		return $this->created;
	}
	
	public function getStarted() {
		return $this->startTime;
	}
	
	public function activate() {
		$this->started = time();
		$this->status  = self::STATUS_PROCESSING;
	}
	
	public function isCompleted() {
		return $this->status == self::STATUS_SUCCESS
			|| $this->status == self::STATUS_FAILED;
	}
	
	public function setStatus($status = self::STATUS_SUCCESS) {
		$this->status = $status;
	}

	public function getPriority() {
		return $this->priority;
	}
	
	public function setPriority($priority = self::PRIORITY_MEDIUM) {
		$this->priority = $priority;
	}

}

abstract class QueueStorage {
	protected $config;
	protected $queue;
	protected $pointer;
	protected $updated = false;

	public function __construct($config=false) {
		if ($config !== false) {
			$this->setConfig($config);
		}
	}

	public function __destruct() {
		if ($this->waitForLock()) {
			$this->close();
			$this->unlock();
		}
	}
	
	
	public function setConfig($config) {
		$this->config = $config;

		$this->init();

		if ($this->waitForLock()) {
			$this->open();
			$this->unlock();
		}
	}
	
	abstract public function init();
	abstract public function open();
	abstract public function close();
	abstract public function size();
	abstract public function destroy();

	protected function getNewQueue() {
		$queue = array();
	}

//	public function push($obj, $priority=NULL);
//	public function pop();
	
	// Save the last batch of changes to the storage
	abstract protected function persist();
	
	// Check that we have the most up-to-date queue data
	abstract protected function refresh();
	
	// Get a lock on updates to the queue
	abstract protected function lock();
	
	// Unlock the queue for other processes
	abstract protected function unlock($force = false);
	
	// Wait for around until we get a lock
	abstract protected function waitForLock($timeout = false);

	// Check whether there is a lock present
	abstract protected function isLock();

	public function add($obj) {
		if ($this->lock()) { // Lock the queue while updating
			$this->refresh(); // Make sure we have the freshest queue data

			$this->queue[] = $obj;
			$this->pointer = null;

			$this->persist(); // persist the queue to storagee
			$this->unlock(); // Unlock the queue for others
		} else {
			// echo "WARN: Couldn't get a lock\n";
		}
	}
	
	public function update($obj) {
		if ($this->lock()) {
			$this->refresh();
			
			foreach ($this->queue as $k => $item) {
				if ($obj == $item) {
					$this->queue[$k] = $obj;
				}
			}
			
			$this->pointer = null;
			$this->updated = true;
			$this->persist();
			$this->unlock();
		}
	}	
	
	public function hasNext() {
		$this->refresh();
		if (!$this->pointer) {
		
			$next = 'no items left';
			$priority  = 0;
			foreach ($this->queue as $item) {
				if ($item->isCompleted()) continue;
				
				if ($item->getPriority() > $priority) {
					$next = $item;
					$priority = $item->getPriority();
				}
			}
			
			$this->pointer = $next;
		}
		
		return $next !== 'no items left';
	}

	public function next() {
		if ($this->hasNext()) {
			return $this->pointer;
		}
	}

	protected function hasUpdates() {
		return $this->updated;
	}
	
	
}


class SerialisedQueueStorage extends QueueStorage {
	protected $serFile    = '/home/user/data/queue/queue.ser';
	protected $lockFile;
	protected $lockTime   = 0;
	protected $staleLimit = 10;
	
	protected $lastUpdated;

	public function init() {
		//// echo "SerialisedQueueStorage->init()\n";
		
		// At this point we have a config object.
		if ($this->config) {
			if (is_string($this->config)) {
				$this->serFile = $this->config;
			} elseif (is_array($this->config)) {
				if (!empty($this->config['file'])) {
					$this->serFile = $this->config['file'];
				} else {
					$this->serFile = $this->config['datapath'] .
						$this->config['name'] . '.ser';
				}
			} elseif (is_object($this->config)) {
				$this->serFile = $this->config->file;
			}
		}
		
		if (!empty($this->serFile)) {
			// echo "INFO: Queue file: {$this->serFile}\n";
			// Create a lock file name
			$this->lockFile = $this->serFile . '.LOCK';
			//// echo "INFO: Lock file: {$this->lockFile}\n";
		}
	}

	public function open() {
		if (file_exists($this->serFile)) {
			$ser = file_get_contents($this->serFile);
			$this->lastUpdated = filectime($this->serFile);
			$obj = unserialize($ser);
			
			// TODO: Do some tighter checking here
			if (!empty($obj)) {
				$this->queue = $obj;
			}
		} else {
			$this->queue = array();
		}
	}
	
	public function close() {
		if ($this->hasUpdates()) {
			$ser = serialize($this->queue);
			file_put_contents($this->serFile, $ser);
		}
	}
	
	public function size() {
		return count($this->queue);
	}
	
	public function destroy() {
		if (file_exists($this->serFile)) {
			unlink($this->serFile);
		}
	}
	
	
	protected function persist() {
		//// echo "SerialisedQueueStorage->persist()\n";
		$ser = serialize($this->queue);
		file_put_contents($this->serFile, $ser);
		$this->lastUpdated = time(); //filectime($this->serFile);
		//// echo "LastUpdated: {$this->lastUpdated} (", time(), ")\n";
	}

	
	protected function refresh() {
		if (file_exists($this->serFile)) {
			//// echo "SerialisedQueueStorage->refresh()\n";
			$fileTouched = filectime($this->serFile);
			
			// TODO: get a lock on the file
			if ($fileTouched > $this->lastUpdated) {
				// TODO: reload file
				// echo "Reloading the queue from storage {$fileTouched}:{$this->lastUpdated}\n";
				$this->open();
			}
		}
	}

	protected function lock() {
		// Check whether we have a lock
		$lockExists = file_exists($this->lockFile);

		if ($lockExists) {
			// And if we do, check its freshness
			$lockTime = filectime($this->lockFile);
			$duration = time() - $lockTime;

			if ($duration > $this->staleLimit) {
				// Force an unlock if the lock is stale
				// echo "WARN: Forcing an unlock of a stale lock\n";
				$isUnlocked = $this->unlock(true);

				if ($isUnlocked) {
					$lockExists = false;
				} else {
					// echo "ERROR: Can't force an unlock of stale lock\n";
				}
			}
		}
		
		// When we don't have a lock
		if (!$lockExists) {
			// Create a lock file
			if (file_put_contents($this->lockFile, time()) !== false) {
				$this->lockTime = filectime($this->lockFile);
				return true;
			} else {
				// echo "WARN: Creating lock failed: {$this->lockFile}\n";
			}
		}		
		return false;	
	}
	
	protected function unlock($force = false) {
		// Check that we created the lock file in the first place
		$lockTime = filectime($this->lockFile);
		if ($lockTime == $this->lockTime) {
			return unlink($this->lockFile);
		} elseif ($force) {
			// echo "WARN: Forcing an unlock of the queue\n";
			$isUnlocked = unlink($this->lockFile);
			if ($isUnlocked) {
				$this->lockTime = 0;
			}
			return $isUnlocked;
		}
		return false;
	}

	/**
	 * Wait around then get a lock on the file
	 **/	
	protected function waitForLock($timeout = false) {
		if ($timeout===false) {
			$timeout = $this->staleLimit;
		}
		
		$startTime   = time();
		$haveLock    = false;
		$haveTimeout = false;
		
		while(!$haveLock && !$haveTimeout) {
			$isLocked = file_exists($this->lockFile);
			if ($isLocked) {
				$lockTime = filectime($this->lockFile);
				$duration = time() - $lockTime;
				if ($duration > $this->staleLimit) {
					// Force an unlock if the lock is stale
					// echo "WARN: Forcing an unlock after a wait timeout\n";
					$isLocked = !$this->unlock(true);
					if ($isLocked) {
						// echo "ERROR: Couldn't unlock the queue\n";
						//break;
					}
				}
			}

			if ($isLocked) {
				// See if we haven't waited long enough yet
				$waited = time() - $startTime;
				if ($waited > $timeout) {
					// echo "WARN: Waited for $timeout and still haven't got a lock\n";
					$haveTimeout = true;
				}
			} else {
				// Updating haveLock			
				$haveLock = $this->lock();
				if (!$haveLock) {
					// echo "INFO: We didn't get a lock\n";
					// wait before retrying
					sleep(1); // echo '.';
				}
			}
		}
		
		return $haveLock;
	}
	
	protected function isLock() {
		if (file_exists($this->lockFile)) {
			// TODO: keep track of whether we've done this before
			// Rather than rechecking the filectime
			
			// Check whether the lock is fresh
			$staleTime = time() - filectime($this->lockFile);
			if ($staleTime > $this->staleLimit) {
				// echo "WARN: File lock is stale. Forcing an unlocking\n";
				return !$this->unlock(true);
			}
			return true;
		}
		return false;
	}


}

class Queue {
	protected $name;
	protected $storage;
	protected $config;

/****	
	public function __construct($storage, $config=false) {
		$this->setStorage($storage);
		if ($config!==false) {
			$this->setConfig($config);
		}
	}
****/

	public function __construct($config) {
		$this->setStorage($config['type']);
		$this->setConfig($config);
	}

	
	public function setConfig($config) {
		$this->config = $config;
		$this->storage->setConfig($config);
	}	
	
	public function getStorage() {
		return $this->storage;
	}

	/**
	* Adds an object to the end of the queue
	**/
	public function add($obj, $priority=false) {
		if ($priority===false) {
			$priority = QueueItem::PRIORITY_MEDIUM;
		}
		
		$item = new QueueItem($obj, $priority);
		$this->storage->add($item);
	}
	
	public function update($obj) {
		return $this->storage->update($obj);
	}
	
	/**
	* Returns the first object on the queue (at the head)
	**/
	public function next() {
		return $this->storage->next();		
	}

	public function hasNext() {
		return $this->storage->hasNext();
	}
	
	public function size() {
		return $this->storage->size();
	}
	
	public function __destruct() {
		if (!$this->hasNext()) {
			$this->storage->destroy();
		}
	}
	
	protected function setStorage($storage) {
		$storeObj = $this->initStorage($storage);
		if ($storeObj) {
			$this->storage = $storeObj;
		}
	}
	
	protected function initStorage($name, $config=false) {
		if (class_exists($name)) {
			$tmpStore = new $name($config);
			if (is_a($tmpStore, 'QueueStorage')) {
				 return $tmpStore;
			} else {
				// echo "ERROR: $name does not subclass QueueStorage\n";
			}
		} else {
			// echo "ERROR: No queue storage class called $name found.\n";
		}
		return NULL;		
	}
}
