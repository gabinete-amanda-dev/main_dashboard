<?php

namespace App\Service\Meta\Facebook;

use App\Service\Meta\Facebook\Scraper\Crawler as AuthenticatedScraper;
use App\Service\Meta\Facebook\Crawler\PublicCrawler;

/**
 * Meta Facebook service.
 *
 * Provides high-level helpers backed by the Scraper Crawler that return
 * structured data for dashboards and analytics.
 */
class FacebookService {
    private string $accessToken;
    public function __construct(string $accessToken) { $this->accessToken = $accessToken; }
    /** @var array<string,mixed>|null */
    private ?array $lastData = null;
    private ?string $lastPageId = null;
    /** @var array<int,array<string,mixed>>|null */
    private ?array $lastComments = null;

    /**
     * Ensures we have fresh crawl data for a page.
     * @param array{limit?:int,scrolls?:int,wait?:int} $opts
     */
    private function ensureData(string $pageId, array $opts = []): void
    {
        if ($this->lastData === null || $this->lastPageId !== $pageId) {
            $mode = strtolower((string) (($_ENV['FB_MODE'] ?? getenv('FB_MODE') ?? 'scraper')));
            if ($mode === 'crawler') {
                $this->lastData = (new PublicCrawler())->crawlPage($pageId);
            } else {
                $this->lastData = (new AuthenticatedScraper())->crawlPage($pageId, $opts);
            }
            $this->lastPageId = $pageId;
            $this->lastComments = null;
        }
    }

    /** Returns the page name, or null if unavailable. */
    public function getPageName(string $pageId): ?string
    {
        $this->ensureData($pageId);
        return $this->lastData['name'] ?? null;
    }

    /** Returns the page followers count, or null if unavailable. */
    public function getPageFolloewers(string $pageId): ?int
    {
        $this->ensureData($pageId);
        $v = $this->lastData['followers'] ?? null;
        return is_numeric($v) ? (int) $v : null;
    }

    /**
     * Returns posts with basic metrics. Keys: text, preview, full_text, likes, comments, shares.
     * @return array<int,array<string,mixed>>
     */
    public function getPosts(string $pageId, int $limit = 25): array
    {
        $this->ensureData($pageId, ['limit' => $limit]);
        $posts = $this->lastData['posts'] ?? [];
        if (!is_array($posts)) { return []; }
        return array_slice($posts, 0, max(0, $limit));
    }

    /** Returns the reactions (likes) count for a post index, if available. */
    public function GetPostReactions(int $postIndex): ?int
    {
        if (!$this->lastData || !isset($this->lastData['posts']) || !is_array($this->lastData['posts'])) { return null; }
        $posts = $this->lastData['posts'];
        if (!isset($posts[$postIndex]) || !is_array($posts[$postIndex])) { return null; }
        $v = $posts[$postIndex]['likes'] ?? null;
        return is_numeric($v) ? (int) $v : null;
    }

    /**
     * Returns comments for the given post index.
     * Note: comment extraction is not implemented yet; returns an empty array.
     * @return array<int,array<string,mixed>>
     */
    public function getPostComments(int $postIndex): array
    {
        // Placeholder: comments extraction not implemented in Extractors yet.
        $this->lastComments = [];
        return [];
    }

    /** Returns reactions count for a previously selected comment index (from getPostComments). */
    public function getPostCommentReactions(int $commentIndex): ?int
    {
        if ($this->lastComments === null) { return null; }
        if (!isset($this->lastComments[$commentIndex]) || !is_array($this->lastComments[$commentIndex])) { return null; }
        $v = $this->lastComments[$commentIndex]['likes'] ?? null;
        return is_numeric($v) ? (int) $v : null;
    }
}
