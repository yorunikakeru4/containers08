<?php

$defaultDbPath = __DIR__ . "/../db/db.sqlite";
$dbPath = getenv("DB_PATH");
if ($dbPath === false || $dbPath === "") {
    $dbPath = $defaultDbPath;
}

$config = [
    "db" => [
        "path" => $dbPath,
    ],
];
