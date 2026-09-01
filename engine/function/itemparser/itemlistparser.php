<?php

function getItemList(): array {
    return parseItems();
}

function getItemById(int $id): string|false {
    static $items = null;

    if ($items === null) {
        $items = parseItems();
    }

    return $items[$id] ?? false;
}

function parseItems(): array {
    // ZnoteAAC compatible
    $serverPath = function_exists('Config')
        ? Config('server_path')
        : ($GLOBALS['config']['server_path'] ?? null);

    if (!$serverPath) {
        return [];
    }

    $file = $serverPath . '/data/items/items.xml';

    if (!file_exists($file)) {
        return [];
    }

    libxml_use_internal_errors(true);
    $xml = simplexml_load_file($file);

    if ($xml === false) {
        return [];
    }

    $itemList = [];

    foreach ($xml->children() as $item) {
        if (isset($item['id'], $item['name'])) {
            $itemList[(int)$item['id']] = (string)$item['name'];
        }
    }

    return $itemList;
}