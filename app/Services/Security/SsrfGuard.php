<?php

namespace App\Services\Security;

class SsrfGuard
{
    /**
     * CIDR blocks that must never be reachable (IPv4 + IPv6).
     * NOTE: We also use FILTER_FLAG_NO_PRIV_RANGE + NO_RES_RANGE as baseline.
     */
    private array $blockedCidrs = [
        // IPv4
        '0.0.0.0/8',
        '10.0.0.0/8',
        '127.0.0.0/8',
        '169.254.0.0/16',
        '172.16.0.0/12',
        '192.168.0.0/16',
        '100.64.0.0/10',
        '198.18.0.0/15',
        '192.0.0.0/24',
        '192.0.2.0/24',
        '198.51.100.0/24',
        '203.0.113.0/24',
        '224.0.0.0/4',
        '240.0.0.0/4',
        '255.255.255.255/32',

        // IPv6
        '::/128',
        '::1/128',
        'fc00::/7',
        'fe80::/10',
        'ff00::/8',
        '2001:db8::/32',
    ];

    public function validateUrl(string $url): array
    {
        $url = trim($url);
        $parts = @parse_url($url);

        if (! is_array($parts)) {
            throw new SsrfBlockedException('Invalid URL.');
        }

        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new SsrfBlockedException('Only http/https URLs are allowed.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new SsrfBlockedException('Credentials in URL are not allowed.');
        }

        $host = (string)($parts['host'] ?? '');
        if ($host === '') {
            throw new SsrfBlockedException('Missing host.');
        }

        $host = $this->normalizeHost($host);

        $hostLower = strtolower($host);
        if ($hostLower === 'localhost' || str_ends_with($hostLower, '.localhost')) {
            throw new SsrfBlockedException('Localhost is blocked.');
        }
        if (str_ends_with($hostLower, '.local')) {
            throw new SsrfBlockedException('Local domains are blocked.');
        }

        // If host is IP, validate directly
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $this->assertPublicIp($host);
            return [
                'scheme' => $scheme,
                'host' => $host,
                'ips' => [$host],
            ];
        }

        $ips = $this->resolveHostIps($host);
        if (count($ips) === 0) {
            throw new SsrfBlockedException('Host could not be resolved to an IP address.');
        }

        foreach ($ips as $ip) {
            $this->assertPublicIp($ip);
        }

        return [
            'scheme' => $scheme,
            'host' => $host,
            'ips' => $ips,
        ];
    }

    public function assertPublicIp(?string $ip): void
    {
        if (! is_string($ip) || $ip === '') {
            throw new SsrfBlockedException('Missing resolved IP.');
        }

        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new SsrfBlockedException('Invalid resolved IP.');
        }

        // Baseline block (private + reserved)
        $ok = (bool) filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );

        if (! $ok) {
            throw new SsrfBlockedException('Resolved IP is private/reserved.');
        }

        // Extra explicit CIDR blocks
        foreach ($this->blockedCidrs as $cidr) {
            if ($this->ipInCidr($ip, $cidr)) {
                throw new SsrfBlockedException('Resolved IP is blocked.');
            }
        }

        // Explicit metadata endpoints (redundant but loud)
        if ($ip === '169.254.169.254' || $ip === '169.254.170.2') {
            throw new SsrfBlockedException('Metadata IP is blocked.');
        }
    }

    private function normalizeHost(string $host): string
    {
        $host = trim($host);

        // Normalize IDN to ASCII if possible
        if (function_exists('idn_to_ascii')) {
            $ascii = @idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            if (is_string($ascii) && $ascii !== '') {
                $host = $ascii;
            }
        }

        return $host;
    }

    private function resolveHostIps(string $host): array
    {
        $records = @dns_get_record($host, DNS_A + DNS_AAAA);

        $ips = [];
        if (is_array($records)) {
            foreach ($records as $rec) {
                if (isset($rec['ip']) && is_string($rec['ip'])) {
                    $ips[] = $rec['ip'];
                }
                if (isset($rec['ipv6']) && is_string($rec['ipv6'])) {
                    $ips[] = $rec['ipv6'];
                }
            }
        }

        // Fallback (A records only)
        if (count($ips) === 0) {
            $fallback = @gethostbynamel($host);
            if (is_array($fallback)) {
                $ips = array_merge($ips, $fallback);
            }
        }

        // De-dupe
        $ips = array_values(array_unique(array_filter($ips, fn ($v) => is_string($v) && $v !== '')));

        return $ips;
    }

    private function ipInCidr(string $ip, string $cidr): bool
    {
        [$net, $maskBits] = array_pad(explode('/', $cidr, 2), 2, null);

        if (! is_string($net) || $net === '' || ! is_string($maskBits) || $maskBits === '') {
            return false;
        }

        if (! filter_var($ip, FILTER_VALIDATE_IP) || ! filter_var($net, FILTER_VALIDATE_IP)) {
            return false;
        }

        $ipBin = @inet_pton($ip);
        $netBin = @inet_pton($net);

        if (! is_string($ipBin) || ! is_string($netBin) || strlen($ipBin) !== strlen($netBin)) {
            return false;
        }

        $mask = (int) $maskBits;
        $len = strlen($ipBin); // 4 or 16
        $maxBits = $len * 8;

        if ($mask < 0 || $mask > $maxBits) {
            return false;
        }

        $fullBytes = intdiv($mask, 8);
        $remBits = $mask % 8;

        if ($fullBytes > 0) {
            if (substr($ipBin, 0, $fullBytes) !== substr($netBin, 0, $fullBytes)) {
                return false;
            }
        }

        if ($remBits === 0) {
            return true;
        }

        $maskByte = (0xFF << (8 - $remBits)) & 0xFF;

        $ipByte = ord($ipBin[$fullBytes]);
        $netByte = ord($netBin[$fullBytes]);

        return (($ipByte & $maskByte) === ($netByte & $maskByte));
    }
}
