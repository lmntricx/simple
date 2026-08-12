<?php

namespace app\Models;

use app\ORM\Model as ORMModel;

class BaseModel extends ORMModel
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    public static function getAll(string $table = null): array
    {
        $instance = new static();

        if ($table) {
            return $instance->newQuery()
                ->table($table)
                ->get();
        }

        return static::all();
    }

    public function findId(mixed $id, string $table = null): ?self
    {
        $builder = static::query();

        if ($table) {
            $builder->table($table);
        }

        $row = $builder
            ->where('id', '=', $id)
            ->first();

        return $row ? new static($row) : null;
    }

    public function findByColumn(string $column, mixed $value): ?self
    {
        return static::findBy($column, $value);
    }

    public function insert(array $data, string $table = null): bool
    {
        $builder = $this->newQuery();

        if ($table) {
            $builder->table($table);
        }

        return $builder->insert($data);
    }

    public function updateRecord(array $data, mixed $id = null, string $table = null): bool
    {
        $id = $id ?? $this->getAttribute($this->primaryKey);

        $builder = $this->newQuery();

        if ($table) {
            $builder->table($table);
        }

        return $builder
            ->where($this->primaryKey, '=', $id)
            ->update($data);
    }
}