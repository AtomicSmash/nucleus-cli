<?php

namespace Nucleus\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Question\ChoiceQuestion;

class ThemeCleanupCommand extends Command
{
    protected static $defaultName = 'theme:cleanup';
    private string $projectRoot;

    protected function configure(): void
    {
        $this
            ->setDescription('Clean up themes by keeping only the active theme and its parent (if child theme)')
            ->setHelp('This command prompts for the active theme, detects if it\'s a parent or child theme, and removes other themes while preserving the parent theme if needed.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->projectRoot = getcwd();

        $io->title('Theme Cleanup');

        // Check if we're in a project directory
        if (!$this->isProjectDirectory($io)) {
            return Command::FAILURE;
        }

        // Get the active theme
        $activeTheme = $this->getActiveTheme($io);
        if (!$activeTheme) {
            return Command::FAILURE;
        }

        // Check if active theme exists
        if (!$this->themeExists($activeTheme, $io)) {
            return Command::FAILURE;
        }

        // Determine if it's a child theme and get parent theme
        $parentTheme = $this->getParentTheme($activeTheme, $io);

        // Get all themes
        $allThemes = $this->getAllThemes();
        if (empty($allThemes)) {
            $io->warning('No themes found in wp-content/themes/');
            return Command::SUCCESS;
        }

        // Determine which themes to keep
        $themesToKeep = [$activeTheme];
        if ($parentTheme) {
            $themesToKeep[] = $parentTheme;
        }

        $themesToDelete = array_diff($allThemes, $themesToKeep);

        if (empty($themesToDelete)) {
            $io->success('No themes to delete. All themes are either the active theme or its parent.');
            return Command::SUCCESS;
        }

        // Show what will be deleted
        $io->section('Theme Cleanup Summary');
        $io->text("Active theme: <info>{$activeTheme}</info>");
        if ($parentTheme) {
            $io->text("Parent theme: <info>{$parentTheme}</info>");
        }
        $io->text('Themes to be deleted:');
        $io->listing($themesToDelete);

        // Confirm deletion
        if (!$io->confirm('Do you want to proceed with deleting these themes?', false)) {
            $io->text('Theme cleanup cancelled.');
            return Command::SUCCESS;
        }

        // Delete themes
        $this->deleteThemes($themesToDelete, $io);

        $io->success('Theme cleanup completed successfully!');
        $io->text([
            "Active theme: {$activeTheme}",
            $parentTheme ? "Parent theme preserved: {$parentTheme}" : "No parent theme detected",
            "Deleted " . count($themesToDelete) . " theme(s)"
        ]);

        return Command::SUCCESS;
    }

    private function isProjectDirectory(SymfonyStyle $io): bool
    {
        if (!is_dir($this->projectRoot)) {
            $io->error('Invalid project directory.');
            return false;
        }
        return true;
    }

    private function getActiveTheme(SymfonyStyle $io): ?string
    {
        // Check if package.json exists and has config.theme_name
        $packageJsonPath = $this->projectRoot . '/package.json';
        if (file_exists($packageJsonPath)) {
            $packageJson = json_decode(file_get_contents($packageJsonPath), true);
            if (isset($packageJson['config']['theme_name'])) {
                $themeName = $packageJson['config']['theme_name'];
                $io->text("Found active theme in package.json: <info>{$themeName}</info>");
                return $themeName;
            }
        }

        // Prompt user to select active theme
        $themes = $this->getAllThemes();
        if (empty($themes)) {
            $io->error('No themes found in wp-content/themes/');
            return null;
        }

        $io->section('Select Active Theme');
        $io->text('Available themes:');
        $io->listing($themes);

        $choice = $io->choice('Select the active theme', $themes);
        return $choice;
    }

    private function themeExists(string $themeName, SymfonyStyle $io): bool
    {
        $themePath = $this->projectRoot . '/wp-content/themes/' . $themeName;
        if (!is_dir($themePath)) {
            $io->error("Theme '{$themeName}' not found in wp-content/themes/");
            return false;
        }
        return true;
    }

    private function getParentTheme(string $themeName, SymfonyStyle $io): ?string
    {
        $themePath = $this->projectRoot . '/wp-content/themes/' . $themeName;
        $stylePath = $themePath . '/style.css';

        if (!file_exists($stylePath)) {
            return null;
        }

        $styleContent = file_get_contents($stylePath);
        
        // Look for Template: or Parent Theme: in the header
        if (preg_match('/Template:\s*([^\r\n]+)/i', $styleContent, $matches)) {
            $parentTheme = trim($matches[1]);
            $io->text("Detected child theme. Parent theme: <info>{$parentTheme}</info>");
            return $parentTheme;
        }

        $io->text("Detected parent theme: <info>{$themeName}</info>");
        return null;
    }

    private function getAllThemes(): array
    {
        $themesPath = $this->projectRoot . '/wp-content/themes';
        if (!is_dir($themesPath)) {
            return [];
        }

        $themes = glob($themesPath . '/*', GLOB_ONLYDIR);
        return array_map('basename', $themes);
    }

    private function deleteThemes(array $themes, SymfonyStyle $io): void
    {
        $io->section('Deleting Themes');

        foreach ($themes as $theme) {
            $themePath = $this->projectRoot . '/wp-content/themes/' . $theme;
            
            if ($this->recursiveDelete($themePath)) {
                $io->text("Deleted: {$theme}");
            } else {
                $io->error("Failed to delete: {$theme}");
            }
        }
    }

    private function recursiveDelete(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }

        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                $path = $dir . "/" . $object;
                if (is_dir($path)) {
                    if (!$this->recursiveDelete($path)) {
                        return false;
                    }
                } else {
                    if (!unlink($path)) {
                        return false;
                    }
                }
            }
        }

        return rmdir($dir);
    }
} 
