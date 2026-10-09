<?php

namespace Simple\Scheduling;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Standard 5-field cron expressions: minute hour day-of-month month day-of-week.
 *
 * Fields accept *, numbers, ranges (1-5), steps (0-30/10, or a star then /15 for every 15), lists (1,15,30),
 * and names for months (JAN-DEC) and weekdays (SUN-SAT; Sunday is 0 or 7).
 * Shortcuts: @yearly @monthly @weekly @daily @hourly.
 *
 * As in cron, when both day-of-month and day-of-week are restricted, a day
 * matches if EITHER does ("0 0 1 * MON" = the 1st of the month and every Monday).
 */
final class CronExpression
{
    private const SHORTCUTS = [
        '@yearly' => '0 0 1 1 *',
        '@annually' => '0 0 1 1 *',
        '@monthly' => '0 0 1 * *',
        '@weekly' => '0 0 * * 0',
        '@daily' => '0 0 * * *',
        '@midnight' => '0 0 * * *',
        '@hourly' => '0 * * * *',
    ];

    private const MONTHS = ['JAN' => 1, 'FEB' => 2, 'MAR' => 3, 'APR' => 4, 'MAY' => 5, 'JUN' => 6,
        'JUL' => 7, 'AUG' => 8, 'SEP' => 9, 'OCT' => 10, 'NOV' => 11, 'DEC' => 12];
    private const DAYS = ['SUN' => 0, 'MON' => 1, 'TUE' => 2, 'WED' => 3, 'THU' => 4, 'FRI' => 5, 'SAT' => 6];

    /** @var array<int, array<int, true>> allowed values per field */
    private array $fields;
    private bool $domRestricted;
    private bool $dowRestricted;

    public function __construct(public readonly string $expression)
    {
        $parts = preg_split('/\s+/', self::shortcut($expression));

        if (count($parts) !== 5) {
            throw new InvalidArgumentException("Invalid cron expression [$expression]: expected 5 fields.");
        }

        $this->fields = [
            self::parseField($parts[0], 0, 59, [], $expression),
            self::parseField($parts[1], 0, 23, [], $expression),
            self::parseField($parts[2], 1, 31, [], $expression),
            self::parseField($parts[3], 1, 12, self::MONTHS, $expression),
            self::parseField($parts[4], 0, 7, self::DAYS, $expression),
        ];

        // 7 is also Sunday.
        if (isset($this->fields[4][7])) {
            $this->fields[4][0] = true;
            unset($this->fields[4][7]);
        }

        $this->domRestricted = !str_starts_with($parts[2], '*');
        $this->dowRestricted = !str_starts_with($parts[4], '*');
    }

    /**
     * Validate and return the 5-field form ("@daily" => "0 0 * * *").
     */
    public static function expand(string $expression): string
    {
        new self($expression);
        return self::shortcut($expression);
    }

    private static function shortcut(string $expression): string
    {
        return self::SHORTCUTS[strtolower(trim($expression))] ?? trim(preg_replace('/\s+/', ' ', $expression));
    }

    public static function isValid(string $expression): bool
    {
        try {
            new self($expression);
            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Whether the expression matches $time (to the minute).
     */
    public function isDue(DateTimeInterface $time): bool
    {
        return isset($this->fields[0][(int) $time->format('i')])
            && isset($this->fields[1][(int) $time->format('G')])
            && isset($this->fields[3][(int) $time->format('n')])
            && $this->dayMatches($time);
    }

    /**
     * The first matching minute after $from.
     */
    public function nextRunDate(DateTimeInterface $from): DateTimeImmutable
    {
        $time = DateTimeImmutable::createFromInterface($from)->setTime((int) $from->format('G'), (int) $from->format('i'))->modify('+1 minute');
        $limit = $time->modify('+5 years');

        while ($time < $limit) {
            if (!isset($this->fields[3][(int) $time->format('n')])) {
                $time = $time->modify('first day of next month')->setTime(0, 0);
                continue;
            }
            if (!$this->dayMatches($time)) {
                $time = $time->modify('+1 day')->setTime(0, 0);
                continue;
            }
            if (!isset($this->fields[1][(int) $time->format('G')])) {
                $time = $time->setTime((int) $time->format('G'), 0)->modify('+1 hour');
                continue;
            }
            if (!isset($this->fields[0][(int) $time->format('i')])) {
                $time = $time->modify('+1 minute');
                continue;
            }
            return $time;
        }

        throw new InvalidArgumentException("Cron expression [{$this->expression}] never matches.");
    }

    private function dayMatches(DateTimeInterface $time): bool
    {
        $dom = isset($this->fields[2][(int) $time->format('j')]);
        $dow = isset($this->fields[4][(int) $time->format('w')]);

        return $this->domRestricted && $this->dowRestricted ? ($dom || $dow) : ($dom && $dow);
    }

    /**
     * @return array<int, true>
     */
    private static function parseField(string $field, int $min, int $max, array $names, string $expression): array
    {
        $values = [];
        $field = strtoupper($field);
        foreach ($names as $name => $number) {
            $field = str_replace($name, (string) $number, $field);
        }

        foreach (explode(',', $field) as $part) {
            if (!preg_match('/^(\*|\d+(?:-\d+)?)(?:\/(\d+))?$/', $part, $m)) {
                throw new InvalidArgumentException("Invalid cron expression [$expression]: bad field [$part].");
            }

            $step = isset($m[2]) ? (int) $m[2] : 1;

            if ($m[1] === '*') {
                [$from, $to] = [$min, $max];
            } elseif (str_contains($m[1], '-')) {
                [$from, $to] = array_map('intval', explode('-', $m[1]));
            } else {
                $from = (int) $m[1];
                $to = isset($m[2]) ? $max : $from; // "5/15" = from 5 to the end, every 15
            }

            if ($step < 1 || $from < $min || $to > $max || $from > $to) {
                throw new InvalidArgumentException("Invalid cron expression [$expression]: [$part] is outside $min-$max.");
            }

            for ($v = $from; $v <= $to; $v += $step) {
                $values[$v] = true;
            }
        }

        return $values;
    }
}
