<?php

namespace App\Service\Meta\Facebook\Scraper;

use App\Service\Meta\Facebook\Auth\LoginHelper;
use App\Service\Meta\Facebook\Browser\ChromeLauncher;
use App\Service\Meta\Facebook\Support\ProfileUtils;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Symfony\Component\Process\Process;

final class Crawler
{
    /**
     * Crawl a Facebook page and extract structured info.
     *
     * @param string $pageId  Username or numeric page id.
     * @param array{limit?:int,scrolls?:int,wait?:int} $opts Options: limit posts, scroll attempts, wait ms between.
     * @return array{name: ?string, followers: ?int, posts: array<int,array<string,mixed>>, engine: string, pageId: string}
     */
    public function crawlPage(string $pageId, array $opts = []): array
    {
        $env = fn(string $k, $d=null) => $_ENV[$k] ?? getenv($k) ?? $d;
        $limit = isset($opts['limit']) ? (int) $opts['limit'] : (int) ($env('FB_POST_LIMIT', '25'));
        if ($limit <= 0) { $limit = 25; }
        $maxScrolls = isset($opts['scrolls']) ? (int) $opts['scrolls'] : (int) ($env('FB_MAX_SCROLLS', '8'));
        if ($maxScrolls < 0) { $maxScrolls = 0; }
        $waitMs = isset($opts['wait']) ? (int) $opts['wait'] : (int) ($env('FB_SCROLL_WAIT_MS', '500'));
        if ($waitMs < 100) { $waitMs = 250; }

        $driver = null; $userDataDir = null; $persistUserData = false; $chromedriverProcess = null;
        $utils = new ProfileUtils();
        try {
            [ $driver, $userDataDir, $persistUserData, $chromedriverProcess ] = (new ChromeLauncher())->start();

            (new LoginHelper())->maybeLogin($driver, (string) $env('FACEBOOK_EMAIL',''), (string) $env('FACEBOOK_PASSWORD',''));
            $driver->get("https://www.facebook.com/{$pageId}");
            // Handle in-page login dialog and detect 2FA
            (new LoginHelper())->maybeLoginInPageDialog($driver, (string) $env('FACEBOOK_EMAIL',''), (string) $env('FACEBOOK_PASSWORD',''));
            if ((new LoginHelper())->isTwoFactorChallenge($driver)) {
                throw new \RuntimeException('Facebook 2FA challenge detected. Run with PANTHER_HEADLESS=0 and PANTHER_PROFILE_DIR, complete 2FA once, then rerun headless.');
            }
            $driver->wait(15, 250)->until(WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector('body')));

            // Optional consent banner click
            try {
                $consentXpath = "//button[contains(., 'Allow all cookies') or contains(., 'Accept all cookies') or contains(., 'Aceitar todos os cookies') or contains(., 'Permitir todos os cookies')]";
                $btn = $driver->findElement(WebDriverBy::xpath($consentXpath));
                if ($btn) { $btn->click(); usleep(200000); }
            } catch (\Throwable $ignored) {}

            // Scroll to load more posts until limit or maxScrolls
            $seenArticles = 0; $scrolls = 0;
            while ($scrolls < $maxScrolls) {
                try { $articles = $driver->findElements(WebDriverBy::cssSelector('[role="article"]')); $count = count($articles); }
                catch (\Throwable) { $count = 0; }
                if ($count >= $limit) { break; }
                $scrolls++;
                try { $driver->executeScript('window.scrollTo(0, document.body.scrollHeight);'); } catch (\Throwable $ignored) {}
                usleep($waitMs * 1000);
                try {
                    $driver->wait(5, 200)->until(function(RemoteWebDriver $d) use ($count) {
                        try { return count($d->findElements(WebDriverBy::cssSelector('[role="article"]'))) > $count; } catch (\Throwable) { return true; }
                    });
                } catch (\Throwable $ignored) {}
                $seenArticles = $count;
            }

            $html = (string) $driver->getPageSource();
            $ex = new Extractors();
            $structured = $ex->parseFacebookPageHtml($html);
            $posts = $ex->extractPostsFromDom($driver, $limit);
            if ($posts) { $structured['posts'] = $posts; }

            return [
                'engine' => 'scraper-chrome',
                'pageId' => $pageId,
                'name' => $structured['name'] ?? null,
                'followers' => $structured['followers'] ?? null,
                'posts' => $structured['posts'] ?? [],
                'html_length' => strlen($html),
                'articles_seen' => $seenArticles,
            ];
        } finally {
            if ($driver) { try { $driver->quit(); } catch (\Throwable $ignored) {} }
            if ($chromedriverProcess instanceof Process) { try { $chromedriverProcess->stop(2); } catch (\Throwable $ignored) {} }
            if ($userDataDir && is_dir($userDataDir) && !$persistUserData) { $utils->removeDir($userDataDir); }
        }
    }
}

