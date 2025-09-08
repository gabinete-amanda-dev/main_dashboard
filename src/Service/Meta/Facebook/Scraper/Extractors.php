<?php

namespace App\Service\Meta\Facebook\Scraper;

use Facebook\WebDriver\WebDriver;
use Facebook\WebDriver\WebDriverBy;
use Symfony\Component\DomCrawler\Crawler;

final class Extractors
{
    public function parseFacebookPageHtml(string $html): array
    {
        $crawler = new Crawler($html);
        // Name
        $name = null;
        try { $name = $crawler->filter('meta[property="og:title"]')->attr('content'); }
        catch (\Throwable) { try { $name = trim($crawler->filter('h1')->first()->text()); } catch (\Throwable) {} }

        // Followers via description meta/body
        $followers = null; $metaDesc = null;
        try { $metaDesc = $crawler->filter('meta[property="og:description"]')->attr('content'); } catch (\Throwable) {}
        if (!$metaDesc) { try { $metaDesc = $crawler->filter('meta[name="description"]')->attr('content'); } catch (\Throwable) {} }
        if ($metaDesc) { $followers = $this->extractFollowersFromText($metaDesc); }
        if ($followers === null) {
            try { $bodyText = $crawler->filter('body')->text(''); $followers = $this->extractFollowersFromText($bodyText); } catch (\Throwable) {}
        }

        // Posts (Actions for this post ancestor heuristic)
        $posts = [];
        try {
            $seen = [];
            $xpath = "//*[(@role='button' or @role='menuitem') and (@aria-haspopup='menu' or @aria-expanded) and (contains(@aria-label,'Actions') or contains(@aria-label,'Ações') or contains(@aria-label,'Acoes') or contains(@aria-label,'Acciones')) and (contains(translate(@aria-label,'ABCDEFGHIJKLMNOPQRSTUVWXYZÁÀÃÂÉÈÊÍÌÎÓÒÔÕÚÙÛÇ','abcdefghijklmnopqrstuvwxyzáàãâéèêíìîóòôõúùûç'),'post') or contains(translate(@aria-label,'ABCDEFGHIJKLMNOPQRSTUVWXYZÁÀÃÂÉÈÊÍÌÎÓÒÔÕÚÙÛÇ','abcdefghijklmnopqrstuvwxyzáàãâéèêíìîóòôõúùûç'),'publica')) and not(contains(translate(@aria-label,'ABCDEFGHIJKLMNOPQRSTUVWXYZÁÀÃÂÉÈÊÍÌÎÓÒÔÕÚÙÛÇ','abcdefghijklmnopqrstuvwxyzáàãâéèêíìîóòôõúùûç'),'comment')) and not(contains(translate(@aria-label,'ABCDEFGHIJKLMNOPQRSTUVWXYZÁÀÃÂÉÈÊÍÌÎÓÒÔÕÚÙÛÇ','abcdefghijklmnopqrstuvwxyzáàãâéèêíìîóòôõúùûç'),'coment'))]/ancestor::*[@role='article'][1]";
            $crawler->filterXPath($xpath)->each(function (Crawler $node) use (&$posts, &$seen) {
                $dom = $node->getNode(0); if (!$dom) { return; }
                $id = spl_object_hash($dom); if (isset($seen[$id])) { return; } $seen[$id] = true;
                $articleHtml = ''; try { $articleHtml = $node->html(); } catch (\Throwable) {}
                $articleText = '';
                try { $message = $node->filter('[data-ad-preview="message"], [data-ad-comet-preview="message"]')->first(); if ($message->count()) { $articleText = trim(preg_replace('/\s+/', ' ', $message->text(''))); } } catch (\Throwable) {}
                if ($articleText === '') { return; }
                $likes = $this->extractCountByKeywords($articleHtml, ['likes','like','reactions','reaction','curtidas','reações']);
                $comments = $this->extractCountByKeywords($articleHtml, ['comments','comment','comentários','comentario']);
                $posts[] = [
                    'text' => $articleText,
                    'preview' => $this->previewText($articleText),
                    'full_text' => $articleText,
                    'likes' => $likes,
                    'comments' => $comments,
                ];
            });
        } catch (\Throwable) {}

        return [ 'name' => $name, 'followers' => $followers, 'posts' => $posts ];
    }

    public function extractPostsFromDom(WebDriver $wd, int $limit = 5): array
    {
        $results = [];
        try {
            $articles = $wd->findElements(WebDriverBy::cssSelector('[role="article"]'));
            foreach ($articles as $article) {
                if (count($results) >= $limit) { break; }
                try { $article->getLocationOnScreenOnceScrolledIntoView(); } catch (\Throwable $ignored) {}
                usleep(200000);
                $text = '';
                try {
                    $message = $article->findElement(WebDriverBy::cssSelector('[data-ad-preview="message"], [data-ad-comet-preview="message"]'));
                    $text = trim(preg_replace('/\s+/', ' ', $message->getText()));
                } catch (\Throwable $ignored) {}
                if ($text === '') {
                    try { $img = $article->findElement(WebDriverBy::xpath(".//img[@alt and normalize-space(@alt)]")); $alt = trim($img->getAttribute('alt')); if ($alt !== '') { $text = $alt; } } catch (\Throwable $ignored) {}
                    if ($text === '') { $text = '[media post]'; }
                }
                $reactions = null; $comments = null; $shares = null;
                for ($try = 0; $try < 5 && ($comments === null || $shares === null || $reactions === null); $try++) {
                    try {
                        $reactionNums = $article->findElements(WebDriverBy::xpath(
                            ".//div[.//span[contains(@aria-label,'reacted') or contains(@aria-label,'reaç') or contains(@aria-label,'reagiram')]]//span[normalize-space() and not(*)]"
                        ));
                        foreach ($reactionNums as $el) {
                            $t = trim($el->getText());
                            if ($t !== '' && preg_match('/^[\d\.,]+\s*[KMBkmb]?$/', $t)) { $reactions = $reactions ?? $this->parseHumanNumber($t); break; }
                        }
                    } catch (\Throwable $ignored) {}
                    if ($reactions === null) {
                        try {
                            $reactionLabels = $article->findElements(WebDriverBy::xpath(
                                ".//*[contains(@aria-label,'reactions') or contains(@aria-label,'reaç') or contains(@aria-label,'curtidas') or contains(@aria-label,'curtida')]"
                            ));
                            foreach ($reactionLabels as $el) {
                                $al = trim((string) $el->getAttribute('aria-label'));
                                if ($al && preg_match('/([\d\.,]+\s*[KMBkmb]?)/u', $al, $m)) { $reactions = $this->parseHumanNumber($m[1]); break; }
                            }
                        } catch (\Throwable $ignored) {}
                    }
                    $candidates = [];
                    try { $candidates = $article->findElements(WebDriverBy::xpath(".//span|.//a|.//div|.//button")); } catch (\Throwable $ignored) {}
                    foreach ($candidates as $el) {
                        if ($comments !== null && $shares !== null) { break; }
                        $t = trim($el->getText());
                        $al = '';
                        try { $al = trim((string) $el->getAttribute('aria-label')); } catch (\Throwable $ignored) {}
                        if ($t !== '') {
                            if ($comments === null && preg_match('/([\d\.,]+\s*[KMBkmb]?)\s*(comments|coment[aá]rios)/iu', $t, $m)) { $comments = $this->parseHumanNumber($m[1]); }
                            if ($shares === null && preg_match('/([\d\.,]+\s*[KMBkmb]?)\s*(shares|compart)/iu', $t, $m)) { $shares = $this->parseHumanNumber($m[1]); }
                        }
                        if ($al !== '') {
                            if ($comments === null && preg_match('/([\d\.,]+\s*[KMBkmb]?).{0,5}(comments|coment[aá]rios)/iu', $al, $m)) { $comments = $this->parseHumanNumber($m[1]); }
                            if ($shares === null && preg_match('/([\d\.,]+\s*[KMBkmb]?).{0,5}(shares|compart)/iu', $al, $m)) { $shares = $this->parseHumanNumber($m[1]); }
                        }
                    }
                    if ($comments !== null || $shares !== null || $reactions !== null) { break; }
                    usleep(150000);
                }
                $results[] = [
                    'text' => $text,
                    'preview' => $this->previewText($text),
                    'full_text' => $text,
                    'likes' => $reactions,
                    'comments' => $comments,
                    'shares' => $shares,
                ];
            }
        } catch (\Throwable $ignored) {}
        return $results;
    }

    private function previewText(string $text): string
    {
        $raw = $_ENV['FB_PREVIEW_LEN'] ?? getenv('FB_PREVIEW_LEN') ?? '280';
        $len = (int) $raw; if ($len <= 0) { return $text; }
        if ($len > 2000) { $len = 2000; }
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($text) > $len) { return rtrim(mb_substr($text, 0, $len)) . '…'; }
            return $text;
        }
        if (strlen($text) > $len) { return rtrim(substr($text, 0, $len)) . '…'; }
        return $text;
    }

    private function extractCountByKeywords(string $html, array $keywords): ?int
    {
        $kw = implode('|', array_map('preg_quote', $keywords));
        if (preg_match('/([\d\.,]+\s*[KMkmbB]?)\s*(?:'.$kw.')/iu', $html, $m)) { return $this->parseHumanNumber($m[1]); }
        return null;
    }

    private function extractFollowersFromText(string $text): ?int
    {
        if (preg_match('/([\d\.,]+\s*[KMkmbB]?)\s*(followers|seguidores)/iu', $text, $m)) { return $this->parseHumanNumber($m[1]); }
        return null;
    }

    private function parseHumanNumber(string $raw): int
    {
        $s = trim($raw);
        $mult = 1;
        if (preg_match('/([kmb])$/i', $s, $m)) {
            $suffix = strtolower($m[1]);
            $mult = $suffix === 'k' ? 1_000 : ($suffix === 'm' ? 1_000_000 : 1_000_000_000);
            $s = substr($s, 0, -1);
        }
        $s = trim($s);
        if (str_contains($s, ',') && str_contains($s, '.')) { $s = str_replace(',', '', $s); }
        else { $s = str_replace(',', '.', $s); }
        $val = (float) $s; return (int) round($val * $mult);
    }
}
