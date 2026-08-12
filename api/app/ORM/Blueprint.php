<?php

namespace app\ORM;

class Blueprint
{
    protected array $columns = [];
    protected array $constraints = [];

    public function id(): Column
    {
        return $this->integer('id')->unsigned()->primary()->autoIncrement();
    }

    public function integer(string $name, int $length = 11): Column
    {
        return $this->addColumn(new Column($name, 'INT', $length));
    }

    public function string(string $name, int $length = 255): Column
    {
        return $this->addColumn(new Column($name, 'VARCHAR', $length));
    }

    public function text(string $name): Column
    {
        return $this->addColumn(new Column($name, 'TEXT'));
    }

    public function boolean(string $name): Column
    {
        return $this->addColumn(new Column($name, 'TINYINT', 1));
    }

    public function datetime(string $name): Column
    {
        return $this->addColumn(new Column($name, 'DATETIME'));
    }

    public function date(string $name): Column
    {
        return $this->addColumn(new Column($name, 'DATE'));
    }

    public function decimal(string $name, int $precision = 10, int $scale = 2): Column
    {
        $column = new Column($name, 'DECIMAL', null);
        $column->length = null;
        $column->type = sprintf('DECIMAL(%d,%d)', $precision, $scale);

        return $this->addColumn($column);
    }

    public function json(string $name): Column
    {
        return $this->addColumn(new Column($name, 'JSON'));
    }

    public function timestamps(): self
    {
        $this->datetime('created_at')->nullable();
        $this->datetime('updated_at')->nullable();
        return $this;
    }

    public function unique(string|array $columns, ?string $constraint = null): self
    {
        $columns = is_array($columns) ? $columns : [$columns];
        $name = $constraint ?? 'uniq_' . implode('_', $columns);
        $this->constraints[] = sprintf('UNIQUE KEY %s (%s)', $name, implode(', ', $columns));
        return $this;
    }

    public function primary(string|array $columns, ?string $constraint = null): self
    {
        $columns = is_array($columns) ? $columns : [$columns];
        $name = $constraint ? 'CONSTRAINT ' . $constraint . ' ' : '';
        $this->constraints[] = sprintf('%sPRIMARY KEY (%s)', $name, implode(', ', $columns));
        return $this;
    }

    public function index(string|array $columns, ?string $name = null): self
    {
        $columns = is_array($columns) ? $columns : [$columns];
        $name = $name ?? 'idx_' . implode('_', $columns);
        $this->constraints[] = sprintf('KEY %s (%s)', $name, implode(', ', $columns));
        return $this;
    }

    public function getColumns(): array
    {
        return $this->columns;
    }

    public function toSql(string $table, string $driver): string
    {
        $columnSql = array_map(fn(Column $column) => $column->toSql($driver), $this->columns);
        $all = array_merge($columnSql, $this->constraints);

        return sprintf('CREATE TABLE %s (%s)', $table, implode(', ', $all));
    }

    protected function addColumn(Column $column): Column
    {
        $this->columns[] = $column;
        return $column;
    }
}