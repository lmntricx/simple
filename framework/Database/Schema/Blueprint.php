<?php

namespace Simple\Database\Schema;

class Blueprint
{
    /** @var Column[] */
    protected array $columns = [];
    protected array $indexes = [];

    public function __construct(public readonly string $table)
    {
    }

    public function id(string $name = 'id'): Column
    {
        return $this->add(new Column($name, 'increments'));
    }

    public function integer(string $name): Column
    {
        return $this->add(new Column($name, 'integer'));
    }

    public function bigInteger(string $name): Column
    {
        return $this->add(new Column($name, 'bigInteger'));
    }

    /**
     * Unsigned BIGINT matching id() — chain ->constrained('table') to add the foreign key.
     */
    public function foreignId(string $name): Column
    {
        return $this->bigInteger($name)->unsigned();
    }

    public function string(string $name, int $length = 255): Column
    {
        return $this->add(new Column($name, 'string', ['length' => $length]));
    }

    public function text(string $name): Column
    {
        return $this->add(new Column($name, 'text'));
    }

    public function boolean(string $name): Column
    {
        return $this->add(new Column($name, 'boolean'));
    }

    public function date(string $name): Column
    {
        return $this->add(new Column($name, 'date'));
    }

    public function datetime(string $name): Column
    {
        return $this->add(new Column($name, 'datetime'));
    }

    public function timestamp(string $name): Column
    {
        return $this->add(new Column($name, 'timestamp'));
    }

    public function decimal(string $name, int $precision = 10, int $scale = 2): Column
    {
        return $this->add(new Column($name, 'decimal', ['precision' => $precision, 'scale' => $scale]));
    }

    public function float(string $name): Column
    {
        return $this->add(new Column($name, 'float'));
    }

    public function json(string $name): Column
    {
        return $this->add(new Column($name, 'json'));
    }

    /**
     * Nullable created_at / updated_at, filled in automatically by Model::save().
     */
    public function timestamps(): void
    {
        $this->datetime('created_at')->nullable();
        $this->datetime('updated_at')->nullable();
    }

    public function index(string|array $columns, ?string $name = null): void
    {
        $this->addIndex('INDEX', (array) $columns, $name);
    }

    public function unique(string|array $columns, ?string $name = null): void
    {
        $this->addIndex('UNIQUE', (array) $columns, $name);
    }

    /**
     * @return string[] statements to run, in order
     */
    public function toSql(string $driver): array
    {
        $q = $driver === 'mysql' ? '`' : '"';
        $wrap = fn (string $name) => $q . $name . $q;

        $definitions = array_map(fn (Column $c) => $c->toSql($driver), $this->columns);

        foreach ($this->columns as $column) {
            if ($column->foreign) {
                $definitions[] = sprintf(
                    'FOREIGN KEY (%s) REFERENCES %s (%s)%s',
                    $wrap($column->name),
                    $wrap($column->foreign['table']),
                    $wrap($column->foreign['column']),
                    $column->foreign['onDelete'] ? ' ON DELETE ' . $column->foreign['onDelete'] : '',
                );
            }
        }

        $statements = [sprintf("CREATE TABLE %s (\n  %s\n)", $wrap($this->table), implode(",\n  ", $definitions))];

        foreach ($this->indexes as $index) {
            $statements[] = sprintf(
                'CREATE %sINDEX %s ON %s (%s)',
                $index['type'] === 'UNIQUE' ? 'UNIQUE ' : '',
                $wrap($index['name']),
                $wrap($this->table),
                implode(', ', array_map($wrap, $index['columns'])),
            );
        }

        return $statements;
    }

    protected function add(Column $column): Column
    {
        $this->columns[] = $column;
        return $column;
    }

    protected function addIndex(string $type, array $columns, ?string $name): void
    {
        $name ??= $this->table . '_' . implode('_', $columns) . '_' . strtolower($type === 'UNIQUE' ? 'unique' : 'index');
        $this->indexes[] = ['type' => $type, 'columns' => $columns, 'name' => $name];
    }
}
