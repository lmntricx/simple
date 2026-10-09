<?php

namespace Simple\Support;

/**
 * Small fake-data generator for factories and seeders (no dependencies).
 *
 *   fake()->name()  fake()->email()  fake()->sentence()  fake()->numberBetween(1, 10)
 *
 * Call fake()->seed(123) for repeatable data.
 */
final class Fake
{
    private const FIRST = ['Ada', 'Alan', 'Grace', 'Linus', 'Margaret', 'Dennis', 'Barbara', 'Ken', 'Frances', 'Tim',
        'Radia', 'Edsger', 'Katherine', 'Donald', 'Hedy', 'John', 'Shafi', 'Leslie', 'Sophie', 'Niklaus', 'Amara', 'Thabo',
        'Lerato', 'Sipho', 'Naledi', 'Kofi', 'Ama', 'Chen', 'Mei', 'Ravi', 'Priya', 'Mateo', 'Lucia', 'Yuki', 'Hana'];
    private const LAST = ['Lovelace', 'Turing', 'Hopper', 'Torvalds', 'Hamilton', 'Ritchie', 'Liskov', 'Thompson', 'Allen',
        'Berners-Lee', 'Perlman', 'Dijkstra', 'Johnson', 'Knuth', 'Lamarr', 'McCarthy', 'Goldwasser', 'Lamport', 'Wilson',
        'Wirth', 'Okafor', 'Mokoena', 'Dlamini', 'Nkosi', 'Mensah', 'Wang', 'Li', 'Patel', 'Sharma', 'Garcia', 'Tanaka'];
    private const WORDS = ['lorem', 'ipsum', 'dolor', 'sit', 'amet', 'consectetur', 'adipiscing', 'elit', 'sed', 'do',
        'eiusmod', 'tempor', 'incididunt', 'ut', 'labore', 'et', 'dolore', 'magna', 'aliqua', 'enim', 'ad', 'minim',
        'veniam', 'quis', 'nostrud', 'exercitation', 'ullamco', 'laboris', 'nisi', 'aliquip', 'ex', 'ea', 'commodo'];
    private const DOMAINS = ['example.com', 'example.org', 'example.net'];

    private static ?self $instance = null;
    private int $sequence = 0;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function seed(int $seed): self
    {
        mt_srand($seed);
        return $this;
    }

    public function firstName(): string
    {
        return $this->randomElement(self::FIRST);
    }

    public function lastName(): string
    {
        return $this->randomElement(self::LAST);
    }

    public function name(): string
    {
        return $this->firstName() . ' ' . $this->lastName();
    }

    /**
     * Unique within the process (a counter is included), on reserved example domains.
     */
    public function email(): string
    {
        $local = strtolower(preg_replace('/[^a-z]/i', '', $this->firstName()) . '.' . preg_replace('/[^a-z]/i', '', $this->lastName()));
        return $local . (++$this->sequence) . '@' . $this->randomElement(self::DOMAINS);
    }

    public function word(): string
    {
        return $this->randomElement(self::WORDS);
    }

    /** @return string[] */
    public function words(int $count = 3): array
    {
        return array_map(fn () => $this->word(), range(1, max(1, $count)));
    }

    public function sentence(int $words = 8): string
    {
        return ucfirst(implode(' ', $this->words($words))) . '.';
    }

    public function paragraph(int $sentences = 4): string
    {
        return implode(' ', array_map(fn () => $this->sentence($this->numberBetween(5, 12)), range(1, max(1, $sentences))));
    }

    public function slug(int $words = 3): string
    {
        return implode('-', $this->words($words)) . '-' . (++$this->sequence);
    }

    public function numberBetween(int $min = 0, int $max = 100): int
    {
        return mt_rand($min, $max);
    }

    public function boolean(int $chanceOfTrue = 50): bool
    {
        return mt_rand(1, 100) <= $chanceOfTrue;
    }

    /**
     * A datetime string between $from and $to (anything strtotime() understands).
     */
    public function dateTime(string $from = '-1 year', string $to = 'now'): string
    {
        return date('Y-m-d H:i:s', mt_rand(strtotime($from), strtotime($to)));
    }

    public function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    /**
     * @template T
     * @param T[] $items
     * @return T
     */
    public function randomElement(array $items): mixed
    {
        $items = array_values($items);
        return $items[mt_rand(0, count($items) - 1)];
    }
}
