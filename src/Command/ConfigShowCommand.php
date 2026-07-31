<?php

namespace Nucleus\Command;

use Nucleus\Service\GlobalConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

class ConfigShowCommand extends Command
{
    protected static $defaultName = 'config:show';

    protected function configure(): void
    {
        $this
            ->setDescription('Show the global Nucleus config')
            ->setHelp('Displays the contents of the global Nucleus config file.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $configService = new GlobalConfig();
        $path = $configService->getPath();

        $io->title('Nucleus Global Config');
        $io->text("Path: {$path}");

        if (!$configService->exists()) {
            $io->warning('No global config file found.');
            $io->text('Run `nucleus config:setup` to create one.');
            return Command::SUCCESS;
        }

        $config = $configService->load();
        if (empty($config)) {
            $io->warning('Global config file exists but is empty.');
            return Command::SUCCESS;
        }

        $io->writeln(Yaml::dump($config, 4, 2));

        return Command::SUCCESS;
    }
}
