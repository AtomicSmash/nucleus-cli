<?php

namespace Nucleus\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Process\Process;
use Nucleus\Config\Defaults;

class WordPressSetupCommand extends Command
{
    protected static $defaultName = 'wordpress:setup';
    private string $projectRoot;

    protected function configure(): void
    {
        $this
            ->setDescription('Setup WordPress installation with proper directory structure')
            ->setHelp('This command moves WordPress to the correct location and moves wp-content directory.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->projectRoot = getcwd();

        $io->title('WordPress Setup');

        // Check if we're in a project directory
        if (!$this->isProjectDirectory($io)) {
            return Command::FAILURE;
        }

        // Prompt for web root first
        $webRoot = $this->promptWebRoot($io);
        if (!$webRoot) {
            return Command::FAILURE;
        }

        // Find WordPress installation
        $wordpressPath = $this->findWordPressInstallation($io);
        if (!$wordpressPath) {
            return Command::FAILURE;
        }

        // Prompt for WordPress target location relative to web root
        $targetPath = $this->promptWordPressLocation($io, $webRoot);
        if (!$targetPath) {
            return Command::FAILURE;
        }

        // Move WordPress installation
        if (!$this->moveWordPressInstallation($wordpressPath, $targetPath, $io)) {
            return Command::FAILURE;
        }

        // Move wp-content directory
        if (!$this->moveWpContentDirectory($targetPath, $webRoot, $io)) {
            return Command::FAILURE;
        }

        // Remove WordPress core files from target
        $this->removeWordPressCoreFiles($targetPath, $io);

        $io->success('WordPress setup completed successfully!');
        $io->text([
            'WordPress has been moved to the correct location.',
            '`wp-content` directory has been moved to `' . $webRoot . 'wp-content`.',
            '',
            'Next steps:',
            '1. Run `nucleus project:core` to copy and configure template files',
            '2. Run composer install to install dependencies'
        ]);

        return Command::SUCCESS;
    }

    private function isProjectDirectory(SymfonyStyle $io): bool
    {
        // Check if we're in a valid directory (not requiring composer.json to exist)
        if (!is_dir($this->projectRoot)) {
            $io->error('Invalid project directory.');
            return false;
        }
        
        // If composer.json already exists, warn the user
        if (file_exists($this->projectRoot . '/composer.json')) {
            $io->note('A composer.json file already exists in this directory. It will be handled during the setup process.');
        }
        
        return true;
    }

    private function findWordPressInstallation(SymfonyStyle $io): ?string
    {
        foreach (Defaults::WORDPRESS_SEARCH_PATHS as $path) {
            $fullPath = $this->projectRoot . '/' . $path;
            if (file_exists($fullPath . '/wp-config.php') || file_exists($fullPath . '/wp-load.php')) {
                $io->text("Found WordPress installation at: {$path}");
                return $fullPath;
            }
        }

        $io->error('WordPress installation not found. Please ensure WordPress is installed in one of these locations:');
        $io->listing(Defaults::WORDPRESS_SEARCH_PATHS);
        return null;
    }

    private function promptWebRoot(SymfonyStyle $io): ?string
    {
        $defaultRoot = Defaults::WEB_ROOT;
        $question = new Question("What is the web root for this project? (default: {$defaultRoot}): ", $defaultRoot);
        $webRoot = $io->askQuestion($question);

        if (is_dir($this->projectRoot . '/' . $webRoot) && !$io->confirm("Directory " . $webRoot . " already exists. Do you want to continue?", false)) {
            return null;
        }

        return $webRoot;
    }

    private function promptWordPressLocation(SymfonyStyle $io, string $webRoot): ?string
    {
        $defaultPath = Defaults::WORDPRESS_INSTALL_PATH;
        $question = new Question("What path inside the web root would you like to install WordPress? (default: {$defaultPath}): ", $defaultPath);
        $targetPath = $io->askQuestion($question);

        // Construct the full path inside the web root
        $fullTargetPath = $this->projectRoot . '/' . $webRoot . $targetPath;

        if (is_dir($fullTargetPath) && !$io->confirm("Directory " . $webRoot . "{$targetPath} already exists. Do you want to continue?", false)) {
            return null;
        }

        return $fullTargetPath;
    }

    private function moveWordPressInstallation(string $sourcePath, string $targetPath, SymfonyStyle $io): bool
    {
        if ($sourcePath === $targetPath) {
            $io->text('WordPress is already in the target location.');
            return true;
        }

        $io->text("Moving WordPress from " . basename($sourcePath) . " to " . basename($targetPath) . "...");

        // Create target directory if it doesn't exist
        if (!is_dir($targetPath)) {
            mkdir($targetPath, 0755, true);
        }

        // Copy WordPress files
        $process = Process::fromShellCommandline("cp -r {$sourcePath}/* {$targetPath}/");
        $process->run();

        if (!$process->isSuccessful()) {
            $io->error('Failed to move WordPress installation: ' . $process->getErrorOutput());
            return false;
        }

        $io->text('WordPress installation moved successfully.');
        return true;
    }

    private function moveWpContentDirectory(string $wordpressPath, string $webRoot, SymfonyStyle $io): bool
    {
        $wpContentSource = $wordpressPath . '/wp-content';
        $wpContentTarget = $this->projectRoot . '/' . $webRoot . 'wp-content';

        if (!is_dir($wpContentSource)) {
            $io->warning('`wp-content` directory not found in WordPress installation.');
            return true;
        }

        if (is_dir($wpContentTarget)) {
            $choice = $io->choice(
                "`wp-content` directory already exists at `{$webRoot}wp-content`. What would you like to do?",
                ['overwrite', 'backup and replace', 'skip'],
                'backup and replace'
            );

            switch ($choice) {
                case 'overwrite':
                    $this->recursiveDelete($wpContentTarget);
                    break;
                case 'backup and replace':
                    $backupPath = $wpContentTarget . '.backup.' . date('Y-m-d-H-i-s');
                    rename($wpContentTarget, $backupPath);
                    $io->text("Backed up existing wp-content to: {$backupPath}");
                    break;
                case 'skip':
                    $io->text('Skipping wp-content move.');
                    return true;
            }
        }

        $io->text("Moving `wp-content` directory to `{$webRoot}wp-content`...");
        
        // Create web root directory if it doesn't exist
        if (!is_dir($this->projectRoot . '/' . $webRoot)) {
            mkdir($this->projectRoot . '/' . $webRoot, 0755, true);
        }

        $process = Process::fromShellCommandline("cp -r {$wpContentSource} {$wpContentTarget}");
        $process->run();

        if (!$process->isSuccessful()) {
            $io->error('Failed to move `wp-content` directory: ' . $process->getErrorOutput());
            return false;
        }

        $io->text('`wp-content` directory moved successfully.');
        return true;
    }

    private function removeWordPressCoreFiles(string $wordpressPath, SymfonyStyle $io): void
    {
        $io->text('Removing WordPress core files...');

        $coreFiles = [
            'wp-config.php',
            'wp-config-sample.php',
            'wp-load.php',
            'wp-blog-header.php',
            'wp-cron.php',
            'wp-links-opml.php',
            'wp-mail.php',
            'wp-settings.php',
            'wp-signup.php',
            'wp-trackback.php',
            'xmlrpc.php',
            'index.php',
            'license.txt',
            'readme.html',
            'wp-admin',
            'wp-includes'
        ];

        foreach ($coreFiles as $file) {
            $filePath = $wordpressPath . '/' . $file;
            if (file_exists($filePath)) {
                if (is_dir($filePath)) {
                    $this->recursiveDelete($filePath);
                } else {
                    unlink($filePath);
                }
            }
        }

        $io->text('WordPress core files removed.');
    }

    private function recursiveDelete(string $dir): void
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . "/" . $object)) {
                        $this->recursiveDelete($dir . "/" . $object);
                    } else {
                        unlink($dir . "/" . $object);
                    }
                }
            }
            rmdir($dir);
        }
    }
} 
