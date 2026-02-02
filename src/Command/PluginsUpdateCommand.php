<?php

namespace Nucleus\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use MailerSend\Helpers\Builder\Personalization;

class PluginsUpdateCommand extends Command
{
    protected static $defaultName = 'plugins:update';

    private const WP_ORG_API = 'https://api.wordpress.org/plugins/info/1.0/';
    private const WPACKAGIST_PREFIX = 'wpackagist-plugin/';
    private const RELEASE_BELT_URL = 'https://release-belt.atomicsmash.co.uk';
    private const WPACKAGIST_URL = 'https://wpackagist.org';
    private const PLUGINS_SSH = 'forge@138.68.130.172';
    private const PLUGINS_SITE_PATH = '/home/forge/plugins.atomicsmash.co.uk';
    private const WORKFLOW_FILE = '.nucleus/plugin-workflow.yml';
    private const NUCLEUS_GITIGNORE = '.nucleus/.gitignore';

    private Client $wpOrgClient;
    private string $projectRoot;
    private array $projectConfig = [];
    private array $globalConfig = [];
    private array $workflow = [];
    private array $updatedPlugins = [];

    public function __construct()
    {
        parent::__construct();
        $this->wpOrgClient = new Client(['base_uri' => self::WP_ORG_API]);
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Run monthly plugin updates with optional maintenance emails and deploy workflow')
            ->setHelp('Checks wpackagist and release-belt plugins for updates, optionally sends maintenance emails, and guides through commit/deploy steps.')
            ->addOption('no-resume', null, InputOption::VALUE_NONE, 'Ignore existing workflow progress and start fresh');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->projectRoot = getcwd();

        $io->title('Plugins Update – Monthly Maintenance');

        // Step 1: First prompt – send maintenance email?
        if (!$input->getOption('no-resume')) {
            $this->workflow = $this->loadWorkflow();
        }

        $step = (int) ($this->workflow['step'] ?? 0);
        if ($step === 0) {
            $sendEmail = $io->confirm('Do you want to send the monthly maintenance email to your client?', false);
            $this->workflow['send_maintenance_email'] = $sendEmail;
            $this->workflow['step'] = 1;
            $this->saveWorkflow();
        }

        // Step 2: Project and config checks
        if ($this->workflow['step'] <= 2) {
            if (!$this->checkPreconditions($io)) {
                return Command::FAILURE;
            }
            if ($this->workflow['send_maintenance_email'] && !$this->ensureGlobalConfig($io)) {
                return Command::FAILURE;
            }
            $this->workflow['step'] = 2;
            $this->saveWorkflow();
        }

        // Step 3: Maintenance branch
        if ($this->workflow['step'] <= 3) {
            if (!$this->stepMaintenanceBranch($io)) {
                return Command::FAILURE;
            }
            $this->workflow['step'] = 3;
            $this->saveWorkflow();
        }

        // Step 4: Starting email
        if ($this->workflow['step'] <= 4 && $this->workflow['send_maintenance_email']) {
            if (!$this->stepStartingEmail($io)) {
                return Command::FAILURE;
            }
            $this->workflow['step'] = 4;
            $this->saveWorkflow();
        } elseif ($this->workflow['step'] <= 4) {
            $this->workflow['step'] = 4;
            $this->saveWorkflow();
        }

        // Step 5: Plugin updating
        if ($this->workflow['step'] <= 5) {
            if (!$this->stepPluginUpdates($io)) {
                return Command::FAILURE;
            }
            $this->workflow['updated_plugins'] = $this->updatedPlugins;
            $this->workflow['step'] = 5;
            $this->saveWorkflow();
        }

        // Step 6: Commit to maintenance branch
        if ($this->workflow['step'] <= 6) {
            if (!$this->stepCommit($io)) {
                return Command::FAILURE;
            }
            $this->workflow['step'] = 6;
            $this->saveWorkflow();
        }

        // Step 7: Deploy to staging
        if ($this->workflow['step'] <= 7) {
            if (!$this->stepDeployStaging($io)) {
                return Command::FAILURE;
            }
            $this->workflow['step'] = 7;
            $this->saveWorkflow();
        }

        // Step 8: Staging check
        if ($this->workflow['step'] <= 8) {
            if (!$this->stepStagingCheck($io)) {
                return Command::FAILURE;
            }
            $this->workflow['step'] = 8;
            $this->saveWorkflow();
        }

        // Step 9: Pull main and deploy production
        if ($this->workflow['step'] <= 9) {
            if (!$this->stepPullAndDeployProd($io)) {
                return Command::FAILURE;
            }
            $this->workflow['step'] = 9;
            $this->saveWorkflow();
        }

        // Step 10: Completion email
        if ($this->workflow['step'] <= 10 && $this->workflow['send_maintenance_email']) {
            if (!$this->stepCompletionEmail($io)) {
                return Command::FAILURE;
            }
            $this->workflow['step'] = 10;
            $this->saveWorkflow();
        }

        // Step 11: Done
        $this->stepDone($io);
        $this->clearWorkflow();

        return Command::SUCCESS;
    }

    private function checkPreconditions(SymfonyStyle $io): bool
    {
        if (!file_exists($this->projectRoot . '/composer.json')) {
            $io->error('No composer.json found in the current directory. Run this command from your project root.');
            return false;
        }

        $composer = json_decode(file_get_contents($this->projectRoot . '/composer.json'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $io->error('Invalid composer.json.');
            return false;
        }

        $require = $composer['require'] ?? [];
        if (empty($require['johnpbloch/wordpress'])) {
            $io->error('This project does not use johnpbloch/wordpress. This command is for WordPress projects managed with Composer.');
            return false;
        }

        $repos = $composer['repositories'] ?? [];
        $hasWpackagist = false;
        $hasReleaseBelt = false;
        foreach ($repos as $repo) {
            $url = $repo['url'] ?? '';
            $url = rtrim($url, '/');
            if (strpos($url, 'wpackagist.org') !== false) {
                $hasWpackagist = true;
            }
            if (strpos($url, 'release-belt.atomicsmash.co.uk') !== false) {
                $hasReleaseBelt = true;
            }
        }
        if (!$hasWpackagist && !$hasReleaseBelt) {
            $io->error('composer.json must include at least one of: https://wpackagist.org or https://release-belt.atomicsmash.co.uk in repositories.');
            return false;
        }

        $configPath = $this->projectRoot . '/.nucleus/config.yml';
        if (!file_exists($configPath)) {
            $io->error('.nucleus/config.yml not found. Please create it with plugins_update and (if using emails) maintenance_email settings.');
            return false;
        }

        $this->projectConfig = Yaml::parseFile($configPath) ?: [];
        $this->ensureNucleusGitignore();

        return true;
    }

    private function ensureNucleusGitignore(): void
    {
        $dir = $this->projectRoot . '/.nucleus';
        $gitignore = $dir . '/.gitignore';
        if (!is_dir($dir)) {
            return;
        }
        if (file_exists($gitignore)) {
            $content = file_get_contents($gitignore);
            if (strpos($content, 'plugin-workflow.yml') !== false) {
                return;
            }
        }
        $line = "plugin-workflow.yml\n";
        file_put_contents($gitignore, (file_exists($gitignore) ? file_get_contents($gitignore) : '') . $line);
    }

    private function getGlobalConfigPath(): string
    {
        $home = getenv('HOME') ?: getenv('USERPROFILE') ?: '';
        if ($home && is_dir($home . '/.config')) {
            return $home . '/.config/nucleus/config.yml';
        }
        return $home . '/.nucleus/config.yml';
    }

    private function ensureGlobalConfig(SymfonyStyle $io): bool
    {
        $path = $this->getGlobalConfigPath();
        $dir = dirname($path);
        if (!file_exists($path)) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $apiKey = $io->ask('MailerSend API key (from MailerSend dashboard)');
            $senderEmail = $io->ask('Your @atomicsmash.co.uk sender email', '');
            $developerName = $io->ask('Your name (for email signature)', '');
            $config = [
                'mailersend' => [
                    'api_key' => $apiKey,
                    'template_id' => 'v69oxl5d0ez4785k',
                ],
                'sender_email' => $senderEmail,
                'developer_name' => $developerName,
            ];
            file_put_contents($path, Yaml::dump($config, 2));
            $this->globalConfig = $config;
            return true;
        }
        $this->globalConfig = Yaml::parseFile($path) ?: [];
        $apiKey = $this->globalConfig['mailersend']['api_key'] ?? '';
        $templateId = $this->globalConfig['mailersend']['template_id'] ?? '';
        $sender = $this->globalConfig['sender_email'] ?? '';
        $name = $this->globalConfig['developer_name'] ?? '';
        if (empty($apiKey) || empty($templateId) || empty($sender) || empty($name)) {
            $io->warning('Global config is missing MailerSend API key, template_id, sender_email, or developer_name.');
            if ($io->confirm('Edit global config path: ' . $path . ' now?', true)) {
                if (empty($apiKey)) {
                    $this->globalConfig['mailersend']['api_key'] = $io->ask('MailerSend API key');
                }
                if (empty($templateId)) {
                    $this->globalConfig['mailersend']['template_id'] = $io->ask('MailerSend template ID', 'v69oxl5d0ez4785k');
                }
                if (empty($sender)) {
                    $this->globalConfig['sender_email'] = $io->ask('Sender @atomicsmash.co.uk email');
                }
                if (empty($name)) {
                    $this->globalConfig['developer_name'] = $io->ask('Developer name');
                }
                file_put_contents($path, Yaml::dump($this->globalConfig, 2));
            }
        }
        return true;
    }

    private function loadWorkflow(): array
    {
        $path = $this->projectRoot . '/' . self::WORKFLOW_FILE;
        if (!file_exists($path)) {
            return [];
        }
        $data = Yaml::parseFile($path);
        return is_array($data) ? $data : [];
    }

    private function saveWorkflow(): void
    {
        $path = $this->projectRoot . '/' . self::WORKFLOW_FILE;
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
            $this->ensureNucleusGitignore();
        }
        file_put_contents($path, Yaml::dump($this->workflow, 4));
    }

    private function clearWorkflow(): void
    {
        $path = $this->projectRoot . '/' . self::WORKFLOW_FILE;
        if (file_exists($path)) {
            unlink($path);
        }
        $this->workflow = [];
    }

    private function stepMaintenanceBranch(SymfonyStyle $io): bool
    {
        $branch = $this->workflow['maintenance_branch'] ?? null;
        $today = date('Y-m-d');
        $expectedBranch = 'feature/🛠️-Maintenance-' . $today;

        if ($branch) {
            $io->text('Using existing maintenance branch: ' . $branch);
            return $this->runGit(['checkout', $branch], $io);
        }

        if (!$io->confirm('Do you want to create a maintenance branch (from main)?', true)) {
            return true;
        }

        if (!$this->runGit(['fetch', 'origin'], $io)) {
            return false;
        }
        if (!$this->runGit(['checkout', 'main'], $io)) {
            $io->warning('Could not checkout main; trying current branch.');
        }
        if (!$this->runGit(['pull', 'origin', 'main'], $io)) {
            // continue
        }
        if (!$this->runGit(['checkout', '-b', $expectedBranch], $io)) {
            $io->error('Failed to create maintenance branch.');
            return false;
        }
        $this->workflow['maintenance_branch'] = $expectedBranch;
        $io->success('Created and switched to ' . $expectedBranch);
        return true;
    }

    private function stepStartingEmail(SymfonyStyle $io): bool
    {
        if (!$io->confirm('Do you want to send the starting email to the client?', true)) {
            return true;
        }

        $projectEmail = $this->projectConfig['maintenance_email'] ?? [];
        $clientName = $projectEmail['client_name'] ?? $io->ask('Client name', '');
        $clientEmail = $projectEmail['client_email'] ?? $io->ask('Client email', '');
        $cc = $projectEmail['cc'] ?? [];
        $siteType = $io->choice('Site type', ['WordPress', 'WooCommerce'], 'WordPress');
        $developerName = $this->globalConfig['developer_name'] ?? $io->ask('Your name', '');
        $additionalInfo = $io->ask('Additional information (optional)', '');

        $template = $this->loadEmailTemplate('maintenance-start');
        $replacements = [
            '[current month]' => date('F'),
            '[Client name]' => $clientName,
            '[WordPress or WooCommerce]' => $siteType,
            '[Developer name]' => $developerName,
            '[Additional information, if applicable]' => $additionalInfo,
        ];
        $variables = $this->buildTemplateVariables($template, $replacements);

        $io->section('Email preview (template)');
        $io->text('To: ' . $clientEmail);
        $io->text('Heading: ' . $variables['heading']);
        $io->text($variables['intro']);
        $io->text(mb_substr($variables['body_text'], 0, 200) . '…');
        if (!$io->confirm('Send this email?', true)) {
            return true;
        }

        return $this->sendEmailWithTemplate($clientEmail, $clientName, $variables, $cc, $io);
    }

    private function stepPluginUpdates(SymfonyStyle $io): bool
    {
        $io->section('Checking plugins for updates');

        $exclude = $this->projectConfig['plugins_update']['exclude'] ?? [];
        $packages = $this->getInstalledPluginPackages();
        $packages = array_filter($packages, function ($pkg) use ($exclude) {
            $slug = $pkg['slug'] ?? $pkg['name'];
            return !in_array($slug, $exclude, true) && !in_array($pkg['name'], $exclude, true);
        });

        $toUpdate = [];
        foreach ($packages as $pkg) {
            if ($pkg['source'] === 'wpackagist') {
                $latest = $this->getWpackagistLatest($pkg['slug']);
                if ($latest && version_compare($latest, $pkg['version'], '>')) {
                    $pkg['latest'] = $latest;
                    $pkg['changelog_snippet'] = $this->getWpackagistChangelogSnippet($pkg['slug'], $pkg['version'], $latest);
                    $toUpdate[] = $pkg;
                }
            }
            // Release-belt: check via composer show --all and optionally plugins site
            if ($pkg['source'] === 'release-belt') {
                $latest = $this->getReleaseBeltLatest($pkg['name']);
                if ($latest && version_compare($latest, $pkg['version'], '>')) {
                    $pkg['latest'] = $latest;
                    $pkg['changelog_snippet'] = 'Updated from release-belt';
                    $toUpdate[] = $pkg;
                }
            }
        }

        if (empty($toUpdate)) {
            $io->success('All plugins are up to date.');
            return true;
        }

        $io->table(
            ['Package', 'Current', 'Latest', 'Notes'],
            array_map(function ($p) {
                return [$p['name'], $p['version'], $p['latest'] ?? '-', $p['changelog_snippet'] ?? ''];
            }, $toUpdate)
        );

        if (!$io->confirm('Apply these updates?', true)) {
            return true;
        }

        foreach ($toUpdate as $pkg) {
            if ($pkg['source'] === 'release-belt' && $this->needsPluginsSiteUpdate($pkg)) {
                $this->updatePluginOnPluginsSite($pkg, $io);
            }
            $this->runComposerUpdate($pkg['name'], $io);
            $this->updatedPlugins[] = [
                'name' => $pkg['slug'] ?? $pkg['name'],
                'from_version' => $pkg['version'],
                'to_version' => $pkg['latest'] ?? '',
                'description' => $pkg['changelog_snippet'] ?? '',
            ];
        }

        $io->success('Plugin updates completed.');
        return true;
    }

    private function getInstalledPluginPackages(): array
    {
        $process = Process::fromShellCommandline('composer show --installed --format=json');
        $process->setTimeout(60);
        $process->run();
        if (!$process->isSuccessful()) {
            return [];
        }
        $json = json_decode($process->getOutput(), true);
        $installed = $json['installed'] ?? [];
        $result = [];
        foreach ($installed as $pkg) {
            $name = $pkg['name'] ?? '';
            if (strpos($name, self::WPACKAGIST_PREFIX) === 0) {
                $result[] = [
                    'name' => $name,
                    'slug' => str_replace(self::WPACKAGIST_PREFIX, '', $name),
                    'version' => $pkg['version'] ?? 'dev',
                    'source' => 'wpackagist',
                ];
                continue;
            }
            if ($this->isReleaseBeltPackage($name)) {
                $result[] = [
                    'name' => $name,
                    'slug' => $this->packageNameToSlug($name),
                    'version' => $pkg['version'] ?? 'dev',
                    'source' => 'release-belt',
                ];
            }
        }
        return $result;
    }

    private function isReleaseBeltPackage(string $name): bool
    {
        $lockPath = $this->projectRoot . '/composer.lock';
        if (!file_exists($lockPath)) {
            return false;
        }
        $lock = json_decode(file_get_contents($lockPath), true);
        foreach ($lock['packages'] ?? [] as $p) {
            if (($p['name'] ?? '') === $name) {
                $dist = $p['dist']['url'] ?? $p['source']['url'] ?? '';
                return strpos($dist, 'release-belt') !== false;
            }
        }
        return false;
    }

    private function packageNameToSlug(string $name): string
    {
        $parts = explode('/', $name);
        return end($parts);
    }

    private function getWpackagistLatest(string $slug): ?string
    {
        try {
            $response = $this->wpOrgClient->get($slug . '.json');
            $data = json_decode($response->getBody()->getContents(), true);
            return $data['version'] ?? null;
        } catch (GuzzleException $e) {
            return null;
        }
    }

    private function getWpackagistChangelogSnippet(string $slug, string $from, string $to): string
    {
        try {
            $response = $this->wpOrgClient->get($slug . '.json');
            $data = json_decode($response->getBody()->getContents(), true);
            $html = $data['sections']['changelog'] ?? '';
            if (empty($html)) {
                return 'See plugin changelog on wordpress.org';
            }
            $text = strip_tags(preg_replace('/<h4>/i', "\n", $html));
            return trim(mb_substr($text, 0, 200)) . '…';
        } catch (GuzzleException $e) {
            return 'Changelog unavailable';
        }
    }

    private function getReleaseBeltLatest(string $packageName): ?string
    {
        $process = Process::fromShellCommandline('composer show ' . escapeshellarg($packageName) . ' --all --format=json 2>/dev/null');
        $process->setTimeout(30);
        $process->run();
        if (!$process->isSuccessful()) {
            return null;
        }
        $json = json_decode($process->getOutput(), true);
        $versions = $json['versions'] ?? [];
        $stable = null;
        foreach ($versions as $v) {
            if (!preg_match('/^dev-|^v?dev$/i', $v)) {
                if ($stable === null || version_compare($v, $stable, '>')) {
                    $stable = $v;
                }
            }
        }
        return $stable;
    }

    private function needsPluginsSiteUpdate(array $pkg): bool
    {
        return true;
    }

    private function updatePluginOnPluginsSite(array $pkg, SymfonyStyle $io): void
    {
        $slug = $pkg['slug'];
        $cmd = sprintf(
            'ssh %s "cd %s && wp plugin update %s --allow-root"',
            escapeshellarg(self::PLUGINS_SSH),
            escapeshellarg(self::PLUGINS_SITE_PATH),
            escapeshellarg($slug)
        );
        $process = Process::fromShellCommandline($cmd);
        $process->setTimeout(120);
        $process->run();
        if ($process->isSuccessful()) {
            $script = 'bash ' . self::PLUGINS_SITE_PATH . '/update-plugins.sh --plugin-name ' . escapeshellarg($slug);
            $process2 = Process::fromShellCommandline('ssh ' . escapeshellarg(self::PLUGINS_SSH) . ' ' . escapeshellarg($script));
            $process2->setTimeout(120);
            $process2->run();
        }
    }

    private function runComposerUpdate(string $package, SymfonyStyle $io): bool
    {
        $process = Process::fromShellCommandline('composer update ' . escapeshellarg($package) . ' --no-interaction', $this->projectRoot);
        $process->setTimeout(300);
        $process->run();
        return $process->isSuccessful();
    }

    private function stepCommit(SymfonyStyle $io): bool
    {
        if (!$io->confirm('Do you want to commit the changes to the maintenance branch?', true)) {
            return true;
        }
        $plugins = $this->workflow['updated_plugins'] ?? $this->updatedPlugins;
        $lines = [];
        foreach ($plugins as $p) {
            $lines[] = sprintf('- %s: %s → %s', $p['name'], $p['from_version'], $p['to_version']);
        }
        $message = "Plugin updates\n\n" . implode("\n", $lines);
        $this->runGit(['add', 'composer.json', 'composer.lock'], $io);
        $this->runGit(['commit', '-m', $message], $io);
        $io->success('Changes committed.');
        return true;
    }

    private function stepDeployStaging(SymfonyStyle $io): bool
    {
        if (!$io->confirm('Do you want to deploy to the staging site?', true)) {
            return true;
        }
        $today = date('Y-m-d');
        $releaseBranch = 'release/' . $today;
        $maintenanceBranch = $this->workflow['maintenance_branch'] ?? null;
        if (!$maintenanceBranch) {
            $io->warning('No maintenance branch recorded.');
            return true;
        }

        $this->runGit(['fetch', 'origin'], $io);
        $this->runGit(['checkout', 'main'], $io);
        $this->runGit(['pull', 'origin', 'main'], $io);

        $process = Process::fromShellCommandline('git branch -a');
        $process->run();
        $branchOutput = $process->getOutput();
        $exists = (strpos($branchOutput, 'release/' . $today) !== false);
        if ($exists) {
            if (!$io->confirm('Release branch release/' . $today . ' already exists. Use it?', true)) {
                $i = 2;
                $releaseBranch = 'release/' . $today . '--' . $i;
                while (strpos($branchOutput, $releaseBranch) !== false) {
                    $i++;
                    $releaseBranch = 'release/' . $today . '--' . $i;
                    $process = Process::fromShellCommandline('git branch -a');
                    $process->run();
                    $branchOutput = $process->getOutput();
                }
                $this->runGit(['checkout', '-b', $releaseBranch], $io);
            } else {
                $releaseBranch = 'release/' . $today;
                $this->runGit(['checkout', $releaseBranch], $io);
                $this->runGit(['pull', 'origin', $releaseBranch], $io);
            }
        } else {
            $this->runGit(['checkout', '-b', $releaseBranch], $io);
        }
        $this->runGit(['merge', $maintenanceBranch, '-m', 'Merge maintenance into release'], $io);
        $this->runGit(['push', 'origin', $releaseBranch], $io);
        $this->workflow['release_branch'] = $releaseBranch;

        $io->text('Running npm run deploy – choose the branch/target when prompted.');
        $process = Process::fromShellCommandline('npm run deploy');
        $process->setTimeout(600);
        $process->setTty(true);
        $process->run();
        return true;
    }

    private function stepStagingCheck(SymfonyStyle $io): bool
    {
        $io->section('Staging check');
        $io->text([
            'Please check and test the staging site.',
            'When you are happy, create a pull request (e.g. via Sourcetree or GitLab) from the release branch to main.',
            'Then come back here and continue.',
        ]);
        $io->confirm('Press enter when you have created the PR and are ready to continue.', true);
        return true;
    }

    private function stepPullAndDeployProd(SymfonyStyle $io): bool
    {
        $this->runGit(['checkout', 'main'], $io);
        $this->runGit(['pull', 'origin', 'main'], $io);
        if (!$io->confirm('Do you want to deploy to production?', true)) {
            return true;
        }
        $process = Process::fromShellCommandline('npm run deploy:prod');
        $process->setTimeout(600);
        $process->setTty(true);
        $process->run();
        return true;
    }

    private function stepCompletionEmail(SymfonyStyle $io): bool
    {
        if (!$io->confirm('Do you want to send the completion email to the client?', true)) {
            return true;
        }
        $projectEmail = $this->projectConfig['maintenance_email'] ?? [];
        $clientName = $projectEmail['client_name'] ?? $io->ask('Client name', '');
        $clientEmail = $projectEmail['client_email'] ?? $io->ask('Client email', '');
        $cc = $projectEmail['cc'] ?? [];
        $siteType = $io->choice('Site type', ['WordPress', 'WooCommerce'], 'WordPress');
        $developerName = $this->globalConfig['developer_name'] ?? $io->ask('Your name', '');
        $additionalInfo = $io->ask('Additional information (optional)', '');

        $plugins = $this->workflow['updated_plugins'] ?? $this->updatedPlugins;
        $listLines = [];
        foreach ($plugins as $p) {
            $listLines[] = sprintf('- %s updated from version %s to %s - %s', $p['name'], $p['from_version'], $p['to_version'], $p['description'] ?? '');
        }
        $pluginList = implode("\n", $listLines);

        $template = $this->loadEmailTemplate('maintenance-complete');
        $replacements = [
            '[Client name]' => $clientName,
            '[WordPress or WooCommerce]' => $siteType,
            '[Developer name]' => $developerName,
            '[Additional information, if applicable]' => $additionalInfo,
            '[PLUGIN_UPDATES_LIST]' => $pluginList,
        ];
        $variables = $this->buildTemplateVariables($template, $replacements);

        $io->section('Email preview (template)');
        $io->text('To: ' . $clientEmail);
        $io->text('Heading: ' . $variables['heading']);
        $io->text($variables['intro']);
        $io->text(mb_substr($variables['body_text'], 0, 200) . '…');
        if (!$io->confirm('Send this email?', true)) {
            return true;
        }

        return $this->sendEmailWithTemplate($clientEmail, $clientName, $variables, $cc, $io);
    }

    private function stepDone(SymfonyStyle $io): void
    {
        $io->success('Monthly maintenance workflow completed.');
    }

    private const TEMPLATE_FIELDS = ['intro', 'heading', 'body_text', 'button_link', 'button_text', 'body_statement'];

    private function loadEmailTemplate(string $name): array
    {
        $path = __DIR__ . '/../../resources/emails/' . $name . '.yml';
        if (!file_exists($path)) {
            return array_fill_keys(self::TEMPLATE_FIELDS, '');
        }
        $data = Yaml::parseFile($path);
        $out = [];
        foreach (self::TEMPLATE_FIELDS as $field) {
            $out[$field] = trim((string) ($data[$field] ?? ''));
        }
        return $out;
    }

    private function buildTemplateVariables(array $template, array $replacements): array
    {
        $variables = [];
        foreach (self::TEMPLATE_FIELDS as $field) {
            $variables[$field] = $this->applyReplacements($template[$field] ?? '', $replacements);
        }
        return $variables;
    }

    private function applyReplacements(string $text, array $replacements): string
    {
        foreach ($replacements as $placeholder => $value) {
            $text = str_replace($placeholder, $value, $text);
        }
        return $text;
    }

    private function sendEmailWithTemplate(string $toEmail, string $toName, array $templateVariables, array $cc, SymfonyStyle $io): bool
    {
        $apiKey = $this->globalConfig['mailersend']['api_key'] ?? '';
        $templateId = $this->globalConfig['mailersend']['template_id'] ?? '';
        $from = $this->globalConfig['sender_email'] ?? '';
        $fromName = $this->globalConfig['developer_name'] ?? '';

        if (empty($apiKey) || empty($templateId) || empty($from)) {
            $io->error('Missing MailerSend API key, template_id, or sender email in global config.');
            return false;
        }

        try {
            $mailerSend = new \MailerSend\MailerSend(['api_key' => $apiKey]);
            $recipients = [new \MailerSend\Helpers\Builder\Recipient($toEmail, $toName)];
            $personalization = [new Personalization($toEmail, $templateVariables)];
            $emailParams = (new \MailerSend\Helpers\Builder\EmailParams())
                ->setFrom($from)
                ->setFromName($fromName)
                ->setRecipients($recipients)
                ->setTemplateId($templateId)
                ->setPersonalization($personalization);
            if (!empty($cc)) {
                $ccRecipients = array_map(function ($addr) {
                    return new \MailerSend\Helpers\Builder\Recipient($addr, '');
                }, $cc);
                $emailParams->setCc($ccRecipients);
            }
            $mailerSend->email->send($emailParams);
            $io->success('Email sent.');
            return true;
        } catch (\Throwable $e) {
            $io->error('Failed to send email: ' . $e->getMessage());
            return false;
        }
    }

    private function runGit(array $args, SymfonyStyle $io): bool
    {
        $process = new Process(['git', ...$args], $this->projectRoot);
        $process->setTimeout(60);
        $process->run();
        if (!$process->isSuccessful()) {
            $io->warning('Git: ' . $process->getErrorOutput());
            return false;
        }
        return true;
    }
}
