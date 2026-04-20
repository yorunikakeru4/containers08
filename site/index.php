<?php

require_once __DIR__ . "/modules/database.php";
require_once __DIR__ . "/modules/page.php";

require_once __DIR__ . "/config.php";

$db = new Database($config["db"]["path"]);
$page = new Page(__DIR__ . "/templates/index.tpl");

$pageId = isset($_GET["page"]) ? (int) $_GET["page"] : 1;
if ($pageId <= 0) {
    $pageId = 1;
}

$data = $db->Read("page", $pageId);
if ($data === []) {
    $data = [
        "title" => "Not found",
        "content" => "Page not found",
    ];
}

echo $page->Render($data);
