<?php

namespace Nucleus\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ProjectCoreCommand extends Command
{
    protected static $defaultName = 'project:core';
    private string $projectRoot;
    private array $placeholders = [];

    protected function configure(): void
    {
        $this
            ->setDescription('Copy and configure core project files')
            ->setHelp('This command copies core project files from the nucleus package and replaces placeholders with user input.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->projectRoot = getcwd();

        $io->title('Project Core Setup');

        // Check if we're in a project directory
        if (!$this->isProjectDirectory($io)) {
            return Command::FAILURE;
        }

        // Collect user input for placeholders
        $this->collectPlaceholderValues($io);

        // Copy template files
        if (!$this->copyTemplateFiles($io)) {
            return Command::FAILURE;
        }

        $io->success('Project core setup completed successfully!');

        return Command::SUCCESS;
    }

    private function isProjectDirectory(SymfonyStyle $io): bool
    {
        if (!file_exists($this->projectRoot . '/composer.json')) {
            $io->error('No composer.json found. Please run this command from your project root.');
            return false;
        }
        return true;
    }

    private function collectPlaceholderValues(SymfonyStyle $io): void
    {
        $io->section('Project Configuration');

        $this->placeholders = [
            'VENDOR_NAME' => $io->ask('Vendor name', 'mycompany'),
            'PROJECT_NAME' => $io->ask('Project name', basename($this->projectRoot)),
            'PROJECT_DESCRIPTION' => $io->ask('Project description', 'WordPress project'),
            'PHP_VERSION' => $io->ask('PHP version', '8.1'),
            'WORDPRESS_VERSION' => $io->ask('WordPress version', '6.4'),
            'WEB_ROOT' => $io->ask('Web root path', 'public/'),
            'THEME_NAME' => $io->ask('Theme name', basename($this->projectRoot))
        ];
    }

    private function copyTemplateFiles(SymfonyStyle $io): bool
    {
        $io->section('Copying Core Project Files');

        $templateFiles = [
            '.config/wp-configs/*' => '.config/wp-configs/',
            'public/wp-config.php' => 'public/wp-config.php',
            '.editorconfig' => '.editorconfig',
            '.gitignore' => '.gitignore',
            '.valetrc' => '.valetrc',
            'composer.json' => 'composer.json',
            'herd.yml' => 'herd.yml'
        ];

        foreach ($templateFiles as $templatePath => $targetPath) {
            $fullTemplatePath = __DIR__ . '/../../templates/' . $templatePath;
            $fullTargetPath = $this->projectRoot . '/' . $targetPath;

            if (strpos($templatePath, '*') !== false) {
                // Handle wildcard paths
                $templateDir = dirname($fullTemplatePath);
                $pattern = basename($fullTemplatePath);
                
                if (is_dir($templateDir)) {
                    $files = glob($templateDir . '/' . $pattern);
                    foreach ($files as $file) {
                        $relativePath = str_replace($templateDir . '/', '', $file);
                        $this->copyTemplateFile($file, $fullTargetPath . $relativePath, $io);
                    }
                }
            } else {
                $this->copyTemplateFile($fullTemplatePath, $fullTargetPath, $io);
            }
        }

        return true;
    }

    private function copyTemplateFile(string $sourcePath, string $targetPath, SymfonyStyle $io): void
    {
        if (!file_exists($sourcePath)) {
            $io->warning("Template file not found: {$sourcePath}");
            return;
        }

        $targetDir = dirname($targetPath);
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        if (file_exists($targetPath)) {
            $choice = $io->choice(
                "File already exists: " . basename($targetPath) . ". What would you like to do?",
                ['overwrite', 'backup and replace', 'skip'],
                'backup and replace'
            );

            switch ($choice) {
                case 'overwrite':
                    break;
                case 'backup and replace':
                    $backupPath = $targetPath . '.backup.' . date('Y-m-d-H-i-s');
                    copy($targetPath, $backupPath);
                    $io->text("Backed up existing file to: {$backupPath}");
                    break;
                case 'skip':
                    $io->text('Skipping: ' . basename($targetPath));
                    return;
            }
        }

        $content = file_get_contents($sourcePath);
        $content = $this->replacePlaceholders($content);
        file_put_contents($targetPath, $content);

        $io->text('Copied: ' . basename($targetPath));
    }

    private function replacePlaceholders(string $content): string
    {
        foreach ($this->placeholders as $placeholder => $value) {
            $content = str_replace('{{' . $placeholder . '}}', $value, $content);
        }
        return $content;
    }
} 
