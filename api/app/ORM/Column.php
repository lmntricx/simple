<?php


namespace app\ORM;

class Column
{
    public string $name;
    public string $type;
    public ?int $length;
    public bool $nullable = false;
    public bool $unique = false;
    public bool $primary = false;
    public bool $autoIncrement = false;
    public bool $unsigned = false;
    public mixed $default = null;

    public function __construct(string $name, string $type, ?int $length = null)
    {
        $this->name = $name;
        $this->type = strtoupper($type);
        $this->length = $length;
    }

    public function nullable(bool $nullable = true): self
    {
        $this->nullable = $nullable;
        return $this;
    }

    public function unique(bool $unique = true): self
    {
        $this->unique = $unique;
        return $this;
    }

    public function primary(bool $primary = true): self
    {
        $this->primary = $primary;
        return $this;
    }

    public function autoIncrement(bool $autoIncrement = true): self
    {
        $this->autoIncrement = $autoIncrement;
        return $this;
    }

    public function unsigned(bool $unsigned = true): self
    {
        $this->unsigned = $unsigned;
        return $this;
    }

    public function default(mixed $value): self
    {
        $this->default = $value;
        return $this;
    }

    public function toSql(string $driver): string
    {
        $definition = $this->type;

        if ($this->length !== null) {
            $definition .= "({$this->length})";
        }

        if ($this->unsigned) {
            $definition .= ' UNSIGNED';
        }

        if ($this->autoIncrement) {
            if ($driver === 'sqlite') {
                $definition = 'INTEGER';
                $definition .= ' PRIMARY KEY AUTOINCREMENT';
            } else {
                $definition .= ' AUTO_INCREMENT';
            }
        }

        if ($this->primary && !$this->autoIncrement) {
            $definition .= ' PRIMARY KEY';
        }

        if (!$this->nullable && !$this->autoIncrement) {
            $definition .= ' NOT NULL';
        }

        if ($this->unique && !$this->primary) {
            $definition .= ' UNIQUE';
        }

        if ($this->default !== null) {
            $definition .= ' DEFAULT ' . $this->formatDefault($driver);
        }

        return sprintf('%s %s', $this->name, $definition);
    }

    protected function formatDefault(string $driver): string
    {
        if (is_string($this->default)) {
            return "'" . str_replace("'", "''", $this->default) . "'";
        }

        if (is_bool($this->default)) {
            return $this->default ? '1' : '0';
        }

        return (string) $this->default;
    }
}