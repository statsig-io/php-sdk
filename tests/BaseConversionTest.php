<?php

namespace Statsig\Test;

use PHPUnit\Framework\TestCase;
use Statsig\Evaluator;
use Statsig\StatsigOptions;

class BaseConversionTest extends TestCase {

    private $evaluator;

    public function setUp() {
        $this->evaluator = new Evaluator(new StatsigOptions(__DIR__."/../tests/testdata.config", __DIR__."/../tests/testdata.log"));
    }

    public function testKeyNotFound() {
        // Some basic examples
        $this->assertEquals("256", $this->evaluator->base_convert_to_string("100", 16, 10));
        $this->assertEquals("4096", $this->evaluator->base_convert_to_string("1000", 16, 10));

        // PHP_INT_MAX and one above
        $this->assertEquals(PHP_INT_MAX, $this->evaluator->base_convert_to_string(base_convert(PHP_INT_MAX, 10, 16), 16, 10));
        $this->assertEquals("9223372036854775808", $this->evaluator->base_convert_to_string("8000000000000000", 16, 10));

        // base_convert totally breaks down here
        $this->assertEquals("18446744073709551615", $this->evaluator->base_convert_to_string("ffffffffffffffff", 16, 10));
        $this->assertEquals("18446744073709551616", $this->evaluator->base_convert_to_string("10000000000000000", 16, 10));

    }
}