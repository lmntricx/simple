<?php

namespace Simple\Database\Schema;

class Column
{
    public bool $nullable = false;
    public bool $unique = false;
    public bool $unsigned = false;
    public bool $hasDefault = false;
    public mixed $default = null;
    public bool $useCurrent = false;
    /** @var array{table: string, column: string, onDelete: ?string}|null */
    public ?array $foreign = null;

    /**
     * @param string $type one of: increments, integer, bigInteger, string, text, boolean,
     *                     date, datetime, timestamp, decimal, float, json
     */
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly array $options = [],
    ) {
    }

    public function nullable(bool $value = true): static
    {
        $this->nullable = $value;
        return $this;
    }

    public function unique(bool $value = true): static
    {
        $this->unique = $value;
        return $this;
    }

    public function unsigned(bool $value = true): static
    {
        $this->unsigned = $value;
        return $this;
    }

    public function default(mixed $value): static
    {
        $this->hasDefault = true;
        $this->default = $value;
        return $this;
    }

    public function useCurrent(): static
    {
        $this->useCurrent = true;
        return $this;
    }

    /**
     * Add a foreign key: $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()
     */
    public function constrained(string $table, string $column = 'id'): static
    {
        $this->foreign = ['table' => $table, 'column' => $column, 'onDelete' => null];
        return $this;
    }

    public function cascadeOnDelete(): static
    {
        return $this->onDelete('CASCADE');
    }

    public function nullOnDelete(): static
    {
        $this->nullable = true;
        return $this->onDelete('SET NULL');
    }

    public function onDelete(string $action): static
    {
        if ($this->foreign === null) {
            throw new \LogicException("Call constrained() before onDelete() on column [{$this->name}].");
        }
        $this->foreign['onDelete'] = strtoupper($action);
        return $this;
    }

    public function toSql(string $driver): string
    {
        $q = $driver === 'mysql' ? '`' : '"';

        if ($this->type === 'increments') {
            return $q . $this->name . $q . ' ' . match ($driver) {
                'mysql' => 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY',
                'pgsql' => 'BIGSERIAL PRIMARY KEY',
                default => 'INTEGER PRIMARY KEY AUTOINCREMENT',
            };
        }

        $sql = $q . $this->name . $q . ' ' . $this->typeSql($driver);

        if ($this->unsigned && $driver === 'mysql') {
            $sql .= ' UNSIGNED';
        }

        $sql .= $this->nullable ? ' NULL' : ' NOT NULL';

        if ($this->useCurrent) {
            $sql .= ' DEFAULT CURRENT_TIMESTAMP';
        } elseif ($this->hasDefault) {
            $sql .= ' DEFAULT ' . $this->defaultSql();
        }

        if ($this->unique) {
            $sql .= ' UNIQUE';
        }

        return $sql;
    }

    protected function typeSql(string $driver): string
    {
        return match ($this->type) {
            'integer' => $driver === 'sqlite' ? 'INTEGER' : 'INT',
            'bigInteger' => $driver === 'sqlite' ? 'INTEGER' : 'BIGINT',
            'string' => 'VARCHAR(' . ($this->options['length'] ?? 255) . ')',
            'text' => 'TEXT',
            'boolean' => match ($driver) {
                'mysql' => 'TINYINT(1)',
                'pgsql' => 'BOOLEAN',
                default => 'INTEGER',
            },
            'date' => 'DATE',
            'datetime', 'timestamp' => $driver === 'mysql' ? strtoupper($this->type) : 'TIMESTAMP',
            'decimal' => sprintf('DECIMAL(%d, %d)', $this->options['precision'] ?? 10, $this->options['scale'] ?? 2),
            'float' => $driver === 'pgsql' ? 'DOUBLE PRECISION' : 'DOUBLE',
            'json' => match ($driver) {
                'mysql' => 'JSON',
                'pgsql' => 'JSONB',
                default => 'TEXT',
            },
            default => throw new \InvalidArgumentException("Unknown column type [{$this->type}]."),
        };
    }

    protected function defaultSql(): string
    {
        return match (true) {
            $this->default === null => 'NULL',
            is_bool($this->default) => $this->default ? '1' : '0',
            is_int($this->default), is_float($this->default) => (string) $this->default,
            default => "'" . str_replace("'", "''", (string) $this->default) . "'",
        };
    }
}
