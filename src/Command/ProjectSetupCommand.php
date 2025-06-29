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

        if (!$io->confirm('Do you want to proceed with the project setup?', true)) {
            return Command::SUCCESS;
        }

        $commands = [
            'wordpress:setup' => [
                'name' => 'WordPress Setup',
                'description' => 'Move WordPress to the correct location and organize wp-content directory'
            ],
            'project:core' => [
                'name' => 'Project Core Setup',
                'description' => 'Copy and configure core project files (composer.json, wp-config.php, etc.)'
            ],
            'plugins:migrate' => [
                'name' => 'Plugin Migration',
                'description' => 'Migrate WordPress plugins to Composer via wpackagist'
            ],
            'theme:cleanup' => [
                'name' => 'Theme Cleanup',
                'description' => 'Remove unused themes, keeping only the active theme and its parent'
            ]
        ];

        $success = true;
        $completedCommands = [];

        foreach ($commands as $command => $info) {
            $io->section($info['name']);
            $io->text($info['description']);
            
            if (!$io->confirm("Do you want to run {$info['name']}?", true)) {
                $io->text("Skipping {$info['name']}.");
                continue;
            }
            
            if (!$this->runCommand($command, $io)) {
                $io->error("Failed to run: {$command}");
                $success = false;
                
                if (!$io->confirm('Do you want to continue with the remaining commands?', false)) {
                    break;
                }
            } else {
                $io->success("Completed: {$info['name']}");
                $completedCommands[] = $info['name'];
            }
        }

        if ($success) {
            $io->success('Project setup completed successfully!');
            
            if (!empty($completedCommands)) {
                $io->text([
                    'Your WordPress project is now configured with:',
                    '',
                    ...array_map(fn($cmd) => "✓ {$cmd}", $completedCommands),
                    '',
                    'Next steps:',
                    '1. Run composer install to install dependencies',
                    '2. Configure your database settings',
                    '3. Set up your development environment'
                ]);
            } else {
                $io->text('No commands were executed. You can run individual commands as needed:');
                $io->listing(array_map(fn($cmd, $info) => "nucleus {$cmd} - {$info['description']}", array_keys($commands), array_values($commands)));
            }
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
