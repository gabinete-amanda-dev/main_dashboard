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

**Deploy to AWS Lambda (Bref) + Cognito**
**Deploy to AWS Lambda (Bref) + Cognito**
- Overview: Use Bref to run Symfony on Lambda behind API Gateway; protect the dashboard with Cognito.
- Requirements: AWS CLI configured, Node 18+, PHP 8.1+, Composer, an S3 bucket for deployment assets.
- Install Bref:
  - `composer require bref/bref --dev`
  - Optional: `composer require bref/symfony-bridge`
- serverless.yml (example):

  service: main-dashboard
  provider:
    name: aws
    region: us-east-1
    runtime: provided.al2
    environment:
      APP_ENV: prod
      FB_MODE: ${env:FB_MODE, 'crawler'}
      FACEBOOK_ACCESS_TOKEN: ${env:FACEBOOK_ACCESS_TOKEN, ''}
      PANTHER_REMOTE_URL: ${env:PANTHER_REMOTE_URL, ''}
    iam:
      role: arn:aws:iam::<account-id>:role/<lambda-exec-role>
  plugins:
    - ./vendor/bref/bref
  functions:
    web:
      handler: public/index.php
      runtime: php-82-fpm
      events:
        - httpApi:
            method: ANY
            path: /{proxy+}
            authorizer:
              type: jwt
              issuerUrl: https://cognito-idp.<region>.amazonaws.com/<user-pool-id>
              audience:
                - <app-client-id>
  package:
    exclude:
      - node_modules/**
      - var/**
      - tests/**
      - .git/**
      - .venv*/**

- Cognito setup (JWT authorizer for API Gateway HTTP API):
  - Create a Cognito User Pool and an App Client (no client secret for SPA flows).
  - Configure a Cognito Domain (Hosted UI) for login.
  - Note the User Pool ID and App Client ID; use them in `issuerUrl` and `audience` above.
  - In API Gateway (HTTP API), the JWT authorizer validates the Authorization: Bearer <JWT> on all routes.
  - Frontend/login flow: redirect users to the Cognito Hosted UI; on return, store the ID token and include it in `Authorization` header for requests to the dashboard.

- Running headless Chrome on Lambda (scraper mode) options:
  - Easiest: use a managed remote Chrome and set `PANTHER_REMOTE_URL` (e.g., Browserless/Selenium Grid). Lambda connects out; no system Chrome needed.
  - Advanced: attach a Lambda layer with Chromium (e.g., `Sparticuz/chromium`) and a matching Chromedriver, then set `PANTHER_CHROME_BINARY` and start `chromedriver` via a sidecar/custom runtime.
  - For simple deployments, set `FB_MODE=crawler` in Lambda to avoid a browser.

- Deploy:
  - Export required envs (or use CI/CD params): `export FB_MODE=crawler`
  - `composer install --no-dev --optimize-autoloader`
  - `npm ci && npm run build`
  - `vendor/bin/bref deploy --stage prod` (or `serverless deploy`)

- Configure environment variables in Lambda:
  - In the Lambda console, set `FB_MODE`, `FACEBOOK_ACCESS_TOKEN`, and if using remote Chrome: `PANTHER_REMOTE_URL`.
  - For scraper mode with login, prefer a remote WebDriver rather than persisting a profile on Lambda.

**Troubleshooting**
- 2FA challenge: run non‑headless once (`PANTHER_HEADLESS=0`) with a persistent `PANTHER_PROFILE_DIR`, complete approvals, then return to headless.
- Chromedriver issues (locks/DevToolsActivePort):
  - Use the persistent profile dir in `var/google-chrome` (repo includes `.gitkeep` to preserve path)
  - Optional: `FB_FORCE_UNLOCK_PROFILE=1`, `FB_PROFILE_CLONE_RUN=1` for clean per-run clones
- Public mode returns limited data when the page restricts logged‑out access.

**Security**
- Do not commit real credentials. Use env vars or secrets storage.
- Respect Facebook terms of service and applicable laws when scraping.
