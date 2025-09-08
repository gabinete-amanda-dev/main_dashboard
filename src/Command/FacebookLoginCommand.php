<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

#[AsCommand(name: 'fb:login', description: 'Open an undetected Chrome to log into Facebook and persist the session in the profile directory')]
class FacebookLoginCommand extends Command
{
    protected static $defaultName = 'fb:login';

    protected function configure(): void
    {
        $this
            ->addOption('profile-dir', null, InputOption::VALUE_REQUIRED, 'Chrome user-data-dir to persist the session (falls back to var/google-chrome)')
            ->addOption('email', null, InputOption::VALUE_OPTIONAL, 'FACEBOOK_EMAIL to prefill and submit')
            ->addOption('password', null, InputOption::VALUE_OPTIONAL, 'FACEBOOK_PASSWORD to prefill and submit')
            ->addOption('python-bin', null, InputOption::VALUE_OPTIONAL, 'Python interpreter to use (defaults to FB_PYTHON_BIN or python3)')
            ->addOption('keep-open', null, InputOption::VALUE_NONE, 'Keep Chrome window open after login')
            ->addOption('hold-seconds', null, InputOption::VALUE_OPTIONAL, 'Keep the window open for N seconds')
            ->addOption('home', null, InputOption::VALUE_OPTIONAL, 'Override HOME for the Python process (useful when running as _www)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projectRoot = \dirname(__DIR__, 2);
        $scriptBin   = $projectRoot . '/bin/fb_login_ucd.py';
        $scriptTools = $projectRoot . '/tools/fb_login_ucd.py';
        $script = is_file($scriptBin) ? $scriptBin : (is_file($scriptTools) ? $scriptTools : null);
        if ($script === null) {
            $output->writeln('<error>fb_login_ucd.py not found in bin/ or tools/</error>');
            return Command::FAILURE;
        }

        $profileDir = (string) ($input->getOption('profile-dir')
            ?: getenv('PANTHER_PROFILE_DIR')
            ?: ($_ENV['PANTHER_PROFILE_DIR'] ?? ''));
        if ($profileDir === '') {
            $profileDir = $projectRoot . '/var/google-chrome';
        }
        if (!is_dir($profileDir)) {
            @mkdir($profileDir, 0700, true);
        }

        $email = (string) ($input->getOption('email') ?: getenv('FACEBOOK_EMAIL') ?: ($_ENV['FACEBOOK_EMAIL'] ?? ''));
        $password = (string) ($input->getOption('password') ?: getenv('FACEBOOK_PASSWORD') ?: ($_ENV['FACEBOOK_PASSWORD'] ?? ''));
        $python = (string) ($input->getOption('python-bin') ?: getenv('FB_PYTHON_BIN') ?: ($_ENV['FB_PYTHON_BIN'] ?? 'python3'));
        $keepOpen = (bool) $input->getOption('keep-open');
        $hold = (string) ($input->getOption('hold-seconds') ?? '');
        $home = (string) ($input->getOption('home') ?: getenv('FB_PYTHON_HOME') ?: ($_ENV['FB_PYTHON_HOME'] ?? ''));
        if ($home !== '' && !is_dir($home)) { @mkdir($home, 0700, true); }

        $env = [
            'PANTHER_PROFILE_DIR' => $profileDir,
            'FACEBOOK_EMAIL' => $email,
            'FACEBOOK_PASSWORD' => $password,
        ];
        if ($keepOpen) { $env['FB_UCD_KEEP_OPEN'] = '1'; }
        if ($hold !== '') { $env['FB_UCD_HOLD_SECONDS'] = $hold; }
        if ($home !== '') { $env['HOME'] = $home; }

        $process = new Process([$python, $script], \dirname($script), $env);
        $process->setTimeout(300);
        $process->setIdleTimeout(300);

        // Mostrar saída do helper em tempo real
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });

        return $process->isSuccessful() ? Command::SUCCESS : Command::FAILURE;
    }
}

