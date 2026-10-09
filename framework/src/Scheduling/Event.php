<?php

namespace Simple\Scheduling;

use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * One scheduled task. Frequency methods set its cron expression:
 *   ->dailyAt('02:30')->weekdays()->withoutOverlapping()
 */
class Event
{
    /** [minute, hour, day-of-month, month, day-of-week] */
    private array $cron = ['*', '*', '*', '*', '*'];
    /** @var Closure[] */
    private array $filters = [];
    /** @var Closure[] */
    private array $rejects = [];
    private ?array $environments = null;
    private bool $withoutOverlapping = false;

    public function __construct(private Closure $callback, private string $description)
    {
    }

    // ---- Frequency ---------------------------------------------------------

    /**
     * Any cron expression, e.g. ->cron('0,30 9-17 * * 1-5') or ->cron('@daily').
     */
    public function cron(string $expression): static
    {
        // Stored as 5 fields so modifiers like ->weekdays() can still adjust one.
        $this->cron = preg_split('/\s+/', CronExpression::expand($expression));
        return $this;
    }

    public function everyMinute(): static
    {
        return $this->set(0, '*')->set(1, '*');
    }

    public function everyTwoMinutes(): static
    {
        return $this->set(0, '*/2');
    }

    public function everyFiveMinutes(): static
    {
        return $this->set(0, '*/5');
    }

    public function everyTenMinutes(): static
    {
        return $this->set(0, '*/10');
    }

    public function everyFifteenMinutes(): static
    {
        return $this->set(0, '*/15');
    }

    public function everyThirtyMinutes(): static
    {
        return $this->set(0, '0,30');
    }

    public function hourly(): static
    {
        return $this->hourlyAt(0);
    }

    public function hourlyAt(int $minute): static
    {
        return $this->set(0, (string) $minute)->set(1, '*');
    }

    public function everyTwoHours(): static
    {
        return $this->set(0, '0')->set(1, '*/2');
    }

    public function everySixHours(): static
    {
        return $this->set(0, '0')->set(1, '*/6');
    }

    public function daily(): static
    {
        return $this->dailyAt('00:00');
    }

    /**
     * @param string $time "HH:MM" (24-hour, app timezone)
     */
    public function dailyAt(string $time): static
    {
        [$hour, $minute] = self::parseTime($time);
        return $this->set(0, (string) $minute)->set(1, (string) $hour);
    }

    public function twiceDaily(int $firstHour = 1, int $secondHour = 13, int $minute = 0): static
    {
        return $this->set(0, (string) $minute)->set(1, "$firstHour,$secondHour");
    }

    public function weekly(): static
    {
        return $this->weeklyOn(0);
    }

    /**
     * @param int $day 0 = Sunday … 6 = Saturday
     */
    public function weeklyOn(int $day, string $time = '00:00'): static
    {
        return $this->dailyAt($time)->set(4, (string) $day);
    }

    public function monthly(): static
    {
        return $this->monthlyOn(1);
    }

    public function monthlyOn(int $day = 1, string $time = '00:00'): static
    {
        return $this->dailyAt($time)->set(2, (string) $day);
    }

    public function quarterly(): static
    {
        return $this->monthlyOn(1)->set(3, '1,4,7,10');
    }

    public function yearly(): static
    {
        return $this->monthlyOn(1)->set(3, '1');
    }

    public function weekdays(): static
    {
        return $this->set(4, '1-5');
    }

    public function weekends(): static
    {
        return $this->set(4, '0,6');
    }

    /**
     * @param int[] $days 0 = Sunday … 6 = Saturday
     */
    public function days(array $days): static
    {
        return $this->set(4, implode(',', $days));
    }

    // ---- Conditions --------------------------------------------------------

    /**
     * Only run when $callback returns true (checked each time it's due).
     */
    public function when(Closure $callback): static
    {
        $this->filters[] = $callback;
        return $this;
    }

    /**
     * Don't run when $callback returns true.
     */
    public function skip(Closure $callback): static
    {
        $this->rejects[] = $callback;
        return $this;
    }

    /**
     * Only run in these APP_ENV values, e.g. ->environments('production').
     */
    public function environments(string ...$environments): static
    {
        $this->environments = $environments;
        return $this;
    }

    /**
     * Skip a run if the previous one is still going.
     */
    public function withoutOverlapping(): static
    {
        $this->withoutOverlapping = true;
        return $this;
    }

    public function description(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    // ---- Running -----------------------------------------------------------

    public function getExpression(): string
    {
        return implode(' ', $this->cron);
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function preventsOverlapping(): bool
    {
        return $this->withoutOverlapping;
    }

    public function isDue(DateTimeInterface $now): bool
    {
        if ($this->environments !== null && !in_array(config('app.env'), $this->environments, true)) {
            return false;
        }

        return (new CronExpression($this->getExpression()))->isDue($now);
    }

    /**
     * when() / skip() conditions; evaluated only once the event is due.
     */
    public function filtersPass(): bool
    {
        foreach ($this->filters as $filter) {
            if (!app()->call($filter)) {
                return false;
            }
        }
        foreach ($this->rejects as $reject) {
            if (app()->call($reject)) {
                return false;
            }
        }
        return true;
    }

    public function nextRunDate(?DateTimeInterface $from = null): DateTimeImmutable
    {
        return (new CronExpression($this->getExpression()))->nextRunDate($from ?? new DateTimeImmutable());
    }

    public function run(): mixed
    {
        return ($this->callback)();
    }

    /**
     * Name of the lock file used by withoutOverlapping().
     */
    public function mutexName(): string
    {
        return 'schedule-' . sha1($this->getExpression() . '|' . $this->description);
    }

    private function set(int $field, string $value): static
    {
        $this->cron[$field] = $value;
        return $this;
    }

    /** @return array{int, int} */
    private static function parseTime(string $time): array
    {
        if (!preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $time, $m)) {
            throw new InvalidArgumentException("Invalid time [$time]; use HH:MM.");
        }
        return [(int) $m[1], (int) $m[2]];
    }
}
