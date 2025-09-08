**Main Dashboard**

- Overview: Symfony-based social dashboard with Facebook scraping utilities and charts.
- Key: Uses a unified `App\Service\FacebookService` facade backed by Meta/Facebook implementations.

**Architecture**
- Controller: `src/Controller/DashboardController.php` renders charts from the Facebook service.
- Facade: `App\Service\FacebookService` exposes helper methods for controllers.
- Meta layer: `src/Service/Meta/Facebook` contains concrete implementations and helpers.
  - `FacebookService`: high-level API (getPageName, getPageFolloewers, getPosts, ...).
  - `Scraper/Crawler.php`: authenticated scraper using Chrome + profile (full page data).
  - `Crawler/PublicCrawler.php`: unauthenticated HTTP crawler (public page data).
  - `Scraper/Extractors.php`: DOM/HTML parsing for name, followers, posts, counts.
  - `Browser/ChromeLauncher.php`, `Auth/LoginHelper.php`, `Support/ProfileUtils.php`: browser orchestration and login utilities.

**FacebookService Implementations**
- Two modes selectable via `FB_MODE`:
  - `scraper` (default):
    - Launches Chrome WebDriver, uses your Facebook session/profile to load the unlocked page.
    - More reliable counts; supports scrolling to load more posts.
  - `crawler`:
    - Makes an unauthenticated HTTP request and parses the public HTML only.
    - Limited visibility and counts vs the scraper.

**Public API (Facade)**
- Type-hint `App\Service\FacebookService` in controllers; it delegates to the Meta service.
- Methods:
  - `getPageName(string $pageId): ?string`
  - `getPageFolloewers(string $pageId): ?int` (current spelling preserved)
  - `getPosts(string $pageId, int $limit = 25): array` (keys: text, preview, full_text, likes, comments, shares)
  - `GetPostReactions(int $postIndex): ?int`
  - `getPostComments(int $postIndex): array` (placeholder for now)
  - `getPostCommentReactions(int $commentIndex): ?int`

**Setup**
- Requirements: PHP 8.1+, Composer, Chrome/Chromedriver (bundled in `drivers/`), Node (for assets).
- Install deps: `composer install && npm ci`
- Environment:
  - Set `FACEBOOK_ACCESS_TOKEN` (DI constructor arg; not used in scraping paths but required).
  - Choose mode: `export FB_MODE=scraper` (default) or `export FB_MODE=crawler`.
  - Scraper mode (recommended):
    - `export PANTHER_PROFILE_DIR=var/google-chrome`
    - First run: `export PANTHER_HEADLESS=0` (complete login/2FA once)
    - Optional auto-login: set `FACEBOOK_EMAIL` and `FACEBOOK_PASSWORD`
    - After first run: `export PANTHER_HEADLESS=1`
  - Tuning (scraper): `FB_POST_LIMIT` (default 25), `FB_MAX_SCROLLS` (8), `FB_SCROLL_WAIT_MS` (500)
  - Common: `FB_USER_AGENT` and `FB_LANG` to override UA and Accept-Language

**Run**
- Dev server: `symfony server:start -d` then open `/dashboard`
- CLI crawl (JSON dump):
  - `bin/console app:facebook:crawl <pageId> --mode=scraper --pretty`
  - `bin/console app:facebook:crawl <pageId> --mode=crawler --pretty`

**Dashboard Integration**
- `DashboardController` injects `App\Service\FacebookService`:
  - Fetches name, followers, and posts; renders charts with `symfony/ux-chartjs`.
  - The controller remains unchanged when switching modes; `FB_MODE` selects the implementation.

**Troubleshooting**
- 2FA challenge: run non‑headless once (`PANTHER_HEADLESS=0`) with a persistent `PANTHER_PROFILE_DIR`, complete approvals, then return to headless.
- Chromedriver issues (locks/DevToolsActivePort):
  - Use the persistent profile dir in `var/google-chrome` (repo includes `.gitkeep` to preserve path)
  - Optional: `FB_FORCE_UNLOCK_PROFILE=1`, `FB_PROFILE_CLONE_RUN=1` for clean per-run clones
- Public mode returns limited data when the page restricts logged‑out access.

**Security**
- Do not commit real credentials. Use env vars or secrets storage.
- Respect Facebook terms of service and applicable laws when scraping.

