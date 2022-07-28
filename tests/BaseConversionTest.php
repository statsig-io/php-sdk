<?php

namespace Statsig\Test;

use PHPUnit\Framework\TestCase;
use Statsig\EvaluationUtils;

class BaseConversionTest extends TestCase
{
    public function testKeyNotFound()
    {
        // Some basic examples
        $this->assertEquals("256", EvaluationUtils::baseConvertToString("100", 16, 10));
        $this->assertEquals("4096", EvaluationUtils::baseConvertToString("1000", 16, 10));

        // PHP_INT_MAX and one above
        $this->assertEquals(PHP_INT_MAX, EvaluationUtils::baseConvertToString(base_convert(PHP_INT_MAX, 10, 16), 16, 10));
        $this->assertEquals("9223372036854775808", EvaluationUtils::baseConvertToString("8000000000000000", 16, 10));

        // base_convert totally breaks down here
        $this->assertEquals("18446744073709551615", EvaluationUtils::baseConvertToString("ffffffffffffffff", 16, 10));
        $this->assertEquals("18446744073709551616", EvaluationUtils::baseConvertToString("10000000000000000", 16, 10));
    }
}
