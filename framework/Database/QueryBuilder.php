<?php

namespace Simple\Database;

use Closure;
use InvalidArgumentException;
use PDO;
use PDOException;
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
    /** Number of leading wheres that are always ANDed with the rest (see lockConstraints). */
    protected int $lockedWheres = 0;
    /** Relations to eager load on the results (model queries only). */
    protected array $eagerLoad = [];

    /** @param class-string<Model>|null $model */
    public function __construct(
        protected PDO $connection,
        protected string $table,
        protected ?string $model = null,
    ) {
    }

    /**
     * select('id', 'name'), select('users.*'), select('name as author')
     */
    public function select(string ...$columns): static
    {
        $this->columns = $columns ?: ['*'];
        return $this;
    }

    /**
     * where('age', 18), where('age', '>=', 18), where('deleted_at', null),
     * where(fn (QueryBuilder $q) => $q->where('a', 1)->orWhere('b', 2))  // grouped in parentheses
     */
    public function where(string|Closure $column, mixed $operator = null, mixed $value = null): static
    {
        return $this->addWhere('AND', func_num_args() === 2, $column, $operator, $value);
    }

    public function orWhere(string|Closure $column, mixed $operator = null, mixed $value = null): static
    {
        return $this->addWhere('OR', func_num_args() === 2, $column, $operator, $value);
    }

    /**
     * Make the wheres added so far mandatory: later orWhere() calls are grouped
     * so they can't escape them. Relations use this so that
     * $user->posts()->orWhere(...) never returns another user's posts.
     */
    public function lockConstraints(): static
    {
        $this->lockedWheres = count($this->wheres);
        return $this;
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
        array_push($this->bindings, ...array_map([$this, 'bindable'], array_values($values)));

        return $this;
    }

    public function whereNotIn(string $column, array $values): static
    {
        if ($values === []) {
            return $this;
        }

        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $this->wheres[] = ['boolean' => 'AND', 'sql' => $this->wrap($column) . " NOT IN ($placeholders)"];
        array_push($this->bindings, ...array_map([$this, 'bindable'], array_values($values)));

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

    /**
     * Eager load relations with the results, one query per relation instead
     * of one per row: Post::with('author', 'comments.author')->get()
     */
    public function with(string ...$relations): static
    {
        if ($this->model === null) {
            throw new \LogicException('with() only works on model queries.');
        }

        array_push($this->eagerLoad, ...$relations);
        return $this;
    }

    public function get(): array
    {
        $rows = $this->run($this->toSql(), $this->bindings)->fetchAll(PDO::FETCH_ASSOC);

        if ($this->model === null) {
            return $rows;
        }

        $models = $this->model::hydrate($rows);

        if ($this->eagerLoad !== []) {
            $this->model::eagerLoadRelations($models, $this->eagerLoad);
        }

        return $models;
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

    /**
     * Values of a single column: pluck('email') => ['a@x.io', ...]
     */
    public function pluck(string $column): array
    {
        $query = clone $this;
        $query->columns = [$column];
        $query->model = null;

        return $query->run($query->toSql(), $query->bindings)->fetchAll(PDO::FETCH_COLUMN);
    }

    public function count(): int
    {
        $sql = 'SELECT COUNT(*) FROM ' . $this->compileFromAndWheres();

        return (int) $this->run($sql, $this->bindings)->fetchColumn();
    }

    public function exists(): bool
    {
        $sql = 'SELECT 1 FROM ' . $this->compileFromAndWheres() . ' LIMIT 1';

        return $this->run($sql, $this->bindings)->fetchColumn() !== false;
    }

    /**
     * Returns ['data' => [...], 'meta' => ['page', 'per_page', 'total', 'last_page']].
     */
    public function paginate(int $perPage = 15, int $page = 1): array
    {
        $perPage = max(1, $perPage);
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

        $this->run(
            "INSERT INTO {$this->wrap($this->table)} ($columns) VALUES ($placeholders)",
            array_map([$this, 'bindable'], array_values($values)),
        );

        try {
            return $this->connection->lastInsertId();
        } catch (PDOException) {
            // PostgreSQL throws when the table has no sequence (no auto-increment id).
            return false;
        }
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
        $values = array_map([$this, 'bindable'], array_values($values));

        return $this->run($sql, [...$values, ...$this->bindings])->rowCount();
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
        $columns = $this->columns;

        // With a join, "*" would let the joined table's columns (e.g. its id)
        // overwrite the model's own, so only select the model's table.
        if ($this->model && $this->joins !== [] && $columns === ['*']) {
            $columns = [$this->table . '.*'];
        }

        $sql = 'SELECT ' . implode(', ', array_map([$this, 'wrapSelect'], $columns))
            . ' FROM ' . $this->compileFromAndWheres();

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

    protected function addWhere(string $boolean, bool $shorthand, string|Closure $column, mixed $operator, mixed $value): static
    {
        if ($column instanceof Closure) {
            $nested = new static($this->connection, $this->table);
            $column($nested);

            if ($nested->wheres !== []) {
                $this->wheres[] = ['boolean' => $boolean, 'sql' => '(' . $nested->compileConditions() . ')'];
                array_push($this->bindings, ...$nested->bindings);
            }
            return $this;
        }

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
        $this->bindings[] = $this->bindable($value);

        return $this;
    }

    /**
     * Normalise a value for PDO, rejecting ones that can't be bound.
     */
    protected function bindable(mixed $value): mixed
    {
        return match (true) {
            is_bool($value) => (int) $value,
            $value instanceof \DateTimeInterface => $value->format('Y-m-d H:i:s'),
            is_array($value), is_object($value) && !method_exists($value, '__toString') => throw new InvalidArgumentException(
                'Cannot bind a value of type ' . get_debug_type($value) . ' (use whereIn() for lists, or a json cast for arrays).'
            ),
            default => $value,
        };
    }

    protected function compileFromAndWheres(): string
    {
        $sql = $this->wrap($this->table);

        if ($this->joins !== []) {
            $sql .= ' ' . implode(' ', $this->joins);
        }
        if ($this->wheres !== []) {
            $sql .= ' ' . $this->compileWheres();
        }

        return $sql;
    }

    protected function compileWheres(): string
    {
        return $this->wheres === [] ? '' : 'WHERE ' . $this->compileConditions();
    }

    /**
     * Locked wheres are joined with AND; anything added after them is wrapped in
     * parentheses when needed, so "user_id = ? AND (a = ? OR b = ?)".
     */
    protected function compileConditions(): string
    {
        $join = function (array $wheres): string {
            $sql = '';
            foreach (array_values($wheres) as $i => $where) {
                $sql .= ($i === 0 ? '' : " {$where['boolean']} ") . $where['sql'];
            }
            return $sql;
        };

        $locked = array_slice($this->wheres, 0, $this->lockedWheres);
        $rest = array_slice($this->wheres, $this->lockedWheres);

        if ($locked === []) {
            return $join($rest);
        }

        $sql = implode(' AND ', array_column($locked, 'sql'));

        if ($rest !== []) {
            $sql .= ' AND ' . (count($rest) > 1 ? '(' . $join($rest) . ')' : $rest[0]['sql']);
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

    /**
     * Like wrap(), but also accepts "column as alias".
     */
    protected function wrapSelect(string $column): string
    {
        if (preg_match('/^(.+?)\s+as\s+([A-Za-z_][A-Za-z0-9_]*)$/i', trim($column), $m)) {
            return $this->wrap($m[1]) . ' AS ' . $this->wrap($m[2]);
        }

        return $this->wrap($column);
    }

    protected function run(string $sql, array $bindings): PDOStatement
    {
        $start = hrtime(true);
        $statement = $this->connection->prepare($sql);
        $statement->execute($bindings);
        Connection::logQuery($sql, $bindings, (hrtime(true) - $start) / 1e6);

        return $statement;
    }
}
