<?php
class Database
{
    // private string $path;
    private PDO $pdo;
    public function __construct(string $dsn, string $username, string $password)
    {
        $this->pdo = new PDO($dsn, $username, $password);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    public function Execute(string $query): void
    {
        $this->pdo->prepare($query)->execute();
    }

    public function Fetch(string $query): array
    {
        $stmt = $this->pdo->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function Create(string $table, array $data): int
    {
        $columns = implode(", ", array_keys($data));
        $placeholders = implode(", ", array_fill(0, count($data), "?"));
        $stmt = $this->pdo->prepare(
            "INSERT INTO $table ($columns) VALUES ($placeholders)",
        );
        $stmt->execute(array_values($data));
        return (int) $this->pdo->lastInsertId();
    }
    public function Read(string $table, int $id): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM $table WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? [] : $row;
    }
    public function Update(string $table, int $id, array $data): void
    {
        $set = implode(
            ", ",
            array_map(fn($col) => "$col = ?", array_keys($data)),
        );
        $this->pdo
            ->prepare("UPDATE $table SET $set WHERE id = ?")
            ->execute([...array_values($data), $id]);
    }
    public function Delete(string $table, int $id)
    {
        $this->pdo->prepare("DELETE FROM $table WHERE id = ?")->execute([$id]);
    }
    public function Count(string $table): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM $table");
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }
}
