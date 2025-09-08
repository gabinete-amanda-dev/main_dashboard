Meta/Facebook is the new home for our Facebook scraping utilities and service layer.

What’s here
- `FacebookService.php`: High‑level service used by controllers. Provides helpers like `getPageName`, `getPageFolloewers`, `getPosts`, etc.
- `Browser/ChromeLauncher.php`: Starts Chrome WebDriver, manages profiles, retries, and common flags.
- `Auth/LoginHelper.php`: Handles login flows (direct login, in‑page dialog), cookie checks, and 2FA detection.
- `Scraper/Extractors.php`: Parses page HTML/DOM to extract name, followers, posts, and counts.
- `Scraper/Crawler.php`: Authenticated scraper that uses Chrome + profile to load fully unlocked pages and extract.
- `Crawler/PublicCrawler.php`: Unauthenticated HTTP crawler for public data without a browser session.
- `Support/ProfileUtils.php`: Profile/FS helpers (clone, unlock, cleanup, locks, port waiters).

How it works (two modes)
- Scraper (authenticated, default): Uses Chrome + your Facebook profile to load the full page and extract more reliable counts.
  - Select by env: `FB_MODE=scraper` (or unset).
- Crawler (public, unauthenticated): Simple HTTP fetch + parse, no login; yields only data visible to logged‑out users.
  - Select by env: `FB_MODE=crawler`.

Key env vars
- Mode and basics:
  - `FB_MODE`: `scraper` (default) or `crawler`.
  - `FB_USER_AGENT`: UA string override for both modes (default: modern Chrome UA).
  - `FB_LANG`: Accept‑Language (default: `en-US`).
- Auth (scraper mode):
  - `FACEBOOK_EMAIL`, `FACEBOOK_PASSWORD`: credentials for automated login if not already logged in.
  - `PANTHER_PROFILE_DIR`: Chrome user‑data dir to persist sessions (recommended).
  - `PANTHER_HEADLESS`: `0` for first run (to complete login/2FA), `1` for headless afterward.
  - Optional: `FB_PROFILE_CLONE_RUN`, `FB_LOCK_STARTUP`, `FB_FORCE_UNLOCK_PROFILE`.
- Scraper tuning:
  - `FB_POST_LIMIT` (default `25`), `FB_MAX_SCROLLS` (default `8`), `FB_SCROLL_WAIT_MS` (default `500`).

First‑run (scraper mode)
1) Set a persistent profile and run non‑headless to log in:
   - `export PANTHER_PROFILE_DIR=var/google-chrome`
   - `export PANTHER_HEADLESS=0`
   - Optionally set `FACEBOOK_EMAIL` and `FACEBOOK_PASSWORD` to auto‑fill the login.
2) Visit the dashboard (`/dashboard`), log in if prompted, complete any 2FA once.
3) Switch to headless for subsequent runs: `export PANTHER_HEADLESS=1`.

Using in a controller
- Type‑hint the facade `App\Service\FacebookService` (which extends this Meta service), or type‑hint `App\Service\Meta\Facebook\FacebookService` directly.

Example (simplified):

  // src/Controller/DashboardController.php:16
  public function index(ChartBuilderInterface $charts, \App\Service\FacebookService $fb) {
      $pageId = 'example.page';
      $name = $fb->getPageName($pageId);
      $followers = (int) ($fb->getPageFolloewers($pageId) ?? 0); // note: method name uses current spelling
      $posts = $fb->getPosts($pageId, 10);
      // ... build charts
  }

Notes
- The public crawler may return fewer posts and no private metrics compared to the scraper.
- Comment extraction is scaffolded but not implemented yet.
- If you encounter a 2FA challenge in headless runs, re‑run with `PANTHER_HEADLESS=0` and a persistent `PANTHER_PROFILE_DIR` once to approve the session.

CLI command
- Run the scraper/crawler from the terminal and dump JSON:

  bin/console app:facebook:crawl <pageId> [--mode=scraper|crawler] [--limit=25] [--scrolls=8] [--wait=500] [--pretty]

Examples
- Authenticated scraper, pretty JSON:

  FB_MODE=scraper PANTHER_PROFILE_DIR=var/google-chrome bin/console app:facebook:crawl amandavettorazzo.sp --pretty

- Public crawler (no login):

  FB_MODE=crawler bin/console app:facebook:crawl amandavettorazzo.sp --mode=crawler --pretty
