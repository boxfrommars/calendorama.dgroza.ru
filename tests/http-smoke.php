<?php

declare(strict_types=1);

final readonly class SmokeResponse
{
    /** @param array<string, string> $headers */
    public function __construct(
        public int $status,
        public array $headers,
        public string $body,
    ) {
    }
}

function request(string $path, string $method = 'GET', ?string $content = null): SmokeResponse
{
    $baseUrl = rtrim(getenv('CALENDORAMA_BASE_URL') ?: 'http://web', '/');
    $options = [
        'method' => $method,
        'ignore_errors' => true,
        'timeout' => 10,
    ];
    if ($content !== null) {
        $options['header'] = "Content-Type: application/x-www-form-urlencoded\r\n";
        $options['content'] = $content;
    }

    $http_response_header = [];
    $body = file_get_contents(
        $baseUrl . $path,
        false,
        stream_context_create(['http' => $options]),
    );
    if ($body === false || $http_response_header === []) {
        throw new RuntimeException('HTTP request failed: ' . $path);
    }

    if (preg_match('/^HTTP\/\S+\s+([0-9]{3})\b/', $http_response_header[0], $match) !== 1) {
        throw new RuntimeException('Invalid HTTP status line: ' . $http_response_header[0]);
    }

    $headers = [];
    foreach (array_slice($http_response_header, 1) as $header) {
        $separator = strpos($header, ':');
        if ($separator === false) {
            continue;
        }

        $name = strtolower(trim(substr($header, 0, $separator)));
        $value = trim(substr($header, $separator + 1));
        $headers[$name] = isset($headers[$name]) ? $headers[$name] . ', ' . $value : $value;
    }

    return new SmokeResponse((int) $match[1], $headers, $body);
}

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function versionedAssetUrl(string $html, string $asset): string
{
    $pattern = '~["\'](/' . preg_quote($asset, '~') . '\?v=[a-f0-9]{12})["\']~';
    if (preg_match($pattern, $html, $match) !== 1) {
        throw new RuntimeException('Versioned URL is missing for ' . $asset . '.');
    }

    return $match[1];
}

$index = request('/');
check($index->status === 200, 'Index did not return 200.');
check(str_contains($index->headers['content-security-policy'] ?? '', "default-src 'self'"), 'Index CSP is missing.');
check(!isset($index->headers['permissions-policy']), 'Index must not send an unnecessary Permissions-Policy.');
check(!str_contains($index->headers['cache-control'] ?? '', 'immutable'), 'Index must not be immutable.');
$styleUrl = versionedAssetUrl($index->body, 'style.css');
$scriptUrl = versionedAssetUrl($index->body, 'script.js');

$calendar = request('/handler.php?month=3&year=2010');
check($calendar->status === 200, 'Calendar did not return 200.');
check(str_contains($calendar->body, 'data-month="3"'), 'Calendar response is incorrect.');
check(str_contains($calendar->headers['cache-control'] ?? '', 'no-store'), 'Calendar must not be cached.');

$invalid = request('/handler.php?month=13&year=2010');
check($invalid->status === 400, 'Invalid calendar request did not return 400.');
check($invalid->body === 'Invalid calendar date.', 'Invalid calendar error changed.');
check(str_contains($invalid->headers['cache-control'] ?? '', 'no-store'), 'Invalid calendar response must not be cached.');

$methodNotAllowed = request('/handler.php', 'POST', 'month=3&year=2010');
check($methodNotAllowed->status === 405, 'POST calendar request did not return 405.');
check(($methodNotAllowed->headers['allow'] ?? '') === 'GET', 'Handler Allow header is incorrect.');
check($methodNotAllowed->body === 'Method Not Allowed.', 'Method error body is incorrect.');
check(str_contains($methodNotAllowed->headers['cache-control'] ?? '', 'no-store'), 'Method error must not be cached.');

$rss = request('/rss.xml');
check($rss->status === 200, 'RSS did not return 200.');
check(str_contains($rss->body, 'https://calendorama.dgroza.ru'), 'RSS HTTPS links are missing.');
check(!str_contains($rss->headers['cache-control'] ?? '', 'immutable'), 'RSS must not be immutable.');
check(str_contains($rss->headers['cache-control'] ?? '', 'no-cache'), 'RSS must be revalidated.');

foreach ([$styleUrl, $scriptUrl, '/favicon.ico', '/blog/3_1_thmb.jpg', '/blog/3_1_full.jpg'] as $asset) {
    $response = request($asset);
    check($response->status === 200, $asset . ' did not return 200.');
    check(str_contains($response->headers['cache-control'] ?? '', 'immutable'), $asset . ' is not immutable.');
    check(str_contains($response->headers['cache-control'] ?? '', 'max-age=31536000'), $asset . ' max-age is incorrect.');
    check(str_contains($response->headers['content-security-policy'] ?? '', "default-src 'self'"), $asset . ' CSP is missing.');
    check(!isset($response->headers['permissions-policy']), $asset . ' must not send an unnecessary Permissions-Policy.');
}

$unversionedStyle = request('/style.css');
check(!str_contains($unversionedStyle->headers['cache-control'] ?? '', 'immutable'), 'Unversioned CSS must not be immutable.');
check(str_contains($unversionedStyle->headers['cache-control'] ?? '', 'no-cache'), 'Unversioned CSS must be revalidated.');

foreach (['/content/archive.json', '/src/', '/templates/', '/vendor/', '/blog/1.txt', '/function.php', '/arbitrary.php'] as $path) {
    check(request($path)->status === 404, $path . ' did not return 404.');
}

fwrite(STDOUT, "HTTP smoke checks passed.\n");
