<?php

namespace Nucleus\Service;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class WpCliRunner
{
    public const ENV_DEVELOPMENT = 'development';
    public const ENV_STAGING = 'staging';
    public const ENV_PRODUCTION = 'production';

    private string $environment;
    private ?string $localPath = null;
    private ?string $sshHost = null;
    private ?string $sshUser = null;
    private ?string $sshPort = null;
    private ?string $remoteWebRoot = null;

    public static function forDevelopment(string $wordPressPath): self
    {
        $runner = new self();
        $runner->environment = self::ENV_DEVELOPMENT;
        $runner->localPath = rtrim($wordPressPath, '/');
        return $runner;
    }

    public static function forRemote(string $environment, string $host, string $user, string $port, string $webRoot): self
    {
        $runner = new self();
        $runner->environment = $environment;
        $runner->sshHost = $host;
        $runner->sshUser = $user;
        $runner->sshPort = $port;
        $runner->remoteWebRoot = rtrim($webRoot, '/');
        return $runner;
    }

    public static function fromEnvironment(string $environment, WordPressProjectDetector $detector, EnvFileLoader $envLoader): self
    {
        if ($environment === self::ENV_DEVELOPMENT) {
            $path = $detector->detectWordPressPath();
            if ($path === null) {
                throw new \RuntimeException('Could not detect a local WordPress installation.');
            }

            return self::forDevelopment($path);
        }

        $prefix = $environment === self::ENV_STAGING ? 'STAGING' : 'PRODUCTION';
        $env = $envLoader->load();

        $required = [
            "{$prefix}_SSH_HOST" => 'SSH host',
            "{$prefix}_SSH_USER" => 'SSH user',
            "{$prefix}_SSH_PORT" => 'SSH port',
            "{$prefix}_WEB_ROOT" => 'web root',
        ];

        $missing = [];
        foreach ($required as $key => $label) {
            if (empty($env[$key])) {
                $missing[] = "{$key} ({$label})";
            }
        }

        if (!empty($missing)) {
            throw new \RuntimeException(
                'Missing required .env variables for ' . $environment . ": \n  - " . implode("\n  - ", $missing)
            );
        }

        return self::forRemote(
            $environment,
            $env["{$prefix}_SSH_HOST"],
            $env["{$prefix}_SSH_USER"],
            $env["{$prefix}_SSH_PORT"],
            $env["{$prefix}_WEB_ROOT"]
        );
    }

    public function getEnvironment(): string
    {
        return $this->environment;
    }

    public function run(array $args, ?OutputInterface $output = null, int $timeout = 120): Process
    {
        $command = $this->buildCommand($args);
        $process = Process::fromShellCommandline($command);
        $process->setTimeout($timeout);

        if ($output !== null) {
            $process->run(function ($type, $buffer) use ($output) {
                $output->write($buffer);
            });
        } else {
            $process->run();
        }

        return $process;
    }

    public function runSuccessful(array $args, ?OutputInterface $output = null, int $timeout = 120): string
    {
        $process = $this->run($args, $output, $timeout);

        if (!$process->isSuccessful()) {
            throw new \RuntimeException(trim($process->getErrorOutput() ?: $process->getOutput() ?: 'WP-CLI command failed.'));
        }

        return $process->getOutput();
    }

    public function isWpCliAvailable(): bool
    {
        if ($this->environment !== self::ENV_DEVELOPMENT) {
            return true;
        }

        $process = Process::fromShellCommandline('which wp');
        $process->run();

        return $process->isSuccessful();
    }

    private function buildCommand(array $args): string
    {
        $wpArgs = array_merge(['user'], $args);
        $escapedArgs = array_map('escapeshellarg', $wpArgs);
        $wpCommand = 'wp ' . implode(' ', $escapedArgs);

        if ($this->environment === self::ENV_DEVELOPMENT) {
            return sprintf('%s --path=%s', $wpCommand, escapeshellarg($this->localPath));
        }

        $remoteCommand = sprintf(
            'cd %s && %s --allow-root',
            escapeshellarg($this->remoteWebRoot),
            $wpCommand
        );

        return sprintf(
            'ssh -p %s %s@%s %s',
            escapeshellarg($this->sshPort),
            escapeshellarg($this->sshUser),
            escapeshellarg($this->sshHost),
            escapeshellarg($remoteCommand)
        );
    }
}
