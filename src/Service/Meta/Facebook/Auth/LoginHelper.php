<?php

namespace App\Service\Meta\Facebook\Auth;

use Facebook\WebDriver\WebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Symfony\Component\Panther\Client;

final class LoginHelper
{
    public function maybeLogin(?WebDriver $wd, string $email, string $password, int $timeoutSec = 25): void
    {
        if ($email === '' || $password === '' || !$wd) { return; }
        try {
            try { $url = (string) $wd->getCurrentURL(); } catch (\Throwable $ignored) { $url = ''; }
            if (strpos($url, 'facebook.com') === false) { $wd->get('https://www.facebook.com/'); }
            if ($this->hasCookie($wd, 'c_user')) { return; }
            $wd->get('https://www.facebook.com/login');
            try {
                $consentButtonXpath = "//button[contains(., 'Allow all cookies') or contains(., 'Accept all cookies') or contains(., 'Permitir todos os cookies') or contains(., 'Aceitar todos os cookies') or contains(., 'Only allow essential') or contains(., 'Apenas os essenciais')]";
                $wd->wait(5, 250)->until(WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::xpath($consentButtonXpath)));
                $wd->findElement(WebDriverBy::xpath($consentButtonXpath))->click();
            } catch (\Throwable $ignored) {}
            $emailEl = null; $passEl = null; $loginBtn = null;
            try { $emailEl = $wd->findElement(WebDriverBy::cssSelector('#email')); } catch (\Throwable) {}
            if (!$emailEl) { try { $emailEl = $wd->findElement(WebDriverBy::cssSelector('input[name="email"]')); } catch (\Throwable) {} }
            try { $passEl = $wd->findElement(WebDriverBy::cssSelector('#pass')); } catch (\Throwable) {}
            if (!$passEl) { try { $passEl = $wd->findElement(WebDriverBy::cssSelector('input[name="pass"]')); } catch (\Throwable) {} }
            try { $loginBtn = $wd->findElement(WebDriverBy::cssSelector('button[name="login"]')); } catch (\Throwable) {}
            if (!$loginBtn) { try { $loginBtn = $wd->findElement(WebDriverBy::cssSelector('input[name="login"]')); } catch (\Throwable) {} }
            if ($emailEl && $passEl && $loginBtn) {
                try { $emailEl->clear(); } catch (\Throwable) {}
                $emailEl->sendKeys($email);
                try { $passEl->clear(); } catch (\Throwable) {}
                $passEl->sendKeys($password);
                $loginBtn->click();
                $wd->wait($timeoutSec, 500)->until(function (WebDriver $d) {
                    try {
                        $cookies = $d->manage()->getCookies();
                        foreach ($cookies as $c) { if (method_exists($c,'getName') && $c->getName()==='c_user' && $c->getValue()) { return true; } }
                        return false;
                    } catch (\Throwable) { return false; }
                });
            }
            try { $twoFa = $wd->findElement(WebDriverBy::cssSelector('input[name="approvals_code"]')); if ($twoFa) { throw new \RuntimeException('Facebook requires 2FA approval.'); } } catch (\Throwable $ignored) {}
        } catch (\Throwable $e) {
            // ignore login errors
        }
    }

    public function maybeLoginInPageDialog(?WebDriver $wd, string $email, string $password, int $timeoutSec = 20): void
    {
        if ($email === '' || $password === '' || !$wd) { return; }
        try {
            if ($this->hasCookie($wd, 'c_user')) { return; }
            $dialog = null;
            try { $dialog = $wd->findElement(WebDriverBy::cssSelector('div[role="dialog"]')); } catch (\Throwable $ignored) {}
            if (!$dialog) { return; }
            $emailEl = null; $passEl = null; $loginBtn = null;
            try { $emailEl = $dialog->findElement(WebDriverBy::cssSelector('input[name="email"], input[type="text"]')); } catch (\Throwable $ignored) {}
            try { $passEl = $dialog->findElement(WebDriverBy::cssSelector('input[name="pass"], input[type="password"]')); } catch (\Throwable $ignored) {}
            try { $loginBtn = $dialog->findElement(WebDriverBy::cssSelector('button[name="login"], button[type="submit"]')); } catch (\Throwable $ignored) {}
            if ($emailEl && $passEl && $loginBtn) {
                try { $emailEl->clear(); } catch (\Throwable) {}
                $emailEl->sendKeys($email);
                try { $passEl->clear(); } catch (\Throwable) {}
                $passEl->sendKeys($password);
                $loginBtn->click();
                $start = time();
                while (time() - $start < $timeoutSec) {
                    try {
                        $cookies = $wd->manage()->getCookies();
                        foreach ($cookies as $c) { if (method_exists($c,'getName') && $c->getName()==='c_user' && $c->getValue()) { return; } }
                    } catch (\Throwable $ignored) {}
                    try { $wd->findElement(WebDriverBy::cssSelector('div[role="dialog"]')); } catch (\Throwable $e) { return; }
                    usleep(250000);
                }
            }
        } catch (\Throwable $ignored) {}
    }

    public function isTwoFactorChallenge(?WebDriver $wd): bool
    {
        if (!$wd) { return false; }
        try {
            $url = (string) $wd->getCurrentURL();
            if (stripos($url, '/two_step_verification/') !== false
                || stripos($url, 'approvals_code') !== false
                || stripos($url, '/checkpoint/') !== false) { return true; }
        } catch (\Throwable $ignored) {}
        try { $wd->findElement(WebDriverBy::cssSelector('input[name="approvals_code"]')); return true; } catch (\Throwable $ignored) {}
        return false;
    }

    public function pantherLogin(Client $client, string $email, string $password, int $timeout = 20): void
    {
        if ($email === '' || $password === '') { return; }
        try {
            try { if ($this->hasCookie($client->getWebDriver(), 'c_user')) { return; } } catch (\Throwable $ignored) {}
            $client->request('GET', 'https://m.facebook.com/login');
            try {
                $client->waitFor('button', 5);
                $crawler = $client->getCrawler();
                foreach (['Allow all cookies','Accept all cookies','Aceitar todos os cookies','Permitir todos os cookies'] as $label) {
                    $btn = $crawler->selectButton($label);
                    if ($btn->count()) { $client->click($btn->link()); break; }
                }
            } catch (\Throwable $ignored) {}
            try {
                $client->waitFor('form', 10);
                $client->submitForm('Log In', [ 'email' => $email, 'pass' => $password ]);
            } catch (\Throwable $e) {
                try {
                    $crawler = $client->getCrawler();
                    $form = $crawler->filter('form')->first()->form([ 'email' => $email, 'pass' => $password ]);
                    $client->submit($form);
                } catch (\Throwable $ignored) {}
            }
            try { $client->waitFor('body', $timeout); } catch (\Throwable $ignored) {}
        } catch (\Throwable $ignored) {}
    }

    public function hasCookie(?WebDriver $wd, string $name): bool
    {
        if (!$wd) { return false; }
        try {
            $cookies = $wd->manage()->getCookies();
            foreach ($cookies as $c) { if (method_exists($c, 'getName') && $c->getName() === $name) { return (bool) $c->getValue(); } }
        } catch (\Throwable $e) {}
        return false;
    }
}

