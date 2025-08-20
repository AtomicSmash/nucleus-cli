<?php

namespace Nucleus\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Nucleus\Config\Defaults;

class ProjectConfigCommand extends Command
{
    protected static $defaultName = 'project:config';
    private string $projectRoot;
    private static array $projectData = [];

    protected function configure(): void
    {
        $this
            ->setDescription('Configure project settings')
            ->setHelp('This command collects and stores project configuration that can be used by other commands.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->projectRoot = getcwd();

        $io->title('Project Configuration');

        // Check if we're in a project directory
        if (!$this->isProjectDirectory($io)) {
            return Command::FAILURE;
        }

        // Collect project configuration
        $this->collectProjectData($io);

        $io->success('Project configuration completed successfully!');
        $io->text([
            'Project configuration has been stored and is available for other commands.',
            '',
            'You can now run other commands that will use this configuration:',
            '• nucleus wordpress:setup',
            '• nucleus project:core',
            '• nucleus plugins:migrate',
            '• nucleus theme:cleanup'
        ]);

        return Command::SUCCESS;
    }

    private function isProjectDirectory(SymfonyStyle $io): bool
    {
        if (!is_dir($this->projectRoot)) {
            $io->error('Invalid project directory.');
            return false;
        }
        
        if (file_exists($this->projectRoot . '/composer.json')) {
            $io->note('A composer.json file already exists in this directory. It will be handled during the setup process.');
        }
        
        return true;
    }

    private function collectProjectData(SymfonyStyle $io): void
    {
        $io->section('Project Configuration');

        // Basic project settings
        $io->text('Basic Project Settings:');
        self::$projectData['VENDOR_NAME'] = $io->ask('Vendor name', Defaults::VENDOR_NAME);
        self::$projectData['PROJECT_NAME'] = $io->ask('Project name', Defaults::getProjectName());
        self::$projectData['PROJECT_DESCRIPTION'] = $io->ask('Project description', 'The WordPress site for ' . self::$projectData['PROJECT_NAME']);
        self::$projectData['PROJECT_LICENSE'] = $io->ask('Project license', Defaults::PROJECT_LICENSE);
        
        // Generate project slug from project name
        $generatedSlug = Defaults::generateProjectSlug(self::$projectData['PROJECT_NAME']);
        self::$projectData['PROJECT_SLUG'] = $io->ask('Project slug', $generatedSlug);
        
        self::$projectData['PHP_VERSION'] = $io->ask('PHP version', Defaults::PHP_VERSION);
        self::$projectData['WORDPRESS_VERSION'] = $io->ask('WordPress version', Defaults::WORDPRESS_VERSION);
        
        // WordPress and web configuration
        $io->text('WordPress and Web Configuration:');
        self::$projectData['WEB_ROOT'] = $io->ask('Web root path', Defaults::WEB_ROOT);
        self::$projectData['WORDPRESS_INSTALL_PATH'] = $io->ask('WordPress install path', Defaults::WORDPRESS_INSTALL_PATH);
        self::$projectData['WP_CONTENT_TARGET'] = self::$projectData['WEB_ROOT'] . 'wp-content';
        
        // Theme selection
        self::$projectData['THEME_NAME'] = $this->selectTheme($io);

        // Git configuration
        $io->text('Git Configuration:');
        self::$projectData['GIT_REMOTE_SSH'] = $io->ask('Git remote SSH URL', Defaults::GIT_REMOTE_SSH);
        self::$projectData['GIT_DEFAULT_BRANCH'] = $io->ask('Git default branch', Defaults::GIT_DEFAULT_BRANCH);
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

    /**
     * Get project data by key
     */
    public static function getProjectData(string $key): ?string
    {
        return self::$projectData[$key] ?? null;
    }

    /**
     * Get all project data
     */
    public static function getAllProjectData(): array
    {
        return self::$projectData;
    }

    /**
     * Check if project data has been collected
     */
    public static function hasProjectData(): bool
    {
        return !empty(self::$projectData);
    }

    /**
     * Clear project data (useful for testing or resetting)
     */
    public static function clearProjectData(): void
    {
        self::$projectData = [];
    }
} 
