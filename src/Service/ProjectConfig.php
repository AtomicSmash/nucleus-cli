<?php

namespace Nucleus\Service;

use Symfony\Component\Yaml\Yaml;

class ProjectConfig
{
    private string $path;

    public function __construct(?string $projectRoot = null)
    {
        $root = $projectRoot ?? getcwd() ?: '';
        $this->path = rtrim($root, '/') . '/.nucleus/config.yml';
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function exists(): bool
    {
        return file_exists($this->path);
    }

    /**
     * @return array<string, mixed>
     */
    public function load(): array
    {
        if (!$this->exists()) {
            return [];
        }

        $data = Yaml::parseFile($this->path);

        return is_array($data) ? $data : [];
    }

    /**
     * @param array<string, mixed> $config
     */
    public function save(array $config): void
    {
        $dir = dirname($this->path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($this->path, Yaml::dump($config, 4, 2));
    }

    public function isOnboarded(): bool
    {
        $config = $this->load();

        return !empty($config['onboarded']);
    }

    public function isWordPressUserManagementEnabled(): bool
    {
        $config = $this->load();

        if (!array_key_exists('wordpress_user_management', $config)) {
            return true;
        }

        return (bool) $config['wordpress_user_management'];
    }

    public function setOnboarded(bool $value): void
    {
        $config = $this->load();
        $config['onboarded'] = $value;
        $this->save($config);
    }
}
