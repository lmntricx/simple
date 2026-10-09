<?php

namespace Simple\Tests\Unit\Scheduling;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Simple\Scheduling\CronExpression;

class CronExpressionTest extends TestCase
{
    public static function dueCases(): array
    {
        // [expression, time (2026-10-12 is a Monday), due?]
        return [
            'every minute' => ['* * * * *', '2026-10-12 13:37', true],
            'every 15: on' => ['*/15 * * * *', '2026-10-12 13:45', true],
            'every 15: off' => ['*/15 * * * *', '2026-10-12 13:46', false],
            'hourly' => ['0 * * * *', '2026-10-12 09:00', true],
            'daily at 02:30' => ['30 2 * * *', '2026-10-12 02:30', true],
            'daily at 02:30, wrong hour' => ['30 2 * * *', '2026-10-12 03:30', false],
            'range of hours' => ['0 9-17 * * *', '2026-10-12 17:00', true],
            'range of hours, outside' => ['0 9-17 * * *', '2026-10-12 18:00', false],
            'stepped range' => ['0-30/10 * * * *', '2026-10-12 10:20', true],
            'stepped range, past end' => ['0-30/10 * * * *', '2026-10-12 10:40', false],
            'start/step' => ['5/20 * * * *', '2026-10-12 10:45', true],
            'list' => ['0 8,12,18 * * *', '2026-10-12 12:00', true],
            'weekdays on Monday' => ['0 9 * * 1-5', '2026-10-12 09:00', true],
            'weekdays on Sunday' => ['0 9 * * 1-5', '2026-10-11 09:00', false],
            'Sunday as 0' => ['0 0 * * 0', '2026-10-11 00:00', true],
            'Sunday as 7' => ['0 0 * * 7', '2026-10-11 00:00', true],
            'day names' => ['0 0 * * MON,FRI', '2026-10-16 00:00', true],
            'month names' => ['0 0 1 JAN *', '2026-01-01 00:00', true],
            'month names, other month' => ['0 0 1 JAN *', '2026-02-01 00:00', false],
            'first of month' => ['0 0 1 * *', '2026-11-01 00:00', true],
            'dom OR dow: matches dom' => ['0 0 13 * FRI', '2026-10-13 00:00', true],   // a Tuesday the 13th
            'dom OR dow: matches dow' => ['0 0 13 * FRI', '2026-10-16 00:00', true],   // Friday the 16th
            'dom OR dow: neither' => ['0 0 13 * FRI', '2026-10-14 00:00', false],
            '@daily' => ['@daily', '2026-10-12 00:00', true],
            '@hourly off' => ['@hourly', '2026-10-12 00:01', false],
        ];
    }

    #[DataProvider('dueCases')]
    public function test_is_due(string $expression, string $time, bool $due): void
    {
        $this->assertSame($due, (new CronExpression($expression))->isDue(new DateTimeImmutable($time)));
    }

    public static function nextCases(): array
    {
        return [
            ['*/15 * * * *', '2026-10-12 13:37:42', '2026-10-12 13:45'],
            ['0 * * * *', '2026-10-12 13:00:00', '2026-10-12 14:00'],
            ['30 2 * * *', '2026-10-12 02:30:00', '2026-10-13 02:30'],
            ['0 9 * * 1-5', '2026-10-16 10:00:00', '2026-10-19 09:00'],   // Fri -> Mon
            ['0 0 1 * *', '2026-12-15 00:00:00', '2027-01-01 00:00'],      // year rollover
            ['0 0 29 2 *', '2026-03-01 00:00:00', '2028-02-29 00:00'],     // next leap day
        ];
    }

    #[DataProvider('nextCases')]
    public function test_next_run_date(string $expression, string $from, string $expected): void
    {
        $next = (new CronExpression($expression))->nextRunDate(new DateTimeImmutable($from));
        $this->assertSame($expected, $next->format('Y-m-d H:i'));
    }

    public static function invalid(): array
    {
        return [['* * * *'], ['60 * * * *'], ['* 24 * * *'], ['* * 0 * *'], ['* * * 13 *'], ['* * * * 8'],
            ['*/0 * * * *'], ['5-1 * * * *'], ['abc * * * *'], ['@sometimes']];
    }

    #[DataProvider('invalid')]
    public function test_invalid_expressions_are_rejected(string $expression): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CronExpression($expression);
    }

    public function test_impossible_date_is_reported(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new CronExpression('0 0 31 2 *'))->nextRunDate(new DateTimeImmutable('2026-01-01'));
    }
}
