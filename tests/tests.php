<?php

require_once __DIR__ . "/testframework.php";

function requireProjectFile($relativePathInContainer, $relativePathInRepo)
{
    $containerPath = __DIR__ . "/" . $relativePathInContainer;
    if (is_file($containerPath)) {
        require_once $containerPath;
        return;
    }

    $repoPath = __DIR__ . "/" . $relativePathInRepo;
    require_once $repoPath;
}

requireProjectFile("../config.php", "../site/config.php");
requireProjectFile("../modules/database.php", "../site/modules/database.php");
requireProjectFile("../modules/page.php", "../site/modules/page.php");

function makeTempDbPath(): string
{
    $dir = sys_get_temp_dir();
    $path = tempnam($dir, "containers08_db_");
    if ($path === false) {
        throw new RuntimeException("Failed to create temp db path");
    }
    return $path;
}

function initSchema(Database $db): void
{
    $db->Execute(
        "CREATE TABLE page (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, content TEXT)",
    );
    $db->Execute(
        "INSERT INTO page (title, content) VALUES ('Page 1', 'Content 1')",
    );
    $db->Execute(
        "INSERT INTO page (title, content) VALUES ('Page 2', 'Content 2')",
    );
    $db->Execute(
        "INSERT INTO page (title, content) VALUES ('Page 3', 'Content 3')",
    );
}

function withFreshDb(callable $fn): bool
{
    $path = makeTempDbPath();
    try {
        $db = new Database($path);
        initSchema($db);
        return (bool) $fn($db);
    } catch (Throwable $e) {
        error($e->getMessage());
        return false;
    } finally {
        @unlink($path);
    }
}

$tests = new TestFramework();

function testDbConnection(): bool
{
    $path = makeTempDbPath();
    try {
        new Database($path);
        return assertExpression(
            true,
            "Database connection ok",
            "Database connection failed",
        );
    } catch (Throwable $e) {
        return assertExpression(
            false,
            "Database connection ok",
            "Database connection failed: " . $e->getMessage(),
        );
    } finally {
        @unlink($path);
    }
}

function testDbExecute(): bool
{
    return withFreshDb(function (Database $db) {
        $before = $db->Count("page");
        $db->Execute(
            "INSERT INTO page (title, content) VALUES ('Page X', 'Content X')",
        );
        $after = $db->Count("page");
        return assertExpression(
            $after === $before + 1,
            "Execute inserted row",
            "Execute did not insert row",
        );
    });
}

function testDbFetch(): bool
{
    return withFreshDb(function (Database $db) {
        $rows = $db->Fetch("SELECT title, content FROM page WHERE id = 1");
        $ok =
            is_array($rows) &&
            count($rows) === 1 &&
            ($rows[0]["title"] ?? null) === "Page 1" &&
            ($rows[0]["content"] ?? null) === "Content 1";
        return assertExpression(
            $ok,
            "Fetch returns expected row",
            "Fetch returned unexpected result",
        );
    });
}

function testDbCount(): bool
{
    return withFreshDb(function (Database $db) {
        return assertExpression(
            $db->Count("page") === 3,
            "Count returns 3",
            "Count returned wrong value",
        );
    });
}

function testDbCreate(): bool
{
    return withFreshDb(function (Database $db) {
        $before = $db->Count("page");
        $id = $db->Create("page", [
            "title" => "New page",
            "content" => "New content",
        ]);
        $after = $db->Count("page");
        $ok = $id > 0 && $after === $before + 1;
        return assertExpression(
            $ok,
            "Create returns id and inserts row",
            "Create failed",
        );
    });
}

function testDbRead(): bool
{
    return withFreshDb(function (Database $db) {
        $row = $db->Read("page", 2);
        $ok =
            ($row["id"] ?? null) == 2 &&
            ($row["title"] ?? null) === "Page 2" &&
            ($row["content"] ?? null) === "Content 2";
        return assertExpression(
            $ok,
            "Read returns expected row",
            "Read returned unexpected data",
        );
    });
}

function testDbUpdate(): bool
{
    return withFreshDb(function (Database $db) {
        $db->Update("page", 1, [
            "title" => "Updated",
            "content" => "Updated content",
        ]);
        $row = $db->Read("page", 1);
        $ok =
            ($row["title"] ?? null) === "Updated" &&
            ($row["content"] ?? null) === "Updated content";
        return assertExpression(
            $ok,
            "Update modified row",
            "Update did not modify row",
        );
    });
}

function testDbDelete(): bool
{
    return withFreshDb(function (Database $db) {
        $before = $db->Count("page");
        $db->Delete("page", 1);
        $after = $db->Count("page");
        $row = $db->Read("page", 1);
        $ok = $after === $before - 1 && $row === [];
        return assertExpression($ok, "Delete removed row", "Delete failed");
    });
}

function testPageRender(): bool
{
    $templatePath = tempnam(sys_get_temp_dir(), "containers08_tpl_");
    if ($templatePath === false) {
        return assertExpression(
            false,
            "Template created",
            "Failed to create template",
        );
    }

    try {
        file_put_contents(
            $templatePath,
            "<h1>{{title}}</h1><div>{{content}}</div>",
        );
        $page = new Page($templatePath);
        $html = $page->Render([
            "title" => "Hello <b>world</b>",
            "content" => "A & B",
        ]);
        $ok =
            strpos($html, "Hello &lt;b&gt;world&lt;/b&gt;") !== false &&
            strpos($html, "A &amp; B") !== false;
        return assertExpression(
            $ok,
            "Render substitutes and escapes",
            "Render did not substitute/escape",
        );
    } catch (Throwable $e) {
        return assertExpression(
            false,
            "Page ok",
            "Page failed: " . $e->getMessage(),
        );
    } finally {
        @unlink($templatePath);
    }
}

function testPageMissingTemplate(): bool
{
    try {
        new Page(__DIR__ . "/does-not-exist.tpl");
        return assertExpression(
            false,
            "Exception thrown",
            "Expected exception for missing template",
        );
    } catch (Throwable $e) {
        return assertExpression(
            true,
            "Missing template throws",
            "Missing template did not throw",
        );
    }
}

$tests->add("Database connection", "testDbConnection");
$tests->add("Execute", "testDbExecute");
$tests->add("Fetch", "testDbFetch");
$tests->add("Count", "testDbCount");
$tests->add("Create", "testDbCreate");
$tests->add("Read", "testDbRead");
$tests->add("Update", "testDbUpdate");
$tests->add("Delete", "testDbDelete");
$tests->add("Page render", "testPageRender");
$tests->add("Page missing template", "testPageMissingTemplate");

$tests->run();
echo $tests->getResult() . PHP_EOL;
