<?php

namespace Simple\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Simple\Http\Request;
use Simple\Http\TrustedProxies;

class TrustedProxiesTest extends TestCase
{
    protected function tearDown(): void
    {
        TrustedProxies::set([]);
    }

    private function ip(string $remote, ?string $forwardedFor = null): ?string
    {
        $headers = $forwardedFor === null ? [] : ['x-forwarded-for' => $forwardedFor];
        return (new Request('GET', '/', [], [], $headers, [], ['REMOTE_ADDR' => $remote]))->ip();
    }

    public function test_without_trusted_proxies_forwarded_for_is_ignored(): void
    {
        $this->assertSame('1.2.3.4', $this->ip('1.2.3.4', '9.9.9.9'));
    }

    public static function chains(): array
    {
        return [
            'client behind load balancer' => ['10.1.2.3', '203.0.113.5', '203.0.113.5'],
            'client prepends a fake IP' => ['10.1.2.3', '6.6.6.6, 203.0.113.5', '203.0.113.5'],
            'chain of trusted proxies' => ['10.1.2.3', '203.0.113.5, 10.9.9.9', '203.0.113.5'],
            'untrusted sender' => ['8.8.8.8', '1.1.1.1', '8.8.8.8'],
            'garbage header' => ['10.1.2.3', 'not-an-ip', '10.1.2.3'],
            'no header' => ['10.1.2.3', null, '10.1.2.3'],
        ];
    }

    #[DataProvider('chains')]
    public function test_client_ip_behind_trusted_proxy(string $remote, ?string $forwardedFor, string $expected): void
    {
        TrustedProxies::set(['10.0.0.0/8']);
        $this->assertSame($expected, $this->ip($remote, $forwardedFor));
    }

    public function test_wildcard_trusts_any_proxy(): void
    {
        TrustedProxies::set(['*']);
        $this->assertSame('203.0.113.5', $this->ip('172.16.0.1', '203.0.113.5'));
    }

    public static function ranges(): array
    {
        return [
            ['192.168.15.255', '192.168.0.0/20', true],
            ['192.168.16.0', '192.168.0.0/20', false],
            ['10.0.0.1', '10.0.0.1', true],
            ['10.0.0.2', '10.0.0.1', false],
            ['2001:db8::1', '2001:db8::/32', true],
            ['2001:db9::1', '2001:db8::/32', false],
            ['::1', '127.0.0.0/8', false],
            ['not-an-ip', '10.0.0.0/8', false],
            ['10.0.0.1', '10.0.0.0/33', false],
        ];
    }

    #[DataProvider('ranges')]
    public function test_cidr_matching(string $ip, string $range, bool $expected): void
    {
        $this->assertSame($expected, TrustedProxies::inRange($ip, $range));
    }

    public function test_is_secure_honours_forwarded_proto_only_from_trusted_proxies(): void
    {
        $request = fn (string $remote) => new Request('GET', '/', [], [], ['x-forwarded-proto' => 'https'], [], ['REMOTE_ADDR' => $remote]);

        $this->assertFalse($request('10.0.0.1')->isSecure());

        TrustedProxies::set(['10.0.0.0/8']);
        $this->assertTrue($request('10.0.0.1')->isSecure());
        $this->assertFalse($request('8.8.8.8')->isSecure());
    }
}
