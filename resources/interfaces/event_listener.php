<?php

namespace fusor\resources\interfaces;

use fusor\resources\attributes\on;
use fusor\resources\classes\fusor_event;

interface switch_listener {
	#[on(event_name: 'call.*', priority: 0)]
	public function handle(fusor_event $fusor_event): void;
}
