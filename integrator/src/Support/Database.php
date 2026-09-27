<?php

declare(strict_types=1);

namespace Integrator\Support;

use PDO;
use PDOException;
use RuntimeException;

/**
 * SQLite access layer with a built-in schema bootstrap.
 *
 * The integrator keeps its own state (settings, id mappings, sync logs) so it never
 * needs write access to SIAKAD — SIAKAD stays the system of record, this database is
 * a working ledger that can be recreated at any time.
 */
final class Database
{
    private ?PDO $pdo = null;

    public function __construct(private readonly string $path)
    {
    }

    public function pdo(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        $directory = dirname($this->path);

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        try {
            $pdo = new PDO('sqlite:'.$this->path, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $exception) {
            throw new RuntimeException('Tidak dapat membuka database SQLite: '.$exception->getMessage(), 0, $exception);
        }

        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = 5000');

        return $this->pdo = $pdo;
    }

    /**
     * Create every table this application needs. Safe to run repeatedly.
     */
    public function migrate(): array
    {
        $statements = [
            'users' => <<<'SQL'
                CREATE TABLE IF NOT EXISTS users (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL,
                    email TEXT NOT NULL UNIQUE,
                    password_hash TEXT NOT NULL,
                    last_login_at TEXT,
                    created_at TEXT NOT NULL,
                    updated_at TEXT NOT NULL
                )
            SQL,
            'settings' => <<<'SQL'
                CREATE TABLE IF NOT EXISTS settings (
                    key TEXT PRIMARY KEY,
                    value TEXT,
                    is_secret INTEGER NOT NULL DEFAULT 0,
                    updated_at TEXT NOT NULL
                )
            SQL,
            'mappings' => <<<'SQL'
                CREATE TABLE IF NOT EXISTS mappings (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    entity TEXT NOT NULL,
                    local_key TEXT NOT NULL,
                    feeder_id TEXT,
                    payload_hash TEXT,
                    first_synced_at TEXT NOT NULL,
                    last_synced_at TEXT NOT NULL,
                    UNIQUE (entity, local_key)
                )
            SQL,
            'sync_runs' => <<<'SQL'
                CREATE TABLE IF NOT EXISTS sync_runs (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    run_id TEXT NOT NULL UNIQUE,
                    entity TEXT NOT NULL,
                    mode TEXT NOT NULL,
                    semester_code TEXT,
                    started_at TEXT NOT NULL,
                    finished_at TEXT,
                    total INTEGER NOT NULL DEFAULT 0,
                    succeeded INTEGER NOT NULL DEFAULT 0,
                    failed INTEGER NOT NULL DEFAULT 0,
                    skipped INTEGER NOT NULL DEFAULT 0,
                    notes TEXT
                )
            SQL,
            'sync_logs' => <<<'SQL'
                CREATE TABLE IF NOT EXISTS sync_logs (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    run_id TEXT NOT NULL,
                    entity TEXT NOT NULL,
                    action TEXT NOT NULL,
                    status TEXT NOT NULL,
                    local_key TEXT,
                    feeder_id TEXT,
                    message TEXT,
                    response TEXT,
                    created_at TEXT NOT NULL
                )
            SQL,
            'feeder_reference' => <<<'SQL'
                CREATE TABLE IF NOT EXISTS feeder_reference (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    kind TEXT NOT NULL,
                    reference_key TEXT NOT NULL,
                    feeder_id TEXT,
                    payload TEXT,
                    synced_at TEXT NOT NULL,
                    UNIQUE (kind, reference_key)
                )
            SQL,
            'indexes' => <<<'SQL'
                CREATE INDEX IF NOT EXISTS sync_logs_run_idx ON sync_logs (run_id, status)
            SQL,
            'indexes2' => <<<'SQL'
                CREATE INDEX IF NOT EXISTS feeder_reference_kind_idx ON feeder_reference (kind)
            SQL,
        ];

        foreach ($statements as $name => $sql) {
            try {
                $this->pdo()->exec($sql);
            } catch (PDOException $exception) {
                throw new RuntimeException("Migrasi gagal pada [{$name}]: ".$exception->getMessage(), 0, $exception);
            }
        }

        // Column added after the first release: bring older databases up to date
        // without asking the operator to delete their ledger.
        $this->ensureColumn('sync_runs', 'planned', 'INTEGER NOT NULL DEFAULT 0');

        return array_keys($statements);
    }

    /**
     * Add a column when it does not exist yet (SQLite has no "ADD COLUMN IF NOT EXISTS").
     */
    public function ensureColumn(string $table, string $column, string $definition): void
    {
        $columns = $this->select("PRAGMA table_info({$table})");

        foreach ($columns as $existing) {
            if (($existing['name'] ?? null) === $column) {
                return;
            }
        }

        $this->pdo()->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
    }

    /**
     * @param  array<string|int, mixed>  $parameters
     * @return array<int, array<string, mixed>>
     */
    public function select(string $sql, array $parameters = []): array
    {
        $statement = $this->pdo()->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetchAll() ?: [];
    }

    /**
     * @param  array<string|int, mixed>  $parameters
     * @return array<string, mixed>|null
     */
    public function first(string $sql, array $parameters = []): ?array
    {
        $statement = $this->pdo()->prepare($sql);
        $statement->execute($parameters);

        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @param  array<string|int, mixed>  $parameters
     */
    public function scalar(string $sql, array $parameters = []): mixed
    {
        $statement = $this->pdo()->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetchColumn();
    }

    /**
     * @param  array<string|int, mixed>  $parameters
     */
    public function execute(string $sql, array $parameters = []): int
    {
        $statement = $this->pdo()->prepare($sql);
        $statement->execute($parameters);

        return $statement->rowCount();
    }

    /**
     * Insert a row and return its id.
     *
     * @param  array<string, mixed>  $values
     */
    public function insert(string $table, array $values): int
    {
        $this->execute($this->buildInsertSql($table, $values, null), $values);

        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * Insert or update on a conflict target (upsert semantics).
     *
     * Used for cache-like tables (settings, feeder reference) where a repeated sync
     * must refresh the row instead of failing on the unique index.
     *
     * @param  array<string, mixed>  $values
     * @param  array<int, string>  $conflictColumns
     * @param  array<int, string>  $updateColumns  defaults to every column except the conflict target
     */
    public function upsert(string $table, array $values, array $conflictColumns, array $updateColumns = []): void
    {
        $updateColumns = $updateColumns === []
            ? array_values(array_diff(array_keys($values), $conflictColumns))
            : $updateColumns;

        $this->execute($this->buildInsertSql($table, $values, [$conflictColumns, $updateColumns]), $values);
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array{0: array<int, string>, 1: array<int, string>}|null  $conflict
     */
    private function buildInsertSql(string $table, array $values, ?array $conflict): string
    {
        $columns = array_keys($values);
        $placeholders = array_map(static fn (string $column) => ':'.$column, $columns);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        if ($conflict === null) {
            return $sql;
        }

        [$conflictColumns, $updateColumns] = $conflict;

        $assignments = implode(', ', array_map(
            static fn (string $column) => $column.' = excluded.'.$column,
            $updateColumns
        ));

        return sprintf(
            '%s ON CONFLICT(%s) DO UPDATE SET %s',
            $sql,
            implode(', ', $conflictColumns),
            $assignments
        );
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array<string, mixed>  $where
     */
    public function update(string $table, array $values, array $where): int
    {
        $sets = implode(', ', array_map(static fn (string $column) => $column.' = :set_'.$column, array_keys($values)));
        $conditions = implode(' AND ', array_map(static fn (string $column) => $column.' = :where_'.$column, array_keys($where)));

        $parameters = [];

        foreach ($values as $column => $value) {
            $parameters['set_'.$column] = $value;
        }

        foreach ($where as $column => $value) {
            $parameters['where_'.$column] = $value;
        }

        return $this->execute("UPDATE {$table} SET {$sets} WHERE {$conditions}", $parameters);
    }

    public function transaction(callable $callback): mixed
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();

        try {
            $result = $callback();
            $pdo->commit();

            return $result;
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function now(): string
    {
        return (new \DateTimeImmutable())->format('Y-m-d H:i:s');
    }
}
