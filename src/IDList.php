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

    public function __construct(array $json, array $ids)
    {
        $this->name = $json["name"] ?? "";
        $this->read_bytes = $json["readBytes"] ?? 0;
        $this->creation_time = $json["creationTime"] ?? 0;
        $this->url = $json["url"] ?? null;
        $this->file_id = $json["fileID"] ?? null;
        $this->ids = $ids;
    }

    static function sync(IDataAdapter $adapter, StatsigNetwork $network)
    {
        $id_lists_lookup = $network->postRequest("get_id_lists", json_encode(['statsigMetadata' => StatsigMetadata::getJson()]));
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

            $headers = $res["headers"];
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

        IDList::updateActiveIDLists($adapter, array_keys($id_lists_lookup));
    }

    static function getIDListFromAdapter(IDataAdapter $adapter, string $name): ?IDList
    {
        $json = @json_decode($adapter->get(self::ID_LIST_KEY . "::$name"), true);
        if ($json === null) {
            return null;
        }

        return new IDList($json["info"], $json["ids"]);
    }

    static function saveToAdapter(IDataAdapter $adapter, string $name, IDList $list)
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

    static function updateActiveIDLists(IDataAdapter $adapter, array $id_list_names)
    {
        $old_lists = json_decode($adapter->get(self::ID_LIST_KEY) ?? "[]", true);

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
    }

    private static function safe_get($array, $key, $fallback)
    {
        return array_key_exists($key, $array) ? $array[$key] : $fallback;
    }
}

