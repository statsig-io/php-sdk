<?php

namespace Statsig\Test;

use PHPUnit\Framework\TestCase;
use Statsig\Adapters\LocalFileDataAdapter;
use Statsig\IDList;
use Statsig\StatsigNetwork;

class IDListSyncingTest extends TestCase
{
    private StatsigNetwork $network;
    private LocalFileDataAdapter $adapter;

    private int $id_list_sync_count = 0;
    private int $list_1_download_count = 0;
    private int $list_2_download_count = 0;
    private int $list_3_download_count = 0;

    protected function setUp(): void
    {
        foreach (self::getIDListFiles() as $file) {
            unlink($file);
        }

        $this->id_list_sync_count = 0;
        $this->list_1_download_count = 0;
        $this->list_2_download_count = 0;
        $this->list_3_download_count = 0;

        $net = TestUtils::getMockNetwork(fn($a, $b, $c) => $this->onRequest($a, $b, $c));
        if ($net instanceof StatsigNetwork) {
            $this->network = $net;
        }
        $this->adapter = new LocalFileDataAdapter("/tmp/statsig_test/");
    }

    public function testSyncingOneTime()
    {
        $this->syncIDLists(1);

        $this->assertEquals(1, $this->id_list_sync_count);
        $this->assertEquals(1, $this->list_1_download_count);
        $this->assertEquals(1, $this->list_2_download_count);
        $this->assertEquals(0, $this->list_3_download_count);

        $this->assertCount(4, self::getIDListFiles(), "Should have 3 IDLists + 1 LastSyncTime file");

        $expected_list_1 = self::makeIDList("list_1", 3, 1, "file_id_1", ["1"]);
        $this->assertIDListEqual($expected_list_1, IDList::getIDListFromAdapter($this->adapter, "list_1"));

        $expected_list_2 = self::makeIDList("list_2", 3, 1, "file_id_2", ["a"]);
        $this->assertIDListEqual($expected_list_2, IDList::getIDListFromAdapter($this->adapter, "list_2"));
    }

    public function testSyncingTwoTimes()
    {
        $this->syncIDLists(2);

        $this->assertEquals(2, $this->id_list_sync_count);
        $this->assertEquals(2, $this->list_1_download_count);
        $this->assertEquals(1, $this->list_2_download_count);
        $this->assertEquals(0, $this->list_3_download_count);

        $this->assertCount(3, self::getIDListFiles(), "Should have 2 IDLists + 1 LastSyncTime File");

        $expected_list_1 = self::makeIDList("list_1", 12, 1, "file_id_1", ["2"]);
        $this->assertIDListEqual($expected_list_1, IDList::getIDListFromAdapter($this->adapter, "list_1"));
    }

    public function testSyncingThreeTimes()
    {
        $this->syncIDLists(3);

        $this->assertEquals(3, $this->id_list_sync_count);
        $this->assertEquals(3, $this->list_1_download_count);
        $this->assertEquals(1, $this->list_2_download_count);
        $this->assertEquals(0, $this->list_3_download_count);

        $this->assertCount(3, self::getIDListFiles(), "Should have 2 IDLists + 1 LastSyncTime File");

        $expected_list_1 = self::makeIDList("list_1", 3, 3, "file_id_1_a", ["3"]);
        $this->assertIDListEqual($expected_list_1, IDList::getIDListFromAdapter($this->adapter, "list_1"));
    }

    public function testSyncingFourTimes()
    {
        $this->syncIDLists(4);

        $this->assertEquals(4, $this->id_list_sync_count);
        $this->assertEquals(3, $this->list_1_download_count);
        $this->assertEquals(1, $this->list_2_download_count);
        $this->assertEquals(0, $this->list_3_download_count);

        $this->assertCount(3, self::getIDListFiles(), "Should have 2 IDLists + 1 LastSyncTime File");

        $expected_list_1 = self::makeIDList("list_1", 3, 3, "file_id_1_a", ["3"]);
        $this->assertIDListEqual($expected_list_1, IDList::getIDListFromAdapter($this->adapter, "list_1"));
    }

    public function testSyncingFiveTimes()
    {
        $this->syncIDLists(5);

        $this->assertEquals(5, $this->id_list_sync_count);
        $this->assertEquals(4, $this->list_1_download_count);
        $this->assertEquals(1, $this->list_2_download_count);
        $this->assertEquals(1, $this->list_3_download_count);

        $this->assertCount(4, self::getIDListFiles(), "Should have 3 IDLists + 1 LastSyncTime File");

        $expected_list_1 = self::makeIDList("list_1", 3, 3, "file_id_1_a", ["3"]);
        $this->assertIDListEqual($expected_list_1, IDList::getIDListFromAdapter($this->adapter, "list_1"));

        $expected_list_3 = self::makeIDList("list_3", 3, 5, "file_id_3", ["0"]);
        $this->assertIDListEqual($expected_list_3, IDList::getIDListFromAdapter($this->adapter, "list_3"));
    }

    public function testSyncingSixTimes()
    {
        $this->syncIDLists(6);

        $this->assertEquals(6, $this->id_list_sync_count);
        $this->assertEquals(5, $this->list_1_download_count);
        $this->assertEquals(1, $this->list_2_download_count);
        $this->assertEquals(1, $this->list_3_download_count);

        $this->assertCount(4, self::getIDListFiles(), "Should have 3 IDLists + 1 LastSyncTime File");

        $expected_list_1 = self::makeIDList("list_1", 21, 3, "file_id_1_a", ["3", "5", "6"]);
        $this->assertIDListEqual($expected_list_1, IDList::getIDListFromAdapter($this->adapter, "list_1"));

        $expected_list_3 = self::makeIDList("list_3", 3, 5, "file_id_3", ["0"]);
        $this->assertIDListEqual($expected_list_3, IDList::getIDListFromAdapter($this->adapter, "list_3"));
    }

    function assertIDListEqual(?IDList $expected, ?IDList $actual)
    {
        if ($expected == null) {
            $this->assertNull($actual);
            return;
        }

        $this->assertEquals($expected->name, $actual->name);
        $this->assertEquals($expected->read_bytes, $actual->read_bytes);
        $this->assertEquals($expected->creation_time, $actual->creation_time);
        $this->assertEquals($expected->url, $actual->url);
        $this->assertEquals($expected->file_id, $actual->file_id);

        $this->assertEqualsCanonicalizing(array_keys($expected->ids), array_keys($actual->ids));
    }

    private static function getIDListFiles(): array
    {
        return glob("/tmp/statsig_test/*");
    }

    private static function makeListInfo(string $name, int $size, int $creation_time, string $file_id): array
    {
        return [
            "name" => $name,
            "size" => $size,
            "url" => "https://id-list-cdn.com/$name",
            "creationTime" => $creation_time,
            "fileID" => $file_id
        ];
    }

    private static function makeIDList(string $name, int $read_bytes, int $creation_time, string $file_id, array $ids): IDList
    {
        $list = new IDList(self::makeListInfo($name, 0, $creation_time, $file_id), array_reduce($ids, function ($acc, $curr) {
            $acc[$curr] = true;
            return $acc;
        }, []));

        $list->read_bytes = $read_bytes;

        return $list;
    }

    private static function endsWith($haystack, $needle): bool
    {
        return substr_compare($haystack, $needle, -strlen($needle)) === 0;
    }

    private function syncIDLists(int $times) {
        for ($i = 0; $i < $times; $i++) {
            IDList::sync($this->adapter, $this->network);
        }
    }

    private function onRequest(string $method, string $endpoint, $input): ?array
    {
        if ($method == "POST" && self::endsWith($endpoint, "get_id_lists")) {
            $this->id_list_sync_count += 1;

            switch ($this->id_list_sync_count) {
                case 1:
                    return [
                        "list_1" => self::makeListInfo("list_1", 3, 1, "file_id_1"),
                        "list_2" => self::makeListInfo("list_2", 3, 1, "file_id_2")
                    ];
                case 2:
                case 4:
                    return [
                        "list_1" => self::makeListInfo("list_1", 9, 1, "file_id_1"),
                    ];
                case 3:
                    return [
                        "list_1" => self::makeListInfo("list_1", 3, 3, "file_id_1_a"),
                    ];
                default:
                    return [
                        "list_1" => self::makeListInfo("list_1", 18, 3, "file_id_1_a"),
                        "list_3" => self::makeListInfo("list_3", 3, 5, "file_id_3"),
                    ];
            }
        }

        if ($method == "GET") {
            if (self::endsWith($endpoint, "list_1")) {
                $this->list_1_download_count += 1;

                $list_1_mocks = [
                    1 => "+1\n",
                    2 => "+1\n-1\n+2\n",
                    3 => "+3\n",
                    4 => "+1\n-1\n+2\n",
                    5 => "3",
                ];

                $list_1_mock = $list_1_mocks[$this->id_list_sync_count] ?? "+3\n+4\n+5\n+4\n-4\n+6\n";
                return ["headers" => ["content-length" => ["" . strlen($list_1_mock)]], "data" => $list_1_mock];
            }

            if (self::endsWith($endpoint, "list_2")) {
                $this->list_2_download_count += 1;

                return ["headers" => ["content-length" => ["3"]], "data" => "+a\n"];
            }

            if (self::endsWith($endpoint, "list_3")) {
                $this->list_3_download_count += 1;

                return ["headers" => ["content-length" => ["3"]], "data" => "+0\n"];
            }
        }

        return null;
    }
}
