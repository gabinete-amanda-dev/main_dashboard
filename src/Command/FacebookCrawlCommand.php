<?php

namespace App\Command;

use App\Service\Meta\Facebook\Crawler\PublicCrawler;
use App\Service\Meta\Facebook\Scraper\Crawler as AuthenticatedScraper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:facebook:crawl',
    description: 'Crawl a Facebook page using scraper (authenticated) or crawler (public) and dump JSON',
)]
class FacebookCrawlCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('pageId', InputArgument::REQUIRED, 'Page username or numeric ID (e.g., amandavettorazzo.sp)')
            ->addOption('mode', null, InputOption::VALUE_REQUIRED, 'Mode: scraper (default) or crawler', 'scraper')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Post limit (scraper mode)', '25')
            ->addOption('scrolls', null, InputOption::VALUE_REQUIRED, 'Max scrolls (scraper mode)', '8')
            ->addOption('wait', null, InputOption::VALUE_REQUIRED, 'Wait between scrolls in ms (scraper mode)', '500')
            ->addOption('pretty', null, InputOption::VALUE_NONE, 'Pretty-print JSON output');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $pageId = (string) $input->getArgument('pageId');
        $mode = strtolower((string) $input->getOption('mode'));
        $limit = (int) $input->getOption('limit');
        $scrolls = (int) $input->getOption('scrolls');
        $wait = (int) $input->getOption('wait');
        $pretty = (bool) $input->getOption('pretty');

        try {
            if ($mode === 'crawler') {
                $data = (new PublicCrawler())->crawlPage($pageId);
            } else {
                $data = (new AuthenticatedScraper())->crawlPage($pageId, [
                    'limit' => $limit,
                    'scrolls' => $scrolls,
                    'wait' => $wait,
                ]);
            }
        } catch (\Throwable $e) {
            $io->error('Crawl failed: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if ($pretty) { $flags |= JSON_PRETTY_PRINT; }
        $output->writeln(json_encode($data, $flags));
        return Command::SUCCESS;
    }
}

