<?php

$config = [
    "db" => [
        "host" => getenv("MYSQL_HOST") ?: "localhost",
        "database" => getenv("MYSQL_DATABASE") ?: "my_database",
        "username" => trim(file_get_contents("/run/secrets/user")),
        "password" => trim(file_get_contents("/run/secrets/secret")),
    ],
];
