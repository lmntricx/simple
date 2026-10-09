<?php

namespace Simple\Tests\Unit\Validation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Simple\Validation\ValidationException;
use Simple\Validation\Validator;

class ValidatorTest extends TestCase
{
    public static function cases(): array
    {
        return [
            // [rules, value, passes]
            'required present' => ['required', 'x', true],
            'required empty string' => ['required', '', false],
            'required empty array' => ['required', [], false],
            'optional empty skips other rules' => ['email', '', true],
            'string' => ['string', 'x', true],
            'string rejects array' => ['string', ['x'], false],
            'integer' => ['integer', '42', true],
            'integer rejects float' => ['integer', '4.2', false],
            'numeric' => ['numeric', '4.2', true],
            'boolean' => ['boolean', 'false', true],
            'boolean rejects yes' => ['boolean', 'yes', false],
            'array' => ['array', [1], true],
            'email' => ['email', 'ada@x.io', true],
            'email invalid' => ['email', 'ada@', false],
            'url' => ['url', 'https://x.io/a', true],
            'url invalid' => ['url', 'x.io', false],
            'min string length' => ['string|min:3', 'ab', false],
            'max string length (multibyte)' => ['string|max:3', 'añé', true],
            'min number' => ['integer|min:18', '17', false],
            'max number' => ['numeric|max:10', '10', true],
            'min array count' => ['array|min:2', [1], false],
            'between' => ['integer|between:1,5', '3', true],
            'between out' => ['integer|between:1,5', '6', false],
            'in' => ['in:a,b,c', 'b', true],
            'in rejects' => ['in:a,b,c', 'd', false],
            'regex' => ['regex:/^[a-z]+$/', 'abc', true],
            'regex rejects' => ['regex:/^[a-z]+$/', 'ab1', false],
        ];
    }

    #[DataProvider('cases')]
    public function test_rules(string $rules, mixed $value, bool $passes): void
    {
        $this->assertSame($passes, Validator::make(['field' => $value], ['field' => $rules])->passes());
    }

    public function test_validate_returns_only_fields_with_rules(): void
    {
        $data = Validator::make(['name' => 'Ada', 'is_admin' => true], ['name' => 'required|string'])->validate();
        $this->assertSame(['name' => 'Ada'], $data);
    }

    public function test_validate_throws_422_with_errors_per_field(): void
    {
        try {
            Validator::make(['email' => 'nope'], ['email' => 'required|email', 'name' => 'required'])->validate();
            $this->fail('expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame(422, $e->status);
            $this->assertSame(['email', 'name'], array_keys($e->errors));
        }
    }

    public function test_sometimes_only_validates_present_fields(): void
    {
        $this->assertTrue(Validator::make([], ['name' => 'sometimes|required|string'])->passes());
        $this->assertFalse(Validator::make(['name' => ''], ['name' => 'sometimes|required|string'])->passes());
    }

    public function test_confirmed(): void
    {
        $rules = ['password' => 'confirmed'];
        $this->assertTrue(Validator::make(['password' => 'x', 'password_confirmation' => 'x'], $rules)->passes());
        $this->assertFalse(Validator::make(['password' => 'x', 'password_confirmation' => 'y'], $rules)->passes());
    }

    public function test_unknown_rule_is_a_programming_error(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Validator::make(['x' => 'y'], ['x' => 'no_such_rule'])->passes();
    }
}
