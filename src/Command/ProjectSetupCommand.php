<?php

namespace Nucleus\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

class ProjectSetupCommand extends Command
{
    protected static $defaultName = 'project:setup';

    protected function configure(): void
    {
        $this
            ->setDescription('Complete project setup - runs all setup commands')
            ->setHelp('This command runs all the necessary setup commands to configure a WordPress project.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Nucleus Project Setup');
        $io->text('This will run all the necessary setup commands for your WordPress project.');

        if (!$io->confirm('Do you want to proceed with the full project setup?', true)) {
            return Command::SUCCESS;
        }

        $commands = [
            'wordpress:setup' => 'WordPress Setup',
            'template:setup' => 'Template Setup',
            'plugins:migrate' => 'Plugin Migration'
        ];

        $success = true;

        foreach ($commands as $command => $description) {
            $io->section($description);
            
            if (!$this->runCommand($command, $io)) {
                $io->error("Failed to run: {$command}");
                $success = false;
                
                if (!$io->confirm('Do you want to continue with the remaining commands?', false)) {
                    break;
                }
            } else {
                $io->success("Completed: {$description}");
            }
        }

        if ($success) {
            $io->success('Project setup completed successfully!');
            $io->text([
                'Your WordPress project is now configured with:',
                '',
                '✓ WordPress moved to the correct location',
                '✓ Template files copied and configured',
                '✓ Plugins migrated to Composer',
                '',
                'Next steps:',
                '1. Run composer install to install dependencies',
                '2. Configure your database settings',
                '3. Set up your development environment'
            ]);
        } else {
            $io->warning('Project setup completed with some errors. Please review the output above.');
        }

        return $success ? Command::SUCCESS : Command::FAILURE;
    }

    private function runCommand(string $command, SymfonyStyle $io): bool
    {
        $process = Process::fromShellCommandline("nucleus {$command}");
        $process->setTty(true);
        $process->setTimeout(null);

        try {
            $process->run(function ($type, $buffer) use ($io) {
                if (Process::ERR === $type) {
                    $io->write($buffer);
                } else {
                    $io->write($buffer);
                }
            });

            return $process->isSuccessful();
        } catch (\Exception $e) {
            $io->error('Command failed: ' . $e->getMessage());
            return false;
        }
    }
} 
