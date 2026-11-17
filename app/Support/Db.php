<?php
declare(strict_types=1);

namespace Pena\Support;

use mysqli;
use Throwable;

/**
 * Thin positional-parameter helper over mysqli for PENA services.
 * Every value goes through a native prepared statement; SQL text never contains input.
 * The connection must already have strict sql_mode (Database::applyStrictMode).
 */
final class Db
{
    private int $depth = 0;

    public function __construct(private readonly mysqli $conn) {}

    public static function fromTemplate(\Database $database): self
    {
        return new self($database->getConnection());
    }

    public function one(string $sql, array $params = []): ?array
    {
        $result = $this->exec($sql, $params)->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        return $row ?: null;
    }

    public function all(string $sql, array $params = []): array
    {
        $result = $this->exec($sql, $params)->get_result();
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function value(string $sql, array $params = []): mixed
    {
        $row = $this->one($sql, $params);
        return $row === null ? null : reset($row);
    }

    /** @return int affected rows */
    public function run(string $sql, array $params = []): int
    {
        return (int)$this->exec($sql, $params)->affected_rows;
    }

    public function insert(string $sql, array $params = []): int
    {
        $this->exec($sql, $params);
        return (int)$this->conn->insert_id;
    }

    /**
     * Runs $work atomically. Nested calls join the outer transaction,
     * so services can call each other without committing half a workflow.
     */
    public function transaction(callable $work): mixed
    {
        if ($this->depth > 0) {
            $this->depth++;
            try { return $work($this); } finally { $this->depth--; }
        }
        $this->conn->begin_transaction();
        $this->depth = 1;
        try {
            $result = $work($this);
            $this->conn->commit();
            return $result;
        } catch (Throwable $error) {
            $this->conn->rollback();
            throw $error;
        } finally {
            $this->depth = 0;
        }
    }

    private function exec(string $sql, array $params): \mysqli_stmt
    {
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(array_map(
            static fn($v) => is_bool($v) ? (int)$v : $v,
            array_values($params)
        ));
        return $stmt;
    }
}
