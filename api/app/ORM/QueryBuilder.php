<?php

namespace app\ORM;

use PDO;
use PDOStatement;

class QueryBuilder
{
    protected PDO $connection;
    protected string $table = '';
    protected array $select = ['*'];
    protected array $wheres = [];
    protected array $bindings = [];
    protected array $orders = [];
    protected ?int $limit = null;
    protected ?int $offset = null;
    protected array $joins = [];

    public function __construct(PDO $connection, string $table = '')
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    public function table(string $table): self
    {
        $this->table = $table;
        return $this;
    }

    public function select(string ...$columns): self
    {
        $this->select = $columns ?: ['*'];
        return $this;
    }

    public function where(string $column, string $operator = null, mixed $value = null): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = sprintf('%s %s ?', $column, $operator);
        $this->bindings[] = $value;

        return $this;
    }

    public function orWhere(string $column, string $operator = null, mixed $value = null): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = 'OR ' . sprintf('%s %s ?', $column, $operator);
        $this->bindings[] = $value;

        return $this;
    }

    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self
    {
        $this->joins[] = sprintf('%s JOIN %s ON %s %s %s', strtoupper($type), $table, $first, $operator, $second);
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orders[] = sprintf('%s %s', $column, strtoupper($direction));
        return $this;
    }

    public function limit(int $limit, ?int $offset = null): self
    {
        $this->limit = $limit;
        $this->offset = $offset;
        return $this;
    }

    public function get(): array
    {
        return $this->execute()->fetchAll(PDO::FETCH_ASSOC);
    }

    public function first(): ?array
    {
        $this->limit(1);
        $stmt = $this->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function insert(array $data): bool
    {
        $columns = array_keys($data);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(', ', $columns),
            $placeholders
        );

        $this->bindings = array_values($data);

        return $this->execute($sql)->rowCount() > 0;
    }

    public function update(array $data): bool
    {
        if (empty($this->wheres)) {
            throw new \RuntimeException('Update without where clause is not allowed.');
        }

        $columns = array_keys($data);
        $set = implode(', ', array_map(fn($column) => "$column = ?", $columns));
        $sql = sprintf('UPDATE %s SET %s %s', $this->table, $set, $this->buildWhereClause());
        $this->bindings = array_merge(array_values($data), $this->bindings);

        return $this->execute($sql)->rowCount() > 0;
    }

    public function delete(): bool
    {
        if (empty($this->wheres)) {
            throw new \RuntimeException('Delete without where clause is not allowed.');
        }

        $sql = sprintf('DELETE FROM %s %s', $this->table, $this->buildWhereClause());

        return $this->execute($sql)->rowCount() > 0;
    }

    public function raw(string $sql, array $bindings = []): array
    {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($bindings);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function toSql(): string
    {
        return $this->buildSelect();
    }

    protected function execute(string $sql = null): PDOStatement
    {
        $sql = $sql ?? $this->buildSelect();
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($this->bindings);

        return $stmt;
    }

    protected function buildSelect(): string
    {
        $query = sprintf('SELECT %s FROM %s', implode(', ', $this->select), $this->table);

        if (!empty($this->joins)) {
            $query .= ' ' . implode(' ', $this->joins);
        }

        if (!empty($this->wheres)) {
            $query .= ' ' . $this->buildWhereClause();
        }

        if (!empty($this->orders)) {
            $query .= ' ORDER BY ' . implode(', ', $this->orders);
        }

        if ($this->limit !== null) {
            $query .= ' LIMIT ' . $this->limit;

            if ($this->offset !== null) {
                $query .= ' OFFSET ' . $this->offset;
            }
        }

        return $query;
    }

    protected function buildWhereClause(): string
    {
        return 'WHERE ' . implode(' ', $this->wheres);
    }
}