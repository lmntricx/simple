<?php

namespace Simple\Validation;

use InvalidArgumentException;
use Simple\Database\Connection;
use Simple\Database\QueryBuilder;

/**
 * Rules are pipe-separated strings or arrays:
 *   ['email' => 'required|email|max:255|unique:users,email', 'age' => ['nullable', 'integer', 'min:18']]
 *
 * Available: required, nullable, sometimes, string, integer, numeric, boolean, array, email, url,
 *            min:n, max:n, between:a,b, in:a,b,c, confirmed, regex:/x/, unique:table,column[,ignoreId]
 *
 * min/max/between compare length for strings, count for arrays, value for numbers.
 */
class Validator
{
    protected array $errors = [];

    public function __construct(protected array $data, protected array $rules)
    {
    }

    public static function make(array $data, array $rules): static
    {
        return new static($data, $rules);
    }

    public function fails(): bool
    {
        return !$this->passes();
    }

    public function passes(): bool
    {
        $this->errors = [];

        foreach ($this->rules as $field => $rules) {
            $rules = is_string($rules) ? explode('|', $rules) : $rules;
            $present = array_key_exists($field, $this->data);
            $value = $this->data[$field] ?? null;

            if (in_array('sometimes', $rules, true) && !$present) {
                continue;
            }

            if ($this->isEmpty($value)) {
                if (in_array('required', $rules, true)) {
                    $this->addError($field, "The $field field is required.");
                }
                continue; // Nothing else to check on an empty optional value.
            }

            foreach ($rules as $rule) {
                [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);

                if (in_array($name, ['required', 'nullable', 'sometimes'], true)) {
                    continue;
                }

                $message = $this->check($field, $value, $name, $param, $rules);
                if ($message !== null) {
                    $this->addError($field, $message);
                    break; // One message per field is enough.
                }
            }
        }

        return $this->errors === [];
    }

    /**
     * @return array<string, string[]>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Throws on failure; otherwise returns only the fields that have rules.
     */
    public function validate(): array
    {
        if ($this->fails()) {
            throw new ValidationException($this->errors);
        }

        return array_intersect_key($this->data, $this->rules);
    }

    protected function check(string $field, mixed $value, string $rule, ?string $param, array $rules): ?string
    {
        $numeric = in_array('integer', $rules, true) || in_array('numeric', $rules, true);
        $size = fn () => match (true) {
            is_array($value) => count($value),
            $numeric && is_numeric($value) => $value + 0,
            default => mb_strlen((string) $value),
        };
        $unit = is_array($value) ? ' items' : ($numeric ? '' : ' characters');

        return match ($rule) {
            'string' => is_string($value) ? null : "The $field must be a string.",
            'integer' => filter_var($value, FILTER_VALIDATE_INT) !== false ? null : "The $field must be an integer.",
            'numeric' => is_numeric($value) ? null : "The $field must be a number.",
            'boolean' => in_array($value, [true, false, 0, 1, '0', '1', 'true', 'false'], true) ? null : "The $field must be true or false.",
            'array' => is_array($value) ? null : "The $field must be an array.",
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : "The $field must be a valid email address.",
            'url' => filter_var($value, FILTER_VALIDATE_URL) ? null : "The $field must be a valid URL.",
            'min' => $size() >= (float) $param ? null : "The $field must be at least $param$unit.",
            'max' => $size() <= (float) $param ? null : "The $field may not be greater than $param$unit.",
            'between' => (function () use ($size, $param, $field, $unit) {
                [$min, $max] = array_map('floatval', explode(',', (string) $param));
                return $size() >= $min && $size() <= $max ? null : "The $field must be between $min and $max$unit.";
            })(),
            'in' => in_array((string) $value, explode(',', (string) $param), true) ? null : "The selected $field is invalid.",
            'confirmed' => ($this->data[$field . '_confirmation'] ?? null) === $value ? null : "The $field confirmation does not match.",
            'regex' => preg_match((string) $param, (string) $value) ? null : "The $field format is invalid.",
            'unique' => $this->isUnique($field, $value, (string) $param) ? null : "The $field has already been taken.",
            default => throw new InvalidArgumentException("Unknown validation rule [$rule]."),
        };
    }

    protected function isUnique(string $field, mixed $value, string $param): bool
    {
        [$table, $column, $ignoreId] = array_pad(explode(',', $param), 3, null);

        $query = (new QueryBuilder(Connection::get(), $table))->where($column ?: $field, $value);
        if ($ignoreId !== null && $ignoreId !== '') {
            $query->where('id', '!=', $ignoreId);
        }

        return !$query->exists();
    }

    protected function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    protected function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }
}
