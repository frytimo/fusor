<?php

require_once dirname(__DIR__) . '/classes/fusor_discovery.php';

if (isset($autoload) && $autoload instanceof \auto_loader) {
	\frytimo\fusor\resources\classes\fusor_discovery::discover_attributes($autoload);
}
