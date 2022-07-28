<?php

namespace Statsig\Test;

use Statsig\StatsigUser;
use Throwable;
use PHPUnit\Framework\TestCase;

class StatsigUserTest extends TestCase
{
    public function testCanCreateValidUser() {
        self::assertNotNull(StatsigUser::withUserID("a_user"));
        self::assertNotNull(StatsigUser::withCustomIDs(["WorkID" => "an_employee"]));
    }

    public function testThrowsWhenNullUserIdIsGiven()
    {
        $this->assertActionThrows(fn() => StatsigUser::withUserID(null));
    }

    public function testThrowsWhenEmptyUserIdIsGiven()
    {
        $this->assertActionThrows(fn() => StatsigUser::withUserID(""));
    }

    public function testThrowsWhenNullCustomIDsIsGiven()
    {
        $this->assertActionThrows(fn() => (StatsigUser::withCustomIDs(null)));
    }

    public function testThrowsWhenEmptyCustomIDsIsGiven()
    {
        $this->assertActionThrows(fn() => StatsigUser::withCustomIDs([]));
    }

    private function assertActionThrows($action)
    {
        try {
            $action();
        } catch (Throwable $exception) {
            self::assertTrue(true);
            return;
        }

        self::fail("Did not throw expected exception");
    }
}
