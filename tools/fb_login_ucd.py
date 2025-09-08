#!/usr/bin/env python3
"""
Log into Facebook using undetected-chromedriver and persist the session into
the provided Chrome user data directory so the PHP scraper can reuse it.

Usage:
  - Ensure you have Python 3 and install dependency:
      pip install --user undetected-chromedriver
  - Set env vars (or pass via CLI flags if you extend it):
      FACEBOOK_EMAIL, FACEBOOK_PASSWORD, PANTHER_PROFILE_DIR
  - Run:
      python tools/fb_login_ucd.py

Notes:
  - Keep this run non-headless to complete any 2FA / checkpoint once.
  - After success, set PANTHER_HEADLESS=1 and PANTHER_PROFILE_DIR to this
    same directory for your PHP scraper.
"""

import os
import sys
import time
import contextlib

try:
    import undetected_chromedriver as uc  # type: ignore
except Exception as e:
    print("[ERR] undetected-chromedriver not installed. Run: pip install --user undetected-chromedriver", file=sys.stderr)
    raise


def env(key: str, default: str = "") -> str:
    return os.environ.get(key, default)


def main() -> int:
    email = env("FACEBOOK_EMAIL")
    password = env("FACEBOOK_PASSWORD")
    profile_dir = env("PANTHER_PROFILE_DIR") or env("UC_PROFILE_DIR")
    keep_open = env("FB_UCD_KEEP_OPEN", env("UCD_KEEP_OPEN", "0")).lower() in ("1", "true", "yes") or "--keep-open" in sys.argv or "--hold" in sys.argv
    hold_seconds = env("FB_UCD_HOLD_SECONDS", env("UCD_HOLD_SECONDS", "")).strip()

    if not profile_dir:
        print("[ERR] PANTHER_PROFILE_DIR (or UC_PROFILE_DIR) is required.", file=sys.stderr)
        return 2
    if not os.path.isdir(profile_dir):
        os.makedirs(profile_dir, exist_ok=True)

    if not email or not password:
        print("[WARN] FACEBOOK_EMAIL / FACEBOOK_PASSWORD not set. You can still log in manually in the window.")

    # Set up undetected-chromedriver with the provided user-data-dir
    options = uc.ChromeOptions()
    options.add_argument(f"--user-data-dir={profile_dir}")
    options.add_argument("--lang=en-US,en;q=0.9")
    options.add_argument("--disable-gpu")
    options.add_argument("--disable-dev-shm-usage")
    options.add_argument("--no-first-run")
    options.add_argument("--no-default-browser-check")
    options.add_argument("--password-store=basic")
    options.add_argument("--use-mock-keychain")

    # Do NOT set headless: we want to complete 2FA if prompted
    driver = uc.Chrome(options=options)
    driver.set_window_size(1200, 900)

    try:
        driver.get("https://www.facebook.com/login")
        time.sleep(1.0)

        # Cookie consent (best effort)
        with contextlib.suppress(Exception):
            for label in [
                "Allow all cookies",
                "Accept all cookies",
                "Aceitar todos os cookies",
                "Permitir todos os cookies",
                "Only allow essential",
                "Apenas os essenciais",
            ]:
                btns = driver.find_elements("xpath", f"//button[contains(., '{label}')]")
                if btns:
                    btns[0].click()
                    time.sleep(0.5)
                    break

        if email and password:
            with contextlib.suppress(Exception):
                driver.find_element("css selector", "input#email, input[name='email']").clear()
            with contextlib.suppress(Exception):
                driver.find_element("css selector", "input#email, input[name='email']").send_keys(email)
            with contextlib.suppress(Exception):
                driver.find_element("css selector", "input#pass, input[name='pass']").clear()
            with contextlib.suppress(Exception):
                driver.find_element("css selector", "input#pass, input[name='pass']").send_keys(password)
            with contextlib.suppress(Exception):
                driver.find_element("css selector", "button[name='login'], input[name='login']").click()

        print("[INFO] Waiting for successful login (c_user cookie) or 2FA prompt…")
        # Wait up to 120s for c_user cookie (logged in) or for a 2FA field
        t0 = time.time()
        had_2fa = False
        while time.time() - t0 < 120:
            with contextlib.suppress(Exception):
                # Detect 2FA field
                if driver.find_elements("css selector", "input[name='approvals_code']"):
                    had_2fa = True
                    print("[ACTION] Enter your 2FA code in the browser window, then press Continue.")
            # Check cookies for c_user
            with contextlib.suppress(Exception):
                cookies = driver.get_cookies()
                if any(c.get('name') == 'c_user' and c.get('value') for c in cookies):
                    print("[OK] Logged in. Session persisted to:", profile_dir)
                    return 0
            time.sleep(1.0)

        if had_2fa:
            print("[WARN] 2FA likely required. Complete it and re-run if needed.")
        else:
            print("[ERR] Timed out waiting for login. Check credentials or try manual login.")
        return 1
    finally:
        # Keep the Chrome window open if requested (for manual verification or debugging)
        try:
            if keep_open:
                if hold_seconds.isdigit():
                    secs = int(hold_seconds)
                    print(f"[INFO] Keeping Chrome open for {secs}s. Close it manually to end earlier.")
                    t0 = time.time()
                    while time.time() - t0 < secs:
                        time.sleep(0.5)
                else:
                    print("[INFO] Keeping Chrome open. Press Ctrl+C here or close the window to exit.")
                    while True:
                        time.sleep(0.5)
        except KeyboardInterrupt:
            pass
        finally:
            with contextlib.suppress(Exception):
                driver.quit()


if __name__ == "__main__":
    raise SystemExit(main())
