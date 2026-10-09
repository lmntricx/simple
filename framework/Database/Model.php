<?php

namespace Simple\Database;

use JsonSerializable;
use PDO;
use Simple\Http\HttpException;
use Simple\Support\Str;

/**
 * Active Record base class.
 *
 *   class Post extends Model {
 *       protected array $fillable = ['title', 'body'];
 *       protected array $casts = ['published' => 'bool', 'tags' => 'array'];
 *   }
 *
 *   Post::create([...]);  Post::find(1);  Post::where('published', true)->latest()->get();
 *
 * Define set{Name}Attribute($value) to transform a value before it is stored,
 * e.g. setPasswordAttribute() to hash passwords.
 *
 * @method static QueryBuilder where(string $column, mixed $operator = null, mixed $value = null)
 * @method static QueryBuilder whereIn(string $column, array $values)
 * @method static QueryBuilder whereNull(string $column)
 * @method static QueryBuilder orderBy(string $column, string $direction = 'ASC')
 * @method static QueryBuilder latest(string $column = 'created_at')
 * @method static QueryBuilder limit(int $limit)
 * @method static array paginate(int $perPage = 15, int $page = 1)
 * @method static int count()
 */
abstract class Model implements JsonSerializable
{
    protected string $table = '';
    protected string $primaryKey = 'id';
    protected ?string $connection = null;
    protected bool $timestamps = true;

    /** Columns that create()/fill() may set. Empty means none (use $guarded = [] to allow all). */
    protected array $fillable = [];
    /** Set to [] to allow mass assignment of every column. */
    protected ?array $guarded = null;
    /** Columns left out of toArray()/JSON. */
    protected array $hidden = [];
    /** column => int|float|bool|string|array */
    protected array $casts = [];

    protected array $attributes = [];
    protected array $original = [];
    /** Relations loaded through property access ($post->author), included in toArray(). */
    protected array $relations = [];
    public bool $exists = false;

    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    // ---- Querying ---------------------------------------------------------

    public static function query(): QueryBuilder
    {
        $model = new static();
        return new QueryBuilder($model->getConnection(), $model->getTable(), static::class);
    }

    /** @return static[] */
    public static function all(): array
    {
        return static::query()->get();
    }

    public static function find(mixed $id): ?static
    {
        if ($id === null || $id === '') {
            return null;
        }

        return static::query()->find($id, (new static())->primaryKey);
    }

    /**
     * Like find(), but throws a 404 when the record doesn't exist.
     */
    public static function findOrFail(mixed $id): static
    {
        return static::find($id) ?? throw new HttpException(404, (new \ReflectionClass(static::class))->getShortName() . ' not found');
    }

    public static function firstWhere(string $column, mixed $value): ?static
    {
        return static::query()->where($column, $value)->first();
    }

    public static function create(array $attributes): static
    {
        $model = new static($attributes);
        $model->save();
        return $model;
    }

    /**
     * Forward static calls to a new query: User::where(...), User::latest(), ...
     */
    public static function __callStatic(string $method, array $arguments): mixed
    {
        return static::query()->$method(...$arguments);
    }

    /** @return static[] */
    public static function hydrate(array $rows): array
    {
        return array_map(fn (array $row) => static::newFromRow($row), $rows);
    }

    protected static function newFromRow(array $row): static
    {
        $model = new static();
        $model->attributes = $row;
        $model->original = $row;
        $model->exists = true;
        return $model;
    }

    // ---- Persistence ------------------------------------------------------

    public function save(): bool
    {
        $now = date('Y-m-d H:i:s');

        if (!$this->exists) {
            if ($this->timestamps) {
                $this->attributes['created_at'] ??= $now;
                $this->attributes['updated_at'] ??= $now;
            }

            $id = $this->newQuery()->insert($this->attributes);

            if ($id !== false && $id !== '0' && !isset($this->attributes[$this->primaryKey])) {
                $this->attributes[$this->primaryKey] = is_numeric($id) ? (int) $id : $id;
            }

            $this->exists = true;
            $this->original = $this->attributes;
            return true;
        }

        if ($this->getKey() === null) {
            throw new \LogicException('Cannot update a ' . static::class . " without a [{$this->primaryKey}] value.");
        }

        $dirty = $this->getDirty();
        if ($dirty === []) {
            return true;
        }

        if ($this->timestamps && !isset($dirty['updated_at'])) {
            $dirty['updated_at'] = $this->attributes['updated_at'] = $now;
        }

        $this->newQuery()->where($this->primaryKey, $this->getKey())->update($dirty);
        $this->original = $this->attributes;

        return true;
    }

    public function update(array $attributes): bool
    {
        return $this->fill($attributes)->save();
    }

    public function delete(): bool
    {
        if (!$this->exists) {
            return false;
        }

        $deleted = $this->newQuery()->where($this->primaryKey, $this->getKey())->delete() > 0;
        $this->exists = false;

        return $deleted;
    }

    /**
     * Reload attributes from the database.
     */
    public function refresh(): static
    {
        $fresh = static::find($this->getKey());
        if ($fresh) {
            $this->attributes = $this->original = $fresh->attributes;
            $this->relations = [];
        }
        return $this;
    }

    // ---- Attributes -------------------------------------------------------

    /**
     * Mass-assign attributes, respecting $fillable / $guarded.
     */
    public function fill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            }
        }
        return $this;
    }

    /**
     * Set attributes without the mass-assignment check (for trusted data).
     */
    public function forceFill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            $this->setAttribute($key, $value);
        }
        return $this;
    }

    public function isFillable(string $key): bool
    {
        if (in_array($key, $this->fillable, true)) {
            return true;
        }

        return $this->guarded !== null && !in_array($key, $this->guarded, true);
    }

    public function getAttribute(string $key): mixed
    {
        if (!array_key_exists($key, $this->attributes)) {
            return $this->getRelationValue($key);
        }

        return $this->castGet($key, $this->attributes[$key]);
    }

    /**
     * $post->author calls $post->author() once and caches the result; for
     * to-many relations (returning a QueryBuilder) the rows are fetched.
     *
     * Only public, parameterless methods declared on your model whose return
     * type is a Model or QueryBuilder count as relations.
     */
    protected function getRelationValue(string $key): mixed
    {
        if (array_key_exists($key, $this->relations)) {
            return $this->relations[$key];
        }

        if (!method_exists($this, $key)) {
            return null;
        }

        $method = new \ReflectionMethod($this, $key);
        $type = $method->getReturnType();

        if (
            $method->getDeclaringClass()->getName() === self::class
            || !$method->isPublic()
            || $method->isStatic()
            || $method->getNumberOfRequiredParameters() > 0
            || !$type instanceof \ReflectionNamedType
            || !(is_a($type->getName(), Model::class, true) || is_a($type->getName(), QueryBuilder::class, true))
        ) {
            return null;
        }

        $result = $this->$key();

        return $this->relations[$key] = $result instanceof QueryBuilder ? $result->get() : $result;
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $mutator = 'set' . Str::studly($key) . 'Attribute';
        if (method_exists($this, $mutator)) {
            $value = $this->$mutator($value);
        }

        $this->attributes[$key] = $this->castSet($key, $value);
    }

    public function getKey(): mixed
    {
        return $this->attributes[$this->primaryKey] ?? null;
    }

    public function getDirty(): array
    {
        return array_filter(
            $this->attributes,
            fn ($value, $key) => !array_key_exists($key, $this->original) || $this->original[$key] !== $value,
            ARRAY_FILTER_USE_BOTH,
        );
    }

    public function toArray(): array
    {
        $array = [];
        foreach (array_diff_key($this->attributes, array_flip($this->hidden)) as $key => $value) {
            $array[$key] = $this->castGet($key, $value);
        }

        foreach (array_diff_key($this->relations, array_flip($this->hidden)) as $key => $value) {
            $array[$key] = match (true) {
                $value instanceof Model => $value->toArray(),
                is_array($value) => array_map(fn (Model $m) => $m->toArray(), $value),
                default => $value,
            };
        }

        return $array;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    protected function castGet(string $key, mixed $value): mixed
    {
        if ($value === null || !isset($this->casts[$key])) {
            return $value;
        }

        return match ($this->casts[$key]) {
            'int', 'integer' => (int) $value,
            'float', 'double' => (float) $value,
            'bool', 'boolean' => (bool) $value,
            'string' => (string) $value,
            'array', 'json' => is_string($value) ? json_decode($value, true) : $value,
            default => $value,
        };
    }

    protected function castSet(string $key, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($this->casts[$key] ?? null) {
            'bool', 'boolean' => (int) (bool) $value,
            'array', 'json' => is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE),
            default => is_bool($value) ? (int) $value : $value,
        };
    }

    // ---- Relations --------------------------------------------------------

    /**
     * $user->hasOne(Profile::class) => profiles.user_id = users.id
     */
    protected function hasOne(string $related, ?string $foreignKey = null, ?string $localKey = null): ?Model
    {
        $foreignKey ??= $this->foreignKeyName();
        return $related::query()->where($foreignKey, $this->getAttribute($localKey ?? $this->primaryKey))->first();
    }

    /**
     * $user->hasMany(Post::class) => posts.user_id = users.id
     */
    protected function hasMany(string $related, ?string $foreignKey = null, ?string $localKey = null): QueryBuilder
    {
        $foreignKey ??= $this->foreignKeyName();

        // Locked, so a later ->orWhere() can't widen the query to other owners' rows.
        return $related::query()
            ->where($foreignKey, $this->getAttribute($localKey ?? $this->primaryKey))
            ->lockConstraints();
    }

    /**
     * $post->belongsTo(User::class) => users.id = posts.user_id
     */
    protected function belongsTo(string $related, ?string $foreignKey = null, string $ownerKey = 'id'): ?Model
    {
        $foreignKey ??= Str::snake((new \ReflectionClass($related))->getShortName()) . '_id';
        $value = $this->getAttribute($foreignKey);

        return $value === null ? null : $related::query()->where($ownerKey, $value)->first();
    }

    protected function foreignKeyName(): string
    {
        return Str::snake((new \ReflectionClass($this))->getShortName()) . '_' . $this->primaryKey;
    }

    // ---- Plumbing ---------------------------------------------------------

    public function getTable(): string
    {
        return $this->table !== ''
            ? $this->table
            : Str::plural(Str::snake((new \ReflectionClass($this))->getShortName()));
    }

    public function getConnection(): PDO
    {
        return Connection::get($this->connection);
    }

    public function newQuery(): QueryBuilder
    {
        return new QueryBuilder($this->getConnection(), $this->getTable(), static::class);
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
        return isset($this->attributes[$key]) || $this->getRelationValue($key) !== null;
    }

    public function __unset(string $key): void
    {
        unset($this->attributes[$key]);
    }
}
