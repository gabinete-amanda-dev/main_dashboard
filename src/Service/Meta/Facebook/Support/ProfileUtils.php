<?php

namespace App\Service\Meta\Facebook\Support;

final class ProfileUtils
{
    public function findFreePort(int $start, int $end): int
    {
        for ($port = $start; $port <= $end; $port++) {
            $sock = @stream_socket_server("tcp://127.0.0.1:$port", $errno, $errstr);
            if ($sock) { fclose($sock); return $port; }
        }
        return $start;
    }

    public function waitForChromeDriver(string $host, int $port, int $timeoutSec = 10): void
    {
        $url = "http://{$host}:{$port}/status";
        $deadline = time() + $timeoutSec;
        while (time() < $deadline) {
            try {
                $ctx = stream_context_create(['http' => ['timeout' => 1]]);
                $json = @file_get_contents($url, false, $ctx);
                if ($json) {
                    $data = json_decode($json, true);
                    if (isset($data['value']['ready']) ? $data['value']['ready'] : ($data['ready'] ?? false)) {
                        return;
                    }
                }
            } catch (\Throwable $ignored) {}
            usleep(200000);
        }
        throw new \RuntimeException('Chromedriver did not become ready in time on '.$url);
    }

    public function removeDir(string $dir): void
    {
        if (!is_dir($dir)) { return; }
        $items = scandir($dir);
        if ($items === false) { return; }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') { continue; }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) { $this->removeDir($path); }
            else { @unlink($path); }
        }
        @rmdir($dir);
    }

    public function cloneProfileDir(string $sourceDir): ?string
    {
        if (!is_dir($sourceDir)) { return null; }
        $dest = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'fb-profile-run-' . bin2hex(random_bytes(5));
        if (!@mkdir($dest, 0700, true)) { return null; }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $file) {
            $bn = $file->getFilename();
            if (preg_match('/^Singleton(Lock|Cookie|Socket)$/', $bn)) { continue; }
            if ($bn === 'DevToolsActivePort' || $bn === 'Crashpad' || $bn === 'BrowserMetrics') { continue; }
            $rel = substr($file->getPathname(), strlen($sourceDir));
            $target = $dest . $rel;
            if ($file->isDir()) { if (!is_dir($target)) { @mkdir($target, 0700, true); } }
            else { @copy($file->getPathname(), $target); }
        }
        return $dest;
    }

    public function unlockChromeProfileDir(string $dir): void
    {
        $candidates = [
            $dir . DIRECTORY_SEPARATOR . 'SingletonLock',
            $dir . DIRECTORY_SEPARATOR . 'SingletonCookie',
            $dir . DIRECTORY_SEPARATOR . 'SingletonSocket',
            $dir . DIRECTORY_SEPARATOR . 'DevToolsActivePort',
        ];
        foreach ($candidates as $f) { if (is_file($f)) { @unlink($f); } }
        foreach (['Default','Profile 1','Profile 2'] as $sub) {
            $p = $dir . DIRECTORY_SEPARATOR . $sub;
            if (!is_dir($p)) { continue; }
            foreach (['SingletonLock','SingletonCookie','SingletonSocket','DevToolsActivePort'] as $sf) {
                $fp = $p . DIRECTORY_SEPARATOR . $sf; if (is_file($fp)) { @unlink($fp); }
            }
        }
    }

    public function acquireProfileLock(string $key, int $timeoutSec = 10)
    {
        $lockFile = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'fb-profile.lock.' . md5($key);
        $h = @fopen($lockFile, 'c'); if (!$h) { return null; }
        $deadline = time() + $timeoutSec;
        do {
            if (@flock($h, LOCK_EX | LOCK_NB)) { return $h; }
            usleep(200000);
        } while (time() < $deadline);
        return $h;
    }

    public function releaseProfileLock($handle): void
    {
        if (is_resource($handle)) { @flock($handle, LOCK_UN); @fclose($handle); }
    }
}

