<?php

namespace Nucleus\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class PluginMigrateCommand extends Command
{
    protected static $defaultName = 'plugins:migrate';
    private Client $client;
    private array $notFoundPlugins = [];
    private bool $isGitRepository = false;

    public function __construct()
    {
        parent::__construct();
        $this->client = new Client([
            'base_uri' => 'https://api.wordpress.org/plugins/info/1.0/',
        ]);
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Migrate WordPress plugins to Composer')
            ->setHelp('This command scans your WordPress plugins directory and migrates them to Composer via wpackagist.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        // Check for composer.json
        if (!$this->checkComposerJson($io)) {
            return Command::FAILURE;
        }

        // Check if this is a git repository
        $this->isGitRepository = is_dir('.git');
        if ($this->isGitRepository) {
            $currentBranch = $this->getCurrentBranch();
            if (!$io->confirm(
                "This command will:\n\n" .
                "1. Remove plugins from the git repository\n" .
                "2. Add them to composer\n" .
                "3. Move non-WordPress plugins to /nucleus/plugins\n\n" .
                "Current branch: {$currentBranch}\n\n" .
                "Do you want to proceed? (q to quit)",
                true
            )) {
                return Command::SUCCESS;
            }
        }

        // Find wp-content directory
        $wpContentPath = $this->findWpContentDirectory($io);
        if (!$wpContentPath) {
            return Command::FAILURE;
        }

        $pluginsPath = $wpContentPath . '/plugins';
        if (!is_dir($pluginsPath)) {
            $io->error('Plugins directory not found at: ' . $pluginsPath);
            return Command::FAILURE;
        }

        // Create nucleus directory structure
        $this->createNucleusDirectory($io);

        $io->section('Scanning plugins directory...');
        $plugins = $this->scanPluginsDirectory($pluginsPath);
        
        if (empty($plugins)) {
            $io->warning('No plugins found in the plugins directory.');
            return Command::SUCCESS;
        }

        $io->section('Processing plugins...');
        foreach ($plugins as $slug => $version) {
            $io->text("Processing {$slug}...");
            
            // Delete the plugin directory
            if ($this->isGitRepository) {
                $this->deletePluginFromGit($slug, $pluginsPath, $io);
            } else {
                $this->deletePluginDirectory($slug, $pluginsPath);
            }
            
            if ($this->pluginExistsInWordPressDirectory($slug)) {
                $this->addPluginToComposer($slug, $version, $io);
            } else {
                $this->movePluginToNucleus($slug, $pluginsPath, $io);
            }
        }

        if (!empty($this->notFoundPlugins)) {
            $io->section('Plugins not found in WordPress directory:');
            foreach ($this->notFoundPlugins as $plugin) {
                $io->text("- {$plugin}");
            }
        }

        // Check and update .gitignore
        if ($this->isGitRepository) {
            $this->updateGitignore($io);
        }

        $io->section('Next Steps');
        $io->text([
            'The migration is complete! Here\'s what you need to do next:',
            '',
            '1. Review the changes in composer.json',
            '2. Run composer install to ensure all dependencies are installed',
            '3. Commit your changes:',
            '   git add composer.json composer.lock .gitignore',
            '   git commit -m "Migrate plugins to Composer"',
            '',
            'Note: Custom plugins have been moved to /nucleus/plugins and added to .gitignore'
        ]);

        return Command::SUCCESS;
    }

    private function getCurrentBranch(): string
    {
        $process = Process::fromShellCommandline('git branch --show-current');
        $process->run();
        return trim($process->getOutput());
    }

    private function deletePluginFromGit(string $slug, string $pluginsPath, SymfonyStyle $io): void
    {
        $pluginPath = $pluginsPath . '/' . $slug;
        if (!is_dir($pluginPath)) {
            return;
        }

        // Check if the plugin is tracked in git
        $process = Process::fromShellCommandline("git ls-files --error-unmatch {$pluginPath} 2>/dev/null");
        $process->run();

        if ($process->isSuccessful()) {
            // Plugin is tracked, remove it from git
            $process = Process::fromShellCommandline("git rm -r {$pluginPath}");
            $process->run();

            if ($process->isSuccessful()) {
                $io->text("Removed {$slug} from git repository");
            } else {
                $io->error("Failed to remove {$slug} from git repository: " . $process->getErrorOutput());
            }
        } else {
            // Plugin is not tracked, just delete the directory
            $this->deletePluginDirectory($slug, $pluginsPath);
            $io->text("Plugin {$slug} was not tracked in git, deleted from filesystem");
        }
    }

    private function deletePluginDirectory(string $slug, string $pluginsPath): void
    {
        $pluginPath = $pluginsPath . '/' . $slug;
        if (is_dir($pluginPath)) {
            $this->recursiveDelete($pluginPath);
        }
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

    private function updateGitignore(SymfonyStyle $io): void
    {
        $gitignorePath = '.gitignore';
        $ignoreRules = [
            'wp-content/plugins/*',
            'nucleus/*'
        ];
        
        if (!file_exists($gitignorePath)) {
            file_put_contents($gitignorePath, implode(PHP_EOL, $ignoreRules) . PHP_EOL);
            $io->text('Created .gitignore with required rules');
            return;
        }

        $content = file_get_contents($gitignorePath);
        $updated = false;

        foreach ($ignoreRules as $rule) {
            if (strpos($content, $rule) === false) {
                $content .= PHP_EOL . $rule . PHP_EOL;
                $updated = true;
            }
        }

        if ($updated) {
            file_put_contents($gitignorePath, $content);
            $io->text('Updated .gitignore with required rules');
        }
    }

    private function checkComposerJson(SymfonyStyle $io): bool
    {
        if (file_exists('composer.json')) {
            return true;
        }

        if (!$io->confirm('No composer.json found. Would you like to create one?', true)) {
            $io->error('Cannot proceed without composer.json');
            return false;
        }

        $templatePath = __DIR__ . '/../../templates/composer.json';
        if (!file_exists($templatePath)) {
            $io->error('Template composer.json not found');
            return false;
        }

        $template = file_get_contents($templatePath);
        
        // Get project details
        $vendor = $io->ask('Enter vendor name', 'vendor');
        $project = $io->ask('Enter project name', 'project');
        $description = $io->ask('Enter project description', 'WordPress project');
        
        // Replace placeholders
        $template = str_replace(
            ['vendor/project', 'WordPress project'],
            ["{$vendor}/{$project}", $description],
            $template
        );

        if (file_put_contents('composer.json', $template)) {
            $io->success('Created composer.json');
            
            // Run composer install
            if ($io->confirm('Would you like to run composer install now?', true)) {
                $process = Process::fromShellCommandline('composer install');
                $process->run();
                
                if ($process->isSuccessful()) {
                    $io->success('Composer dependencies installed');
                } else {
                    $io->error('Failed to install composer dependencies: ' . $process->getErrorOutput());
                    return false;
                }
            }
            
            return true;
        }

        $io->error('Failed to create composer.json');
        return false;
    }

    private function findWpContentDirectory(SymfonyStyle $io): ?string
    {
        $possiblePaths = [
            'wp-content',
            'public/wp-content'
        ];

        foreach ($possiblePaths as $path) {
            if (is_dir($path)) {
                return $path;
            }
        }

        while (true) {
            $path = $io->ask('wp-content directory not found. Please enter the path (relative to root) or "q" to quit');
            
            if (strtolower($path) === 'q') {
                return null;
            }

            if (is_dir($path)) {
                return $path;
            }

            $io->error('Directory not found. Please try again.');
        }
    }

    private function scanPluginsDirectory(string $pluginsPath): array
    {
        $plugins = [];
        $dirs = glob($pluginsPath . '/*', GLOB_ONLYDIR);

        foreach ($dirs as $dir) {
            $slug = basename($dir);
            $version = $this->getPluginVersion($dir);
            $plugins[$slug] = $version;
        }

        return $plugins;
    }

    private function getPluginVersion(string $pluginDir): string
    {
        $pluginFile = glob($pluginDir . '/*.php')[0] ?? null;
        if (!$pluginFile) {
            return 'dev-master';
        }

        $content = file_get_contents($pluginFile);
        if (preg_match('/Version:\s*([0-9.]+)/i', $content, $matches)) {
            return $matches[1];
        }

        return 'dev-master';
    }

    private function pluginExistsInWordPressDirectory(string $slug): bool
    {
        try {
            $response = $this->client->get($slug . '.json');
            return $response->getStatusCode() === 200;
        } catch (GuzzleException $e) {
            return false;
        }
    }

    private function addPluginToComposer(string $slug, string $version, SymfonyStyle $io): void
    {
        $package = "wpackagist-plugin/{$slug}";
        
        // If version is dev-master, try to get the latest stable version
        if ($version === 'dev-master') {
            try {
                $response = $this->client->get($slug . '.json');
                $data = json_decode($response->getBody(), true);
                if (isset($data['version'])) {
                    $version = $data['version'];
                }
            } catch (GuzzleException $e) {
                // If we can't get the version, use a more flexible constraint
                $versionConstraint = '*';
            }
        }

        // If we have a specific version, use it with a flexible constraint
        if ($version !== '*') {
            $versionConstraint = "~{$version}";
        } else {
            $versionConstraint = '*';
        }

        $process = Process::fromShellCommandline("composer require {$package}:{$versionConstraint}");
        $process->setTimeout(300); // 5 minutes timeout
        $process->run();

        if ($process->isSuccessful()) {
            $io->success("Added {$slug} to composer.json");
        } else {
            $io->error("Failed to add {$slug} to composer.json: " . $process->getErrorOutput());
        }
    }

    private function createNucleusDirectory(SymfonyStyle $io): void
    {
        $nucleusPath = 'nucleus';
        $pluginsPath = $nucleusPath . '/plugins';

        if (!is_dir($nucleusPath)) {
            mkdir($nucleusPath, 0755, true);
            $io->text('Created nucleus directory');
        }

        if (!is_dir($pluginsPath)) {
            mkdir($pluginsPath, 0755, true);
            $io->text('Created nucleus/plugins directory');
        }
    }

    private function movePluginToNucleus(string $slug, string $sourcePath, SymfonyStyle $io): void
    {
        $sourcePluginPath = $sourcePath . '/' . $slug;
        $targetPluginPath = 'nucleus/plugins/' . $slug;

        if (!is_dir($sourcePluginPath)) {
            return;
        }

        // Create target directory if it doesn't exist
        if (!is_dir($targetPluginPath)) {
            mkdir($targetPluginPath, 0755, true);
        }

        // Move all files from source to target
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourcePluginPath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $file) {
            $targetPath = $targetPluginPath . '/' . $files->getSubPathName();
            if ($file->isDir()) {
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0755, true);
                }
            } else {
                copy($file, $targetPath);
            }
        }

        $this->notFoundPlugins[] = $slug;
        $io->text("Moved {$slug} to nucleus/plugins directory");
    }
} 
