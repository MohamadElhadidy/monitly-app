<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SafeMonitorUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            $fail('The URL is required.');
            return;
        }

        $url = trim($value);

        if (strlen($url) > 2048) {
            $fail('The URL is too long.');
            return;
        }

        $parts = @parse_url($url);
        if (! is_array($parts)) {
            $fail('The URL format is invalid.');
            return;
        }

        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true)) {
            $fail('Only http and https URLs are allowed.');
            return;
        }

        if (isset($parts['fragment'])) {
            $fail('URL fragments are not allowed.');
            return;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            $fail('Credentials in the URL are not allowed.');
            return;
        }

        $host = (string)($parts['host'] ?? '');
        if ($host === '') {
            $fail('The URL must include a valid host.');
            return;
        }

        // Normalize IDN to ASCII if possible
        if (function_exists('idn_to_ascii')) {
            $ascii = @idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            if (is_string($ascii) && $ascii !== '') {
                $host = $ascii;
            }
        }

        $hostLower = strtolower($host);

        // Hard blocks
        if ($hostLower === 'localhost' || str_ends_with($hostLower, '.localhost')) {
            $fail('Localhost is not allowed.');
            return;
        }

        if (str_ends_with($hostLower, '.local')) {
            $fail('Local network domains are not allowed.');
            return;
        }

        // Validate port if provided
        if (isset($parts['port'])) {
            $port = (int) $parts['port'];
            if ($port < 1 || $port > 65535) {
                $fail('The URL port is invalid.');
                return;
            }
        }

        // If host is an IP, block private/reserved ranges.
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                $fail('Private or reserved IP addresses are not allowed.');
                return;
            }
            return;
        }

        // DNS resolve host and block private/reserved results.
        // This helps reduce SSRF risk from internal DNS and direct private targets.
        $records = @dns_get_record($host, DNS_A + DNS_AAAA);
        if (! is_array($records) || count($records) === 0) {
            $fail('The host could not be resolved (no A/AAAA record).');
            return;
        }

        $ips = [];
        foreach ($records as $rec) {
            if (isset($rec['ip']) && is_string($rec['ip'])) {
                $ips[] = $rec['ip'];
            }
            if (isset($rec['ipv6']) && is_string($rec['ipv6'])) {
                $ips[] = $rec['ipv6'];
            }
        }

        if (count($ips) === 0) {
            $fail('The host could not be resolved to an IP address.');
            return;
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                $fail('The host resolves to a private or reserved IP address.');
                return;
            }
        }
    }
}
