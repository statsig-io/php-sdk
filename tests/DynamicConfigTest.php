<?php

namespace Statsig\Test;

use PHPUnit\Framework\TestCase;
use Statsig\DynamicConfig;

class DynamicConfigTest extends TestCase
{
    private DynamicConfig $config;

    protected function setUp(): void
    {
        $this->config = new DynamicConfig("test_config", [
            "bool" => true,
            "number" => 2,
            "string" => "string",
            "object" => [
                "key" => "value",
                "key2" => 123,
            ],
            "boolStr1" => "true",
            "boolStr2" => "FALSE",
            "numberStr1" => "3",
            "numberStr2" => "3.3",
            "numberStr3" => "3.3.3",
            "array" => [1, 2, "three"],
        ], "default");
    }

    public function testKeyNotFound()
    {
        $this->assertEquals(null, $this->config->get("key_not_found", null));
        $this->assertEquals(true, $this->config->get("key_not_found", true));
        $this->assertEquals(12, $this->config->get("key_not_found", 12));
        $this->assertEquals("a string", $this->config->get("key_not_found", "a string"));
        $this->assertEquals(["test", "onetwo"], $this->config->get("key_not_found", ["test", "onetwo"]));
        $this->assertEquals(["test" => 123], $this->config->get("key_not_found", ["test" => 123],));
    }

    public function testHelpers()
    {
        $this->assertEquals([
            "bool" => true,
            "number" => 2,
            "string" => "string",
            "object" => (object) [
                "key" => "value",
                "key2" => 123,
            ],
            "boolStr1" => "true",
            "boolStr2" => "FALSE",
            "numberStr1" => "3",
            "numberStr2" => "3.3",
            "numberStr3" => "3.3.3",
            "array" => [1, 2, "three"],
        ], $this->config->getValue());

        $this->assertEquals("default", $this->config->getRuleID());
    }

    public function testMatchingTypes()
    {
        $this->assertEquals("true", $this->config->get("boolStr1", "unused"));
        $this->assertEquals("true", $this->config->get("boolStr1", null));

        $this->assertEquals(2, $this->config->get("number", 123));
        $this->assertEquals(2, $this->config->get("number", null));

        $this->assertEquals(true, $this->config->get("bool", false));
        $this->assertEquals(true, $this->config->get("bool", null));

        $this->assertEquals((object) [
            "key" => "value",
            "key2" => 123,
        ], $this->config->get("object", (object) []));
        $this->assertEquals((object) [
            "key" => "value",
            "key2" => 123,
        ], $this->config->get("object", null));

        $this->assertEquals([1, 2, "three"], $this->config->get("array", []));
        $this->assertEquals([1, 2, "three"], $this->config->get("array", null));
    }

    public function testMismatch()
    {
        $this->assertEquals(123, $this->config->get("boolStr1", 123));
        $this->assertEquals("str", $this->config->get("number", "str"));
        $this->assertEquals("a string", $this->config->get("bool", "a string"));
        $this->assertEquals("another string", $this->config->get("object", "another string"));
        $this->assertEquals(["another string"], $this->config->get("number", ["another string"]));
        $this->assertEquals(["key" => "val"], $this->config->get("bool", ["key" => "val"]));
    }
}
