<?php

namespace frytimo\fusor\resources\attributes;

use Attribute;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_METHOD)]
class put extends route {

}
