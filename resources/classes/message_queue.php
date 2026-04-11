<?php

namespace frytimo\fusor\resources\classes;

use Fuz\Component\SharedMemory\SharedMemory;
use Fuz\Component\SharedMemory\Storage\StorageInterface;

class message_queue extends SharedMemory {
		public function __construct(StorageInterface $storage) {
			parent::__construct($storage);
			parent::__set('list', []);
		}

		public function queue(): array {
			return parent::__get('list');
		}

		/**
		 * Enqueue a message to print to console.
		 * @param string $id group id for message
		 * @param string $message message to print to console
		 */
		public function enqueue(string $id, string $message) {
			$this->lock();
			$arr = parent::__get('list');
			$arr[$id][] = $message;
			parent::__set('list',$arr);
			$this->unlock();
		}

		/**
		 * Remove a group of messages from the queue.
		 * @param array|string $id Group of messages to remove
		 */
		public function remove($id) {
			// mutex lock
			$this->lock();
			$arr = parent::__get('list');
			$arr[$id] = null;
			parent::__set('list',$arr);
			// mutex unlock
			$this->unlock();
		}

		/**
		 * Returns if the queue is empty or not.
		 * @return bool True if no more messages
		 */
		public function empty(): bool {
			return empty(parent::__get('list'));
		}

}