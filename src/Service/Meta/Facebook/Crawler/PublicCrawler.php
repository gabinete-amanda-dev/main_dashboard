<?php

namespace App\Service\Meta\Facebook\Crawler;

use App\Service\Meta\Facebook\Scraper\Extractors;

/**
 * Public (unauthenticated) crawler that fetches the public page HTML
 * without using a browser profile or login, and extracts basic data.
 */
final class PublicCrawler
{
    /**
     * @return array{name: ?string, followers: ?int, posts: array<int,array<string,mixed>>, engine: string, pageId: string, html_length: int}
     */
    public function crawlPage(string $pageId): array
    {
        $env = fn(string $k, $d=null) => $_ENV[$k] ?? getenv($k) ?? $d;
        $ua = (string) ($env('FB_USER_AGENT', '') ?: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36');
        $lang = (string) ($env('FB_LANG', '') ?: 'en-US');

        $url = 'https://www.facebook.com/' . ltrim($pageId, '/');
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", [
                    'User-Agent: ' . $ua,
                    'Accept-Language: ' . $lang,
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Connection: close',
                ]),
                'timeout' => 10,
            ],
            'ssl' => [ 'verify_peer' => true, 'verify_peer_name' => true ],
        ]);

        $html = @file_get_contents($url, false, $ctx);
        if ($html === false) { $html = ''; }

        $ex = new Extractors();
        $structured = $ex->parseFacebookPageHtml($html);

        return [
            'engine' => 'crawler-http',
            'pageId' => $pageId,
            'name' => $structured['name'] ?? null,
            'followers' => $structured['followers'] ?? null,
            'posts' => $structured['posts'] ?? [],
            'html_length' => strlen($html),
        ];
    }
}

