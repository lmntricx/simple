<?php

namespace Simple\Database;

use InvalidArgumentException;
use PDO;
use PDOStatement;

/**
 * Fluent SQL builder. Values are always bound; table/column names are validated
 * and quoted, so passing user input as a column name can't inject SQL.
 *
 * When created through a Model, get()/first() return model instances instead of arrays.
 */
class QueryBuilder
{
    private const OPERATORS = ['=', '!=', '<>', '<', '<=', '>', '>=', 'LIKE', 'NOT LIKE'];

    protected array $columns = ['*'];
    /** @var array<int, array{boolean: string, sql: string}> */
    protected array $wheres = [];
    protected array $bindings = [];
    protected array $joins = [];
    protected array $orders = [];
    protected ?int $limit = null;
    protected ?int $offset = null;

    /** @param class-string<Model>|null $model */
    public function __construct(
        protected PDO $connection,
        protected string $table,
        protected ?string $model = null,
    ) {
    }

    public function select(string ...$columns): static
    {
        $this->columns = $columns ?: ['*'];
        return $this;
    }

    /**
     * where('age', 18), where('age', '>=', 18), where('deleted_at', null)
     */
    public function where(string $column, mixed $operator = null, mixed $value = null): static
    {
        return $this->addWhere('AND', func_num_args() === 2, $column, $operator, $value);
    }

    public function orWhere(string $column, mixed $operator = null, mixed $value = null): static
    {
        return $this->addWhere('OR', func_num_args() === 2, $column, $operator, $value);
    }

    public function whereNull(string $column): static
    {
        $this->wheres[] = ['boolean' => 'AND', 'sql' => $this->wrap($column) . ' IS NULL'];
        return $this;
    }

    public function whereNotNull(string $column): static
    {
        $this->wheres[] = ['boolean' => 'AND', 'sql' => $this->wrap($column) . ' IS NOT NULL'];
        return $this;
    }

    public function whereIn(string $column, array $values): static
    {
        if ($values === []) {
            $this->wheres[] = ['boolean' => 'AND', 'sql' => '1 = 0'];
            return $this;
        }

        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $this->wheres[] = ['boolean' => 'AND', 'sql' => $this->wrap($column) . " IN ($placeholders)"];
        array_push($this->bindings, ...array_values($values));

        return $this;
    }

    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): static
    {
        $type = strtoupper($type);
        if (!in_array($type, ['INNER', 'LEFT', 'RIGHT'], true)) {
            throw new InvalidArgumentException("Invalid join type [$type].");
        }

        $this->joins[] = sprintf(
            '%s JOIN %s ON %s %s %s',
            $type,
            $this->wrap($table),
            $this->wrap($first),
            $this->operator($operator),
            $this->wrap($second),
        );

        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): static
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    public function orderBy(string $column, string $direction = 'ASC'): static
    {
        $direction = strtoupper($direction);
        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            throw new InvalidArgumentException("Invalid order direction [$direction].");
        }

        $this->orders[] = $this->wrap($column) . ' ' . $direction;
        return $this;
    }

    public function latest(string $column = 'created_at'): static
    {
        return $this->orderBy($column, 'DESC');
    }

    public function limit(int $limit): static
    {
        $this->limit = max(0, $limit);
        return $this;
    }

    public function offset(int $offset): static
    {
        $this->offset = max(0, $offset);
        return $this;
    }

    public function get(): array
    {
        $rows = $this->run($this->toSql(), $this->bindings)->fetchAll(PDO::FETCH_ASSOC);

        return $this->model ? $this->model::hydrate($rows) : $rows;
    }

    public function first(): mixed
    {
        $query = clone $this;
        return $query->limit(1)->get()[0] ?? null;
    }

    public function find(mixed $id, string $key = 'id'): mixed
    {
        return (clone $this)->where($key, $id)->first();
    }

    public function count(): int
    {
        $query = clone $this;
        $query->columns = ['COUNT(*) AS aggregate'];
        $query->orders = [];
        $query->limit = $query->offset = null;

        return (int) $query->run($query->toSql(), $query->bindings)->fetchColumn();
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /**
     * Returns ['data' => [...], 'meta' => ['page', 'per_page', 'total', 'last_page']].
     */
    public function paginate(int $perPage = 15, int $page = 1): array
    {
        $page = max(1, $page);
        $total = $this->count();
        $items = (clone $this)->limit($perPage)->offset(($page - 1) * $perPage)->get();

        return [
            'data' => $items,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ];
    }

    /**
     * Inserts a row and returns the new auto-increment id (as a string, or false if none).
     */
    public function insert(array $values): string|false
    {
        if ($values === []) {
            throw new InvalidArgumentException('Cannot insert an empty row.');
        }

        $columns = implode(', ', array_map(fn ($c) => $this->wrap($c), array_keys($values)));
        $placeholders = implode(', ', array_fill(0, count($values), '?'));

        $this->run("INSERT INTO {$this->wrap($this->table)} ($columns) VALUES ($placeholders)", array_values($values));

        return $this->connection->lastInsertId();
    }

    /**
     * Returns the number of affected rows.
     */
    public function update(array $values): int
    {
        if ($this->wheres === []) {
            throw new InvalidArgumentException('Refusing to UPDATE without a WHERE clause.');
        }
        if ($values === []) {
            return 0;
        }

        $set = implode(', ', array_map(fn ($c) => $this->wrap($c) . ' = ?', array_keys($values)));
        $sql = "UPDATE {$this->wrap($this->table)} SET $set " . $this->compileWheres();

        return $this->run($sql, [...array_values($values), ...$this->bindings])->rowCount();
    }

    /**
     * Returns the number of deleted rows.
     */
    public function delete(): int
    {
        if ($this->wheres === []) {
            throw new InvalidArgumentException('Refusing to DELETE without a WHERE clause.');
        }

        return $this->run("DELETE FROM {$this->wrap($this->table)} " . $this->compileWheres(), $this->bindings)->rowCount();
    }

    /**
     * Escape hatch for SQL the builder can't express. Always use bindings for values.
     */
    public function raw(string $sql, array $bindings = []): array
    {
        return $this->run($sql, $bindings)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function toSql(): string
    {
        $columns = implode(', ', array_map(
            fn ($c) => $c === 'COUNT(*) AS aggregate' ? $c : $this->wrap($c),
            $this->columns,
        ));

        $sql = "SELECT $columns FROM {$this->wrap($this->table)}";

        if ($this->joins !== []) {
            $sql .= ' ' . implode(' ', $this->joins);
        }
        if ($this->wheres !== []) {
            $sql .= ' ' . $this->compileWheres();
        }
        if ($this->orders !== []) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orders);
        }
        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
        }
        if ($this->offset !== null) {
            // SQLite and MySQL need a LIMIT before OFFSET.
            $sql .= ($this->limit === null ? ' LIMIT ' . PHP_INT_MAX : '') . ' OFFSET ' . $this->offset;
        }

        return $sql;
    }

    public function getBindings(): array
    {
        return $this->bindings;
    }

    protected function addWhere(string $boolean, bool $shorthand, string $column, mixed $operator, mixed $value): static
    {
        if ($shorthand) {
            [$value, $operator] = [$operator, '='];
        }

        $operator = $this->operator((string) $operator);

        if ($value === null) {
            $null = match ($operator) {
                '=' => 'IS NULL',
                '!=', '<>' => 'IS NOT NULL',
                default => throw new InvalidArgumentException("Cannot compare NULL with [$operator]."),
            };
            $this->wheres[] = ['boolean' => $boolean, 'sql' => $this->wrap($column) . ' ' . $null];
            return $this;
        }

        $this->wheres[] = ['boolean' => $boolean, 'sql' => $this->wrap($column) . " $operator ?"];
        $this->bindings[] = is_bool($value) ? (int) $value : $value;

        return $this;
    }

    protected function compileWheres(): string
    {
        $sql = '';
        foreach ($this->wheres as $i => $where) {
            $sql .= ($i === 0 ? 'WHERE ' : " {$where['boolean']} ") . $where['sql'];
        }
        return $sql;
    }

    protected function operator(string $operator): string
    {
        $operator = strtoupper(trim($operator));
        if (!in_array($operator, self::OPERATORS, true)) {
            throw new InvalidArgumentException("Invalid operator [$operator].");
        }
        return $operator;
    }

    /**
     * Quote an identifier: users.id => `users`.`id`, "*" and "users.*" pass through.
     */
    protected function wrap(string $identifier): string
    {
        if ($identifier === '*') {
            return '*';
        }

        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.([A-Za-z_][A-Za-z0-9_]*|\*))?$/', $identifier)) {
            throw new InvalidArgumentException("Invalid identifier [$identifier].");
        }

        $quote = Connection::driver($this->connection) === 'mysql' ? '`' : '"';

        return implode('.', array_map(
            fn ($part) => $part === '*' ? '*' : $quote . $part . $quote,
            explode('.', $identifier),
        ));
    }

    protected function run(string $sql, array $bindings): PDOStatement
    {
        $statement = $this->connection->prepare($sql);
        $statement->execute($bindings);
        return $statement;
    }
}
