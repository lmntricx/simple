<?php

namespace app\ORM;

use core\Database;
use PDO;

abstract class Model
{
    use Relations;

    protected string $table = '';
    protected string $primaryKey = 'id';
    protected array $fillable = [];
    protected array $hidden = [];
    protected array $attributes = [];
    protected PDO $connection;
    protected static bool $schemaBooted = false;
    protected static bool $autoCreateSchema = true;

    public function __construct(array $attributes = [], bool $bootSchema = true)
    {
        $this->connection = Database::getInstance();

        if ($bootSchema) {
            $this->bootSchema();
        }

        $this->attributes = $attributes;
    }

    protected function bootSchema(): void
    {
        if (!static::$autoCreateSchema || static::$schemaBooted) {
            return;
        }

        static::ensureSchema();
        static::$schemaBooted = true;
    }

    protected static function ensureSchema(): void
    {
        $instance = new static([], false);
        $table = $instance->getTable();
        SchemaBuilder::createTableIfNotExists($instance->getConnection(), $table, function (Blueprint $blueprint): void {
            static::schema($blueprint);
        });
    }

    protected static function schema(Blueprint $table): void
    {
        // Override in child models.
    }

    public function getConnection(): PDO
    {
        return $this->connection;
    }

    public function getTable(): string
    {
        if ($this->table !== '') {
            return $this->table;
        }

        return strtolower((new \ReflectionClass($this))->getShortName()) . 's';
    }

    public function newQuery(): QueryBuilder
    {
        return new QueryBuilder($this->getConnection(), $this->getTable());
    }

    public static function query(): QueryBuilder
    {
        return (new static([], false))->newQuery();
    }

    public static function all(): array
    {
        return static::query()->get();
    }

    public static function find(mixed $id): ?self
    {
        $instance = new static([], false);
        $row = $instance->newQuery()
            ->where($instance->primaryKey, '=', $id)
            ->first();

        return $row ? new static($row) : null;
    }

    public static function findBy(string $column, mixed $value): ?self
    {
        $instance = new static([], false);
        $row = $instance->newQuery()
            ->where($column, '=', $value)
            ->first();

        return $row ? new static($row) : null;
    }

    public static function create(array $data): bool|self
    {
        $instance = new static([], false);
        $attributes = $instance->filterFillable($data);

        if ($instance->newQuery()->insert($attributes)) {
            $attributes[$instance->primaryKey] = $instance->getConnection()->lastInsertId();
            return new static($attributes);
        }

        return false;
    }

    public function update(array $data): bool
    {
        if (empty($this->attributes[$this->primaryKey])) {
            return false;
        }

        $attributes = $this->filterFillable($data);

        return $this->newQuery()
            ->where($this->primaryKey, '=', $this->attributes[$this->primaryKey])
            ->update($attributes);
    }

    public function delete(): bool
    {
        if (empty($this->attributes[$this->primaryKey])) {
            return false;
        }

        return $this->newQuery()
            ->where($this->primaryKey, '=', $this->attributes[$this->primaryKey])
            ->delete();
    }

    public function fill(array $attributes): static
    {
        $this->attributes = array_merge($this->attributes, $attributes);
        return $this;
    }

    public function getAttribute(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function toArray(): array
    {
        if ($this->hidden === []) {
            return $this->attributes;
        }

        return array_diff_key($this->attributes, array_flip($this->hidden));
    }

    public static function hydrate(array $rows): array
    {
        return array_map(fn($row) => new static($row), $rows);
    }

    protected function filterFillable(array $data): array
    {
        return $this->fillable === [] ? $data : array_intersect_key($data, array_flip($this->fillable));
    }

    public function __get(string $key): mixed
    {
        return $this->getAttribute($key);
    }

    public function __set(string $key, mixed $value): void
    {
        $this->setAttribute($key, $value);
    }

    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    public function __unset(string $key): void
    {
        unset($this->attributes[$key]);
    }
}