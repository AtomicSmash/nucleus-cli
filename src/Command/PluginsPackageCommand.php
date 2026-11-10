<?php

namespace Nucleus\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use ZipArchive;

class PluginsPackageCommand extends Command
{
    protected static $defaultName = 'plugins:package';

    private const SKIP_NAMES = [
        '.DS_Store',
        '__MACOSX',
        '.Spotlight-V100',
        '.Trashes',
        'Thumbs.db',
        'desktop.ini',
    ];

    private string $projectRoot;

    protected function configure(): void
    {
        $this
            ->setDescription('Package WordPress plugins into clean zip files')
            ->setHelp('Zips selected plugins from wp-content/plugins into `.nucleus/packaged-plugins/`, excluding macOS hidden files. Zip names include the plugin version.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->projectRoot = getcwd() ?: '';

        $io->title('Package WordPress Plugins');

        if (!class_exists(ZipArchive::class)) {
            $io->error('The PHP ZipArchive extension is required to package plugins.');
            return Command::FAILURE;
        }

        $wpContentPath = $this->findWpContentDirectory($io);
        if ($wpContentPath === null) {
            return Command::FAILURE;
        }

        $pluginsPath = $wpContentPath . '/plugins';
        if (!is_dir($pluginsPath)) {
            $io->error('Plugins directory not found at: ' . $pluginsPath);
            return Command::FAILURE;
        }

        $plugins = $this->scanPlugins($pluginsPath);
        if (empty($plugins)) {
            $io->warning('No plugins found in the plugins directory.');
            return Command::SUCCESS;
        }

        $choices = ['0' => 'All plugins'];
        foreach (array_values($plugins) as $index => $plugin) {
            $choices[(string) ($index + 1)] = sprintf(
                '%s (%s)',
                $plugin['slug'],
                $plugin['version']
            );
        }

        $selected = (string) $io->choice(
            'Select which plugins to zip ([0] for all)',
            $choices,
            '0'
        );

        $toPackage = $selected === '0'
            ? array_values($plugins)
            : [$plugins[(int) $selected - 1]];

        $outputDir = $this->projectRoot . '/.nucleus/packaged-plugins';
        if (!$this->ensureOutputDirectory($outputDir, $io)) {
            return Command::FAILURE;
        }

        $created = [];
        foreach ($toPackage as $plugin) {
            try {
                $zipPath = $this->packagePlugin($plugin, $outputDir);
                $created[] = basename($zipPath);
                $io->text("Created <info>{$zipPath}</info>");
            } catch (\RuntimeException $e) {
                $io->error($e->getMessage());
                return Command::FAILURE;
            }
        }

        $io->success(sprintf(
            'Packaged %d plugin(s) into .nucleus/packaged-plugins/',
            count($created)
        ));
        $io->listing($created);

        return Command::SUCCESS;
    }

    private function findWpContentDirectory(SymfonyStyle $io): ?string
    {
        $possiblePaths = [
            $this->projectRoot . '/wp-content',
            $this->projectRoot . '/public/wp-content',
        ];

        foreach ($possiblePaths as $path) {
            if (is_dir($path)) {
                return $path;
            }
        }

        while (true) {
            $path = $io->ask('wp-content directory not found. Please enter the path (relative to project root) or "q" to quit');

            if ($path === null || strtolower(trim($path)) === 'q') {
                return null;
            }

            $fullPath = $path[0] === '/'
                ? rtrim($path, '/')
                : rtrim($this->projectRoot . '/' . ltrim($path, '/'), '/');

            if (is_dir($fullPath)) {
                return $fullPath;
            }

            $io->error('Directory not found. Please try again.');
        }
    }

    /**
     * @return array<int, array{slug: string, path: string, version: string}>
     */
    private function scanPlugins(string $pluginsPath): array
    {
        $plugins = [];
        $dirs = glob($pluginsPath . '/*', GLOB_ONLYDIR) ?: [];
        sort($dirs);

        foreach ($dirs as $dir) {
            $slug = basename($dir);
            if ($slug === '' || $slug[0] === '.') {
                continue;
            }

            $plugins[] = [
                'slug' => $slug,
                'path' => $dir,
                'version' => $this->getPluginVersion($dir, $slug),
            ];
        }

        return $plugins;
    }

    private function getPluginVersion(string $pluginDir, string $slug): string
    {
        $candidates = [];
        $preferred = $pluginDir . '/' . $slug . '.php';
        if (is_file($preferred)) {
            $candidates[] = $preferred;
        }

        foreach (glob($pluginDir . '/*.php') ?: [] as $file) {
            if (!in_array($file, $candidates, true)) {
                $candidates[] = $file;
            }
        }

        foreach ($candidates as $file) {
            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }

            if (!preg_match('/^\s*\*\s*Plugin Name\s*:/mi', $content)
                && !preg_match('/^[\s]*Plugin Name\s*:/mi', $content)
            ) {
                // Prefer files that look like the main plugin bootstrap.
                if ($file !== $preferred) {
                    continue;
                }
            }

            if (preg_match('/^\s*\*?\s*Version:\s*([^\r\n]+)/mi', $content, $matches)) {
                $version = trim($matches[1]);
                if ($version !== '') {
                    return $this->sanitizeVersionForFilename($version);
                }
            }
        }

        return '0.0.0';
    }

    private function sanitizeVersionForFilename(string $version): string
    {
        $version = trim($version);
        $version = preg_replace('/[^A-Za-z0-9._-]+/', '-', $version) ?? $version;
        $version = trim($version, '.-_');

        return $version !== '' ? $version : '0.0.0';
    }

    private function ensureOutputDirectory(string $outputDir, SymfonyStyle $io): bool
    {
        $nucleusDir = dirname($outputDir);
        if (!is_dir($nucleusDir) && !mkdir($nucleusDir, 0755, true) && !is_dir($nucleusDir)) {
            $io->error('Could not create .nucleus directory.');
            return false;
        }

        if (!is_dir($outputDir) && !mkdir($outputDir, 0755, true) && !is_dir($outputDir)) {
            $io->error('Could not create .nucleus/packaged-plugins directory.');
            return false;
        }

        $this->ensurePackagedPluginsGitignore($nucleusDir);

        return true;
    }

    private function ensurePackagedPluginsGitignore(string $nucleusDir): void
    {
        $gitignore = $nucleusDir . '/.gitignore';
        $entries = ["plugin-workflow.yml\n", "packaged-plugins/\n"];
        $content = file_exists($gitignore) ? (string) file_get_contents($gitignore) : '';

        foreach ($entries as $entry) {
            if (strpos($content, trim($entry)) === false) {
                $content .= $entry;
            }
        }

        file_put_contents($gitignore, $content);
    }

    /**
     * @param array{slug: string, path: string, version: string} $plugin
     */
    private function packagePlugin(array $plugin, string $outputDir): string
    {
        $zipName = sprintf('%s.%s.zip', $plugin['slug'], $plugin['version']);
        $zipPath = $outputDir . '/' . $zipName;

        if (file_exists($zipPath) && !unlink($zipPath)) {
            throw new \RuntimeException("Could not replace existing zip: {$zipPath}");
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Could not create zip: {$zipPath}");
        }

        $rootPrefix = $plugin['slug'] . '/';
        $zip->addEmptyDir($plugin['slug']);
        $this->addDirectoryToZip($zip, $plugin['path'], $rootPrefix);
        $zip->close();

        return $zipPath;
    }

    private function addDirectoryToZip(ZipArchive $zip, string $directory, string $zipPrefix): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            $relative = ltrim(substr($file->getPathname(), strlen($directory)), DIRECTORY_SEPARATOR);
            $parts = $relative === '' ? [] : explode(DIRECTORY_SEPARATOR, $relative);
            $skip = false;
            foreach ($parts as $part) {
                if ($this->shouldSkip($part)) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) {
                continue;
            }

            $zipPath = $zipPrefix . str_replace(DIRECTORY_SEPARATOR, '/', $relative);

            if ($file->isDir()) {
                $zip->addEmptyDir(rtrim($zipPath, '/') . '/');
                continue;
            }

            if (!$file->isFile() || !$file->isReadable()) {
                continue;
            }

            $zip->addFile($file->getPathname(), $zipPath);
        }
    }

    private function shouldSkip(string $name): bool
    {
        if (in_array($name, self::SKIP_NAMES, true)) {
            return true;
        }

        // AppleDouble / resource-fork files
        return strpos($name, '._') === 0;
    }
}
