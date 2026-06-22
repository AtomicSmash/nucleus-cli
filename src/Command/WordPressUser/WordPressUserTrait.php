<?php

namespace Nucleus\Command\WordPressUser;

use Nucleus\Service\EnvFileLoader;
use Nucleus\Service\WordPressProjectDetector;
use Nucleus\Service\WpCliRunner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

trait WordPressUserTrait
{
    private function initializeWordPressUserContext(SymfonyStyle $io): ?array
    {
        $projectRoot = getcwd();
        $detector = new WordPressProjectDetector($projectRoot);

        if (!$detector->hasWpCliConfig()) {
            $io->error('This does not appear to be a WordPress project. Expected wp-cli.yml in the project root.');
            return null;
        }

        $environment = $io->choice(
            'Which environment?',
            ['development', 'staging', 'production'],
            'development'
        );

        $envLoader = new EnvFileLoader($projectRoot);

        if ($environment !== WpCliRunner::ENV_DEVELOPMENT && !$envLoader->exists()) {
            $io->error('A .env file is required for staging and production environments.');
            return null;
        }

        try {
            $runner = WpCliRunner::fromEnvironment($environment, $detector, $envLoader);
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());
            return null;
        }

        if (!$runner->isWpCliAvailable()) {
            $io->error('WP-CLI is not available locally. Please install wp-cli before running commands against development.');
            return null;
        }

        return [
            'detector' => $detector,
            'envLoader' => $envLoader,
            'runner' => $runner,
            'environment' => $environment,
        ];
    }

    private function failure(): int
    {
        return Command::FAILURE;
    }

    private function success(): int
    {
        return Command::SUCCESS;
    }
}
