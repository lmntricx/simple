<?php

namespace Simple\Database\Schema;

class Blueprint
{
    /** @var Column[] */
    protected array $columns = [];
    protected array $indexes = [];
    protected array $dropColumns = [];
    protected array $dropIndexes = [];

    /**
     * @param bool $altering true for Schema::table() (ALTER TABLE), false for Schema::create()
     */
    public function __construct(public readonly string $table, public readonly bool $altering = false)
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
     * Schema::table() only. SQLite needs 3.35+ for DROP COLUMN.
     */
    public function dropColumn(string ...$columns): void
    {
        array_push($this->dropColumns, ...$columns);
    }

    /**
     * Schema::table() only. Pass the index name, or the column(s) it was created
     * on to use the default name: dropIndex(['email']) / dropUnique(['email']).
     */
    public function dropIndex(string|array $index): void
    {
        $this->dropIndexes[] = is_array($index) ? $this->indexName('INDEX', $index) : $index;
    }

    public function dropUnique(string|array $index): void
    {
        $this->dropIndexes[] = is_array($index) ? $this->indexName('UNIQUE', $index) : $index;
    }

    /**
     * @return string[] statements to run, in order
     */
    public function toSql(string $driver): array
    {
        return $this->altering ? $this->alterSql($driver) : $this->createSql($driver);
    }

    protected function createSql(string $driver): array
    {
        $definitions = array_map(fn (Column $c) => $c->toSql($driver), $this->columns);

        foreach ($this->columns as $column) {
            if ($column->foreign) {
                $definitions[] = 'FOREIGN KEY (' . $this->wrap($column->name, $driver) . ') ' . $this->references($column, $driver);
            }
        }

        return [
            sprintf("CREATE TABLE %s (\n  %s\n)", $this->wrap($this->table, $driver), implode(",\n  ", $definitions)),
            ...$this->indexSql($driver),
        ];
    }

    protected function alterSql(string $driver): array
    {
        $table = $this->wrap($this->table, $driver);
        $statements = [];

        foreach ($this->dropIndexes as $name) {
            $statements[] = $driver === 'mysql'
                ? "DROP INDEX {$this->wrap($name, $driver)} ON $table"
                : "DROP INDEX {$this->wrap($name, $driver)}";
        }

        foreach ($this->dropColumns as $name) {
            $statements[] = "ALTER TABLE $table DROP COLUMN {$this->wrap($name, $driver)}";
        }

        foreach ($this->columns as $column) {
            $definition = $column->toSql($driver);

            // SQLite can only add a foreign key inline with the column.
            if ($column->foreign && $driver === 'sqlite') {
                $definition .= ' ' . $this->references($column, $driver);
            }

            $statements[] = "ALTER TABLE $table ADD COLUMN $definition";

            if ($column->foreign && $driver !== 'sqlite') {
                $statements[] = "ALTER TABLE $table ADD FOREIGN KEY ({$this->wrap($column->name, $driver)}) "
                    . $this->references($column, $driver);
            }
        }

        return [...$statements, ...$this->indexSql($driver)];
    }

    protected function references(Column $column, string $driver): string
    {
        return sprintf(
            'REFERENCES %s (%s)%s',
            $this->wrap($column->foreign['table'], $driver),
            $this->wrap($column->foreign['column'], $driver),
            $column->foreign['onDelete'] ? ' ON DELETE ' . $column->foreign['onDelete'] : '',
        );
    }

    protected function indexSql(string $driver): array
    {
        return array_map(fn (array $index) => sprintf(
            'CREATE %sINDEX %s ON %s (%s)',
            $index['type'] === 'UNIQUE' ? 'UNIQUE ' : '',
            $this->wrap($index['name'], $driver),
            $this->wrap($this->table, $driver),
            implode(', ', array_map(fn ($c) => $this->wrap($c, $driver), $index['columns'])),
        ), $this->indexes);
    }

    protected function wrap(string $name, string $driver): string
    {
        $q = $driver === 'mysql' ? '`' : '"';
        return $q . str_replace($q, $q . $q, $name) . $q;
    }

    protected function add(Column $column): Column
    {
        $this->columns[] = $column;
        return $column;
    }

    protected function addIndex(string $type, array $columns, ?string $name): void
    {
        $this->indexes[] = ['type' => $type, 'columns' => $columns, 'name' => $name ?? $this->indexName($type, $columns)];
    }

    protected function indexName(string $type, array $columns): string
    {
        return $this->table . '_' . implode('_', $columns) . '_' . ($type === 'UNIQUE' ? 'unique' : 'index');
    }
}
