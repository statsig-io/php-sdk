<?php

namespace Statsig;

use Statsig\Adapters\IDataAdapter;

class IDList
{
    public string $name;
    public int $read_bytes;
    public int $creation_time;
    public ?string $url;
    public ?string $file_id;
    public array $ids;

    private const ID_LIST_KEY = "statsig.id_lists";
    private const ID_LIST_LAST_SYNC_TIME_KEY = self::ID_LIST_KEY . "::__php_server_last_sync_time__";

    public function __construct(array $json, array $ids)
    {
        $this->name = $json["name"] ?? "";
        $this->read_bytes = $json["readBytes"] ?? 0;
        $this->creation_time = $json["creationTime"] ?? 0;
        $this->url = $json["url"] ?? null;
        $this->file_id = $json["fileID"] ?? null;
        $this->ids = $ids;
    }

    static function sync(IDataAdapter $adapter, StatsigNetwork $network): void
    {
        $id_lists_lookup = $network->postRequest("get_id_lists", json_encode(['statsigMetadata' => StatsigMetadata::getJson()]))["body"];
        if ($id_lists_lookup === null) {
            return;
        }

        $requests = [];
        $lists = [];

        foreach ($id_lists_lookup as $list_name => $list_info) {

            $list = IDList::getIDListFromAdapter($adapter, $list_name) ?? new IDList($list_info, []);
            $url = $list_info["url"] ?? null;

            $new_file_id = $list_info["fileID"] ?? null;
            $new_creation_time = $list_info["creationTime"] ?? 0;

            if ($url == null || $url == "" || $new_creation_time < $list->creation_time || $new_file_id == null) {
                continue;
            }

            if ($new_file_id != $list->file_id) {
                $list = new IDList($list_info, []);
            }

            $size = $list_info["size"] ?? 0;
            if ($size <= $list->read_bytes) {
                continue;
            }

            $lists[$list_name] = $list;
            $requests[$list_name] = [
                "headers" => [
                    "Range" => "bytes=$list->read_bytes-"
                ],
                "url" => $list_info["url"]
            ];
        }

        $responses = $network->multiGetRequest($requests);
        
        foreach ($responses as $list_name => $res) {
            $list = $lists[$list_name];

            $headers = array_change_key_case($res["headers"], CASE_LOWER);
            $content_len = intval($headers["content-length"][0]);
            if ($content_len <= 0) {
                continue;
            }

            $content = $res["data"];
            if (!is_string($content) || ($content[0] !== "-" && $content[0] !== "+")) {
                unset($responses[$list_name]);
                continue;
            }

            $lines = explode("\n", $content);
            foreach ($lines as $line) {
                $stripped = trim($line);
                if (strlen($stripped) <= 1) {
                    continue;
                }

                $op = $line[0];
                $id = substr($stripped, 1);
                if ($op == "+") {
                    $list->ids[$id] = true;
                } else {
                    unset($list->ids[$id]);
                }
            }

            $list->read_bytes += $content_len;
            IDList::saveToAdapter($adapter, $list_name, $list);
        }

        self::updateActiveIDLists($adapter, array_keys($id_lists_lookup));
    }

    static function getIDListFromAdapter(IDataAdapter $adapter, string $name): ?IDList
    {
        $json = @json_decode($adapter->get(self::ID_LIST_KEY . "::$name"), true, 512, JSON_BIGINT_AS_STRING);
        if ($json === null) {
            return null;
        }

        return new IDList($json["info"], $json["ids"]);
    }

    static function getLastIDListSyncTimeFromAdapter(IDataAdapter $adapter): int  {
        return (int) $adapter->get(self::ID_LIST_LAST_SYNC_TIME_KEY);
    }

    static function saveToAdapter(IDataAdapter $adapter, string $name, IDList $list): void
    {
        $adapter->set(self::ID_LIST_KEY . "::$name", json_encode([
            "info" => [
                "name" => $list->name,
                "readBytes" => $list->read_bytes,
                "creationTime" => $list->creation_time,
                "url" => $list->url,
                "fileID" => $list->file_id,
            ],
            "ids" => $list->ids
        ]));
    }

    private static function updateActiveIDLists(IDataAdapter $adapter, array $id_list_names): void
    {
        $old_lists = json_decode($adapter->get(self::ID_LIST_KEY) ?? "[]", true, 512, JSON_BIGINT_AS_STRING);

        $id_list_paths = [];
        foreach ($id_list_names as $name) {
            $id_list_paths[$name] = true;
        }

        foreach ($old_lists as $name => $value) {
            if (!array_key_exists($name, $id_list_paths)) {
                $adapter->set(self::ID_LIST_KEY . "::$name", null);
            }
        }

        $adapter->set(self::ID_LIST_KEY, json_encode($id_list_paths));
        $adapter->set(self::ID_LIST_LAST_SYNC_TIME_KEY, floor(microtime(true) * 1000));
    }
}

