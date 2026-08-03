<?php

namespace Nucleus\Command;

use Nucleus\Service\ProjectConfig;
use Symfony\Component\Console\Style\SymfonyStyle;

trait RequiresNotOnboardedTrait
{
    private function failIfOnboarded(SymfonyStyle $io): bool
    {
        $projectConfig = new ProjectConfig();
        if (!$projectConfig->isOnboarded()) {
            return false;
        }

        $io->error(
            'This project is already onboarded. Set `onboarded: false` in `.nucleus/config.yml` to re-run onboarding commands.'
        );

        return true;
    }
}
