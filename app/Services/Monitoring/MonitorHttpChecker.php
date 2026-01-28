<?php

namespace App\Services\Monitoring;

use App\Services\Security\SsrfBlockedException;
use App\Services\Security\SsrfGuard;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use GuzzleHttp\TransferStats;
use Throwable;

class MonitorHttpChecker
{
    private Client $client;

    public function __construct(
        private readonly SsrfGuard $ssrfGuard
    ) {
        $this->client = new Client([
            'http_errors' => false,
            'verify' => true,
        ]);
    }

    public function check(string $url): CheckResult
    {
        $maxRedirects = (int) config('monitly.http.max_redirects', 3);
        $timeout = (int) config('monitly.http.timeout', 10);
        $connectTimeout = (int) config('monitly.http.connect_timeout', 5);
        $ua = (string) config('monitly.http.user_agent', 'MonitlyBot/1.0');

        $redirects = [];
        $current = $url;
        $resolvedIp = null;
        $resolvedHost = null;

        for ($i = 0; $i <= $maxRedirects; $i++) {
            // Validate target (scheme/host + DNS -> public IPs)
            $validated = $this->ssrfGuard->validateUrl($current);
            $resolvedHost = $validated['host'] ?? $resolvedHost;

            $primaryIp = null;
            $start = microtime(true);

            try {
                $response = $this->client->request('GET', $current, [
                    'allow_redirects' => false,
                    'timeout' => $timeout,
                    'connect_timeout' => $connectTimeout,
                    'headers' => [
                        'User-Agent' => $ua,
                        'Accept' => '*/*',
                    ],
                    'sink' => fopen('php://temp', 'w+'),
                    'on_stats' => function (TransferStats $stats) use (&$primaryIp) {
                        $hs = $stats->getHandlerStats();
                        if (is_array($hs)) {
                            $primaryIp = $hs['primary_ip'] ?? ($hs['primaryIp'] ?? null);
                        }
                    },
                ]);

                $ms = (int) round((microtime(true) - $start) * 1000);

                if (is_string($primaryIp) && $primaryIp !== '') {
                    // Protect against DNS rebinding / unexpected route
                    $this->ssrfGuard->assertPublicIp($primaryIp);
                    $resolvedIp = $primaryIp;
                }

                $status = (int) $response->getStatusCode();

                // Handle redirects manually (validate every hop)
                if ($status >= 300 && $status < 400) {
                    $location = $response->getHeaderLine('Location');
                    if ($location === '') {
                        return new CheckResult(
                            ok: false,
                            statusCode: $status,
                            responseTimeMs: $ms,
                            errorCode: 'REDIRECT_NO_LOCATION',
                            errorMessage: 'Redirect response without Location header.',
                            resolvedIp: $resolvedIp,
                            resolvedHost: $resolvedHost,
                            finalUrl: $current,
                            meta: [
                                'redirects' => $redirects,
                                'attempt' => $i,
                            ],
                        );
                    }

                    $next = $this->resolveRedirectUrl($current, $location);

                    $redirects[] = [
                        'from' => $current,
                        'to' => $next,
                        'status' => $status,
                    ];

                    $current = $next;
                    continue;
                }

                $ok = ($status >= 200 && $status <= 399);

                return new CheckResult(
                    ok: $ok,
                    statusCode: $status,
                    responseTimeMs: $ms,
                    errorCode: $ok ? null : 'HTTP_STATUS',
                    errorMessage: $ok ? null : "Unexpected HTTP status {$status}.",
                    resolvedIp: $resolvedIp,
                    resolvedHost: $resolvedHost,
                    finalUrl: $current,
                    meta: [
                        'redirects' => $redirects,
                        'attempt' => $i,
                    ],
                );
            } catch (SsrfBlockedException $e) {
                throw $e;
            } catch (ConnectException $e) {
                $ms = (int) round((microtime(true) - $start) * 1000);
                return new CheckResult(
                    ok: false,
                    statusCode: null,
                    responseTimeMs: $ms,
                    errorCode: 'CONNECT',
                    errorMessage: $this->truncate((string) $e->getMessage()),
                    resolvedIp: $resolvedIp,
                    resolvedHost: $resolvedHost,
                    finalUrl: $current,
                    meta: [
                        'redirects' => $redirects,
                        'attempt' => $i,
                        'exception' => get_class($e),
                    ],
                );
            } catch (RequestException $e) {
                $ms = (int) round((microtime(true) - $start) * 1000);
                $status = $e->getResponse() ? (int) $e->getResponse()->getStatusCode() : null;

                return new CheckResult(
                    ok: false,
                    statusCode: $status,
                    responseTimeMs: $ms,
                    errorCode: 'REQUEST',
                    errorMessage: $this->truncate((string) $e->getMessage()),
                    resolvedIp: $resolvedIp,
                    resolvedHost: $resolvedHost,
                    finalUrl: $current,
                    meta: [
                        'redirects' => $redirects,
                        'attempt' => $i,
                        'exception' => get_class($e),
                    ],
                );
            } catch (Throwable $e) {
                $ms = (int) round((microtime(true) - $start) * 1000);
                return new CheckResult(
                    ok: false,
                    statusCode: null,
                    responseTimeMs: $ms,
                    errorCode: 'EXCEPTION',
                    errorMessage: $this->truncate((string) $e->getMessage()),
                    resolvedIp: $resolvedIp,
                    resolvedHost: $resolvedHost,
                    finalUrl: $current,
                    meta: [
                        'redirects' => $redirects,
                        'attempt' => $i,
                        'exception' => get_class($e),
                    ],
                );
            }
        }

        // Exceeded redirect limit
        return new CheckResult(
            ok: false,
            statusCode: null,
            responseTimeMs: null,
            errorCode: 'TOO_MANY_REDIRECTS',
            errorMessage: 'Too many redirects.',
            resolvedIp: $resolvedIp,
            resolvedHost: $resolvedHost,
            finalUrl: $current,
            meta: [
                'redirects' => $redirects,
            ],
        );
    }

    private function resolveRedirectUrl(string $currentUrl, string $location): string
    {
        $base = new Uri($currentUrl);
        $loc = new Uri($location);

        $resolved = UriResolver::resolve($base, $loc);
        $next = (string) $resolved;

        // Force scheme re-validation (block mailto:, file:, etc.)
        $parts = @parse_url($next);
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new SsrfBlockedException('Redirected to a non-http(s) scheme.');
        }

        return $next;
    }

    private function truncate(string $msg): string
    {
        $max = (int) config('monitly.http.max_error_message_len', 500);
        $msg = trim($msg);
        if ($msg === '') return 'Request failed.';
        if (mb_strlen($msg) <= $max) return $msg;
        return mb_substr($msg, 0, $max);
    }
}
