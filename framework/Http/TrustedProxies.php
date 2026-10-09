<?php

namespace Simple\Http;

/**
 * Which proxies may tell us the real client IP via X-Forwarded-For.
 * Configured with TRUSTED_PROXIES (see config/app.php).
 */
final class TrustedProxies
{
    /** @var string[] IPs and CIDR ranges, or ['*'] for any */
    private static array $proxies = [];

    /**
     * @param string[] $proxies e.g. ['10.0.0.0/8', '192.168.1.10', '2001:db8::/32'] or ['*']
     */
    public static function set(array $proxies): void
    {
        self::$proxies = array_values(array_filter(array_map('trim', $proxies), 'strlen'));
    }

    public static function isTrusted(string $ip): bool
    {
        foreach (self::$proxies as $proxy) {
            if ($proxy === '*' || self::inRange($ip, $proxy)) {
                return true;
            }
        }

        return false;
    }

    /**
     * IPv4 and IPv6, single addresses or CIDR ranges.
     */
    public static function inRange(string $ip, string $range): bool
    {
        [$subnet, $bits] = array_pad(explode('/', $range, 2), 2, null);

        $ipBin = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);

        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }

        $maxBits = strlen($ipBin) * 8;
        $bits = $bits === null ? $maxBits : (int) $bits;

        if ($bits < 0 || $bits > $maxBits) {
            return false;
        }

        $bytes = intdiv($bits, 8);
        if (substr($ipBin, 0, $bytes) !== substr($subnetBin, 0, $bytes)) {
            return false;
        }

        $remaining = $bits % 8;
        if ($remaining === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remaining)) & 0xFF;

        return (ord($ipBin[$bytes]) & $mask) === (ord($subnetBin[$bytes]) & $mask);
    }
}
