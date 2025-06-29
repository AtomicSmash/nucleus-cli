<?php

namespace Nucleus\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Nucleus\Config\Defaults;

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

    private function collectPlaceholderValues(SymfonyStyle $io): void
    {
        $io->section('Project Configuration');

        // Basic project settings
        $io->text('Basic Project Settings:');
        $this->placeholders['VENDOR_NAME'] = $io->ask('Vendor name', Defaults::VENDOR_NAME);
        $this->placeholders['PROJECT_NAME'] = $io->ask('Project name', Defaults::getProjectName());
        $this->placeholders['PROJECT_DESCRIPTION'] = $io->ask('Project description', 'The WordPress site for ' . $this->placeholders['PROJECT_NAME']);
        $this->placeholders['PROJECT_LICENSE'] = $io->ask('Project license', Defaults::PROJECT_LICENSE);
        
        // Generate project slug from project name
        $generatedSlug = Defaults::generateProjectSlug($this->placeholders['PROJECT_NAME']);
        $this->placeholders['PROJECT_SLUG'] = $io->ask('Project slug', $generatedSlug);
        
        $this->placeholders['PHP_VERSION'] = $io->ask('PHP version', Defaults::PHP_VERSION);
        $this->placeholders['WORDPRESS_VERSION'] = $io->ask('WordPress version', Defaults::WORDPRESS_VERSION);
        
        // Get web root and WordPress install path first for theme selection
        $this->placeholders['WEB_ROOT'] = $io->ask('Web root path', Defaults::WEB_ROOT);
        $this->placeholders['WORDPRESS_INSTALL_PATH'] = $io->ask('WordPress install path', Defaults::WORDPRESS_INSTALL_PATH);
        $this->placeholders['WORDPRESS_TABLE_PREFIX'] = $io->ask('WordPress table prefix', Defaults::WORDPRESS_TABLE_PREFIX);
        
        // Theme selection based on the intended wp-content location
        $this->placeholders['THEME_NAME'] = $this->selectTheme($io);

        // Git configuration
        $io->text('Git Configuration:');
        $this->placeholders['GIT_REMOTE_SSH'] = $io->ask('Git remote SSH URL', Defaults::GIT_REMOTE_SSH);
        $this->placeholders['GIT_DEFAULT_BRANCH'] = $io->ask('Git default branch', Defaults::GIT_DEFAULT_BRANCH);

        // Development environment
        $io->text('Development Environment:');
        $this->placeholders['DB_NAME_DEVELOPMENT'] = $io->ask('Development database name', Defaults::DB_NAME_DEVELOPMENT);
        $this->placeholders['DB_USER_DEVELOPMENT'] = $io->ask('Development database user', Defaults::DB_USER_DEVELOPMENT);
        $this->placeholders['DB_PASSWORD_DEVELOPMENT'] = $io->ask('Development database password', Defaults::DB_PASSWORD_DEVELOPMENT);
        $this->placeholders['DB_HOST_DEVELOPMENT'] = $io->ask('Development database host', Defaults::DB_HOST_DEVELOPMENT);

        // Staging environment
        $io->text('Staging Environment:');
        $this->placeholders['STAGING_SSH_HOST'] = $io->ask('Staging SSH host', Defaults::STAGING_SSH_HOST);
        $this->placeholders['STAGING_SSH_USER'] = $io->ask('Staging SSH user', Defaults::STAGING_SSH_USER);
        $this->placeholders['STAGING_SSH_PORT'] = $io->ask('Staging SSH port', Defaults::STAGING_SSH_PORT);
        $this->placeholders['STAGING_URL'] = $io->ask('Staging URL', Defaults::STAGING_URL);
        $this->placeholders['DB_NAME_STAGING'] = $io->ask('Staging database name', Defaults::DB_NAME_STAGING);
        $this->placeholders['DB_USER_STAGING'] = $io->ask('Staging database user', Defaults::DB_USER_STAGING);
        $this->placeholders['DB_PASSWORD_STAGING'] = $io->ask('Staging database password', Defaults::DB_PASSWORD_STAGING);
        $this->placeholders['DB_HOST_STAGING'] = $io->ask('Staging database host', Defaults::DB_HOST_STAGING);

        // Production environment
        $io->text('Production Environment:');
        $this->placeholders['PRODUCTION_SSH_HOST'] = $io->ask('Production SSH host', Defaults::PRODUCTION_SSH_HOST);
        $this->placeholders['PRODUCTION_SSH_USER'] = $io->ask('Production SSH user', Defaults::PRODUCTION_SSH_USER);
        $this->placeholders['PRODUCTION_SSH_PORT'] = $io->ask('Production SSH port', Defaults::PRODUCTION_SSH_PORT);
        $this->placeholders['PRODUCTION_URL'] = $io->ask('Production URL', Defaults::PRODUCTION_URL);
        $this->placeholders['DB_NAME_PRODUCTION'] = $io->ask('Production database name', Defaults::DB_NAME_PRODUCTION);
        $this->placeholders['DB_USER_PRODUCTION'] = $io->ask('Production database user', Defaults::DB_USER_PRODUCTION);
        $this->placeholders['DB_PASSWORD_PRODUCTION'] = $io->ask('Production database password', Defaults::DB_PASSWORD_PRODUCTION);
        $this->placeholders['DB_HOST_PRODUCTION'] = $io->ask('Production database host', Defaults::DB_HOST_PRODUCTION);
        $this->placeholders['KINSTA_FOLDER'] = $io->ask('Kinsta folder name', Defaults::KINSTA_FOLDER);

        // WordPress security keys
        $io->text('WordPress Security Keys:');
        if ($io->confirm('Generate random WordPress security keys?', true)) {
            $keys = Defaults::generateWordPressKeys();
            foreach ($keys as $key => $value) {
                $this->placeholders[$key] = $value;
            }
        } else {
            $this->placeholders['AUTH_KEY'] = $io->ask('WordPress Auth Key', Defaults::AUTH_KEY);
            $this->placeholders['SECURE_AUTH_KEY'] = $io->ask('WordPress Secure Auth Key', Defaults::SECURE_AUTH_KEY);
            $this->placeholders['LOGGED_IN_KEY'] = $io->ask('WordPress Logged In Key', Defaults::LOGGED_IN_KEY);
            $this->placeholders['NONCE_KEY'] = $io->ask('WordPress Nonce Key', Defaults::NONCE_KEY);
            $this->placeholders['AUTH_SALT'] = $io->ask('WordPress Auth Salt', Defaults::AUTH_SALT);
            $this->placeholders['SECURE_AUTH_SALT'] = $io->ask('WordPress Secure Auth Salt', Defaults::SECURE_AUTH_SALT);
            $this->placeholders['LOGGED_IN_SALT'] = $io->ask('WordPress Logged In Salt', Defaults::LOGGED_IN_SALT);
            $this->placeholders['NONCE_SALT'] = $io->ask('WordPress Nonce Salt', Defaults::NONCE_SALT);
        }

        // External services
        $io->text('External Services:');
        $this->placeholders['MAILTRAP_USERNAME'] = $io->ask('Mailtrap username', Defaults::MAILTRAP_USERNAME);
        $this->placeholders['MAILTRAP_PASSWORD'] = $io->ask('Mailtrap password', Defaults::MAILTRAP_PASSWORD);
        $this->placeholders['RELEASE_BELT_USERNAME'] = $io->ask('Release Belt username', Defaults::RELEASE_BELT_USERNAME);
        $this->placeholders['RELEASE_BELT_PASSWORD'] = $io->ask('Release Belt password', Defaults::RELEASE_BELT_PASSWORD);
        $this->placeholders['ACF_USERNAME'] = $io->ask('ACF username', Defaults::ACF_USERNAME);
        $this->placeholders['ACF_PASSWORD'] = $io->ask('ACF password', Defaults::ACF_PASSWORD);
    }

    private function copyTemplateFiles(SymfonyStyle $io): bool
    {
        $io->section('Copying Core Project Files');

        $templateFiles = [
            '.config/wp-configs/*' => '.config/wp-configs/',
            'public/wp-config.php' => $this->placeholders['WEB_ROOT'] . 'wp-config.php',
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

    private function selectTheme(SymfonyStyle $io): string
    {
        $io->section('Theme Selection');
        
        // Search for themes in current WordPress installation locations
        $themes = $this->findThemesInWordPressInstallations();
        
        if (empty($themes)) {
            $io->text('No themes found in current WordPress installations.');
            $io->text('Searched in: ' . implode(', ', Defaults::WORDPRESS_SEARCH_PATHS));
            return $io->ask('Enter theme name', Defaults::getThemeName());
        }
        
        $themeNames = array_map(function($theme) {
            return basename($theme);
        }, $themes);
        
        // Add manual entry option
        $options = array_merge($themeNames, ['Enter custom theme name']);
        
        $io->text('Available themes found in current WordPress installation:');
        $io->listing($themeNames);
        
        $choice = $io->choice('Select a theme or enter custom name', $options);
        
        if ($choice === 'Enter custom theme name') {
            return $io->ask('Enter custom theme name', Defaults::getThemeName());
        }
        
        return $choice;
    }
    
    private function findThemesInWordPressInstallations(): array
    {
        $themes = [];
        
        foreach (Defaults::WORDPRESS_SEARCH_PATHS as $path) {
            $fullPath = $this->projectRoot . '/' . $path;
            $wpContentPath = $fullPath . '/wp-content/themes';
            
            // Check if this is a WordPress installation
            if (file_exists($fullPath . '/wp-config.php') || file_exists($fullPath . '/wp-load.php')) {
                if (is_dir($wpContentPath)) {
                    $foundThemes = glob($wpContentPath . '/*', GLOB_ONLYDIR);
                    $themes = array_merge($themes, $foundThemes);
                }
            }
        }
        
        return $themes;
    }
} 
