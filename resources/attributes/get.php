<?php

namespace frytimo\fusor\resources\attributes;

use Attribute;

/**
 * Description of Get
 *
 * @author Tim Fry <tim.fry@hotmail.com>
 */
#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_METHOD)]
/**
 * Get.
 */
class get extends route {

}

