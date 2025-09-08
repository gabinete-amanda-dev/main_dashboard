<?php

namespace App\Service\Meta\Facebook\Browser;

use App\Service\Meta\Facebook\Support\ProfileUtils;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Symfony\Component\Process\Process;

/**
 * Encapsula a criação do Chrome WebDriver com suporte a:
 * - profile persistente (PANTHER_PROFILE_DIR) e clone-per-run (FB_PROFILE_CLONE_RUN)
 * - locks/Singleton cleanup e lock de inicialização entre requisições (FB_LOCK_STARTUP)
 * - retry com profile temporário em erros comuns (DevToolsActivePort, JSON template)
 * - flags de UA/idioma/proxy/headless
 */
final class ChromeLauncher
{
    /**
     * Inicia o WebDriver.
     * @return array{0: RemoteWebDriver, 1: string|null, 2: bool, 3: Process|null}
     */
    public function start(): array
    {
        $utils = new ProfileUtils();

        $remoteUrl = trim((string) (getenv('PANTHER_REMOTE_URL')
            ?: getenv('WEBDRIVER_URL')
            ?: getenv('CHROMEDRIVER_URL')
            ?: ($_ENV['PANTHER_REMOTE_URL'] ?? $_ENV['WEBDRIVER_URL'] ?? $_ENV['CHROMEDRIVER_URL'] ?? '')));

        $headless = !in_array((string) (getenv('PANTHER_HEADLESS') ?? $_ENV['PANTHER_HEADLESS'] ?? '1'), ['0','false','False','FALSE'], true);
        $debug = in_array((string) (getenv('FB_DEBUG') ?? $_ENV['FB_DEBUG'] ?? '0'), ['1','true','True','TRUE'], true);

        $chromeDriverBin = '';
        if ($remoteUrl === '') {
            $chromeDriverBin = trim((string) (getenv('PANTHER_CHROMEDRIVER_BINARY') ?? $_ENV['PANTHER_CHROMEDRIVER_BINARY'] ?? ''));
            if ($chromeDriverBin === '') { $chromeDriverBin = './drivers/chromedriver'; }
        }
        $chromeBin = trim((string) (getenv('PANTHER_CHROME_BINARY') ?? $_ENV['PANTHER_CHROME_BINARY'] ?? ''));

        // Perfil
        $userDataDir = null; $persistUserData = false;
        $persistDir = trim((string) (getenv('PANTHER_PROFILE_DIR') ?? $_ENV['PANTHER_PROFILE_DIR'] ?? ''));
        if ($persistDir !== '' && $remoteUrl === '') {
            $cloneRun = in_array((string) (getenv('FB_PROFILE_CLONE_RUN') ?? $_ENV['FB_PROFILE_CLONE_RUN'] ?? '0'), ['1','true','True','TRUE'], true);
            if ($cloneRun) {
                try { $cloned = $utils->cloneProfileDir($persistDir); } catch (\Throwable $e) { $cloned = null; }
                if ($cloned) { $userDataDir = $cloned; $persistUserData = false; }
                else { $userDataDir = $persistDir; $persistUserData = true; }
            } else {
                $userDataDir = $persistDir; $persistUserData = true;
            }
            if ($userDataDir && !is_dir($userDataDir)) { @mkdir($userDataDir, 0700, true); }
        }

        // Args do Chrome
        $ua = trim((string) ((getenv('FB_USER_AGENT') ?? $_ENV['FB_USER_AGENT'] ?? '') ?: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'));
        $lang = trim((string) ((getenv('FB_LANG') ?? $_ENV['FB_LANG'] ?? '') ?: 'en-US'));
        $proxyUrl = trim((string) ((getenv('PANTHER_PROXY') ?? $_ENV['PANTHER_PROXY'] ?? '') ?: (getenv('HTTPS_PROXY') ?: getenv('HTTP_PROXY') ?: '')));
        $noProxy  = trim((string) (getenv('NO_PROXY') ?? $_ENV['NO_PROXY'] ?? getenv('no_proxy') ?? ''));

        $chromeArgs = [];
        if ($headless) { $chromeArgs[] = '--headless=new'; }
        $chromeArgs = array_merge($chromeArgs, [
            '--disable-gpu', '--disable-dev-shm-usage', '--no-sandbox', '--no-first-run', '--no-default-browser-check',
            '--password-store=basic', '--use-mock-keychain', '--disable-extensions', '--remote-debugging-port=0',
            '--disable-features=Translate,AutomationControlled,PrivacySandboxAdsAPIs', '--lang=' . $lang, '--user-agent=' . $ua,
        ]);
        if ($proxyUrl !== '') { $chromeArgs[] = '--proxy-server=' . $proxyUrl; if ($noProxy !== '') { $chromeArgs[] = '--proxy-bypass-list=' . $noProxy; } }
        if ($userDataDir) { $chromeArgs[] = '--user-data-dir='.$userDataDir; $profileSub = trim((string) (getenv('FB_PROFILE_DIR_NAME') ?? $_ENV['FB_PROFILE_DIR_NAME'] ?? '')); if ($profileSub !== '') { $chromeArgs[] = '--profile-directory=' . $profileSub; } }
        else { $chromeArgs[] = '--incognito'; }

        $options = new ChromeOptions();
        $options->addArguments($chromeArgs);
        $options->setExperimentalOption('excludeSwitches', ['enable-automation']);
        $options->setExperimentalOption('useAutomationExtension', false);
        $options->setExperimentalOption('prefs', ['intl.accept_languages' => $lang]);
        if ($chromeBin !== '') { $options->setBinary($chromeBin); }
        $cap = DesiredCapabilities::chrome();
        $cap->setCapability(ChromeOptions::CAPABILITY, $options);

        if (in_array((string) (getenv('FB_DEBUG') ?? $_ENV['FB_DEBUG'] ?? '0'), ['1','true','True','TRUE'], true)) {
            error_log(sprintf('[FB] chrome remote=%s ua="%s" lang=%s proxy=%s profile=%s',
                ($remoteUrl !== '' ? $remoteUrl : 'local'), $ua, $lang, ($proxyUrl !== '' ? $proxyUrl : 'none'), ($userDataDir ?: 'incognito')
            ));
        }

        $driver = null; $chromedriverProcess = null; $lockHandle = null;
        // Lock de inicialização
        $lockWanted = in_array((string) (getenv('FB_LOCK_STARTUP') ?? $_ENV['FB_LOCK_STARTUP'] ?? '1'), ['1','true','True','TRUE'], true);
        $lockKey = $persistDir !== '' ? $persistDir : ($userDataDir ?: '');
        if ($lockWanted && $lockKey !== '') { try { $lockHandle = $utils->acquireProfileLock($lockKey, 10); } catch (\Throwable $ignored) {} }

        // Desbloqueio de locks do Chrome
        $forceUnlock = in_array((string) (getenv('FB_FORCE_UNLOCK_PROFILE') ?? $_ENV['FB_FORCE_UNLOCK_PROFILE'] ?? '0'), ['1','true','True','TRUE'], true);
        if ($forceUnlock && $userDataDir && is_dir($userDataDir)) { try { $utils->unlockChromeProfileDir($userDataDir); } catch (\Throwable $ignored) {} }

        try {
            if ($remoteUrl !== '') {
                $driver = RemoteWebDriver::create($remoteUrl, $cap, 15000, 15000);
            } else {
                $port = $utils->findFreePort(9515, 9599);
                $chromedriverProcess = new Process([$chromeDriverBin, '--port='.$port, '--log-level=SEVERE']);
                $chromedriverProcess->start();
                $utils->waitForChromeDriver('127.0.0.1', $port, 10);
                $driver = RemoteWebDriver::create('http://127.0.0.1:'.$port, $cap, 15000, 15000);
            }
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            if (strpos($msg, 'DevToolsActivePort') !== false
                || strpos($msg, 'user-data-dir') !== false
                || strpos($msg, 'cannot parse internal JSON template') !== false
                || strpos($msg, 'EOF while parsing a value') !== false) {
                // Retry com profile temporário
                $tmpProfile = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'panther-fallback-' . bin2hex(random_bytes(4));
                if (@mkdir($tmpProfile, 0700, true) || is_dir($tmpProfile)) {
                    if ($debug) { error_log(sprintf('[FB] driver create failed: %s — retrying with temp profile: %s', $msg, $tmpProfile)); }
                    $chromeArgs2 = array_values(array_filter($chromeArgs, fn($a) => strpos($a, '--user-data-dir=') !== 0));
                    $chromeArgs2[] = '--user-data-dir=' . $tmpProfile;
                    $options2 = new ChromeOptions();
                    $options2->addArguments($chromeArgs2);
                    $options2->setExperimentalOption('excludeSwitches', ['enable-automation']);
                    $options2->setExperimentalOption('useAutomationExtension', false);
                    $options2->setExperimentalOption('prefs', ['intl.accept_languages' => $lang]);
                    if ($chromeBin !== '') { $options2->setBinary($chromeBin); }
                    $cap->setCapability(ChromeOptions::CAPABILITY, $options2);
                    $userDataDir = $tmpProfile; $persistUserData = false;
                    if ($remoteUrl !== '') { $driver = RemoteWebDriver::create($remoteUrl, $cap, 15000, 15000); }
                    else {
                        $port = $utils->findFreePort(9515, 9599);
                        $chromedriverProcess = new Process([$chromeDriverBin, '--port='.$port, '--log-level=SEVERE']);
                        $chromedriverProcess->start();
                        $utils->waitForChromeDriver('127.0.0.1', $port, 10);
                        $driver = RemoteWebDriver::create('http://127.0.0.1:'.$port, $cap, 15000, 15000);
                    }
                } else { throw $e; }
            } else { throw $e; }
        } finally {
            if ($lockHandle) { $utils->releaseProfileLock($lockHandle); }
        }

        return [$driver, $userDataDir, $persistUserData, $chromedriverProcess];
    }
}

