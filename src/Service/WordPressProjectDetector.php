<?php

namespace Nucleus\Service;

use Nucleus\Config\Defaults;
use Symfony\Component\Yaml\Yaml;

class WordPressProjectDetector
{
    private string $projectRoot;

    public function __construct(string $projectRoot)
    {
        $this->projectRoot = rtrim($projectRoot, '/');
    }

    public function getProjectRoot(): string
    {
        return $this->projectRoot;
    }

    public function hasWpCliConfig(): bool
    {
        return file_exists($this->getWpCliConfigPath());
    }

    public function getWpCliConfigPath(): string
    {
        return $this->projectRoot . '/wp-cli.yml';
    }

    public function detectWordPressPath(): ?string
    {
        if (!$this->hasWpCliConfig()) {
            return null;
        }

        $config = Yaml::parseFile($this->getWpCliConfigPath());
        $path = $config['path'] ?? null;

        if (is_string($path) && $path !== '') {
            $fullPath = $this->resolvePath($path);
            if ($this->isWordPressInstallation($fullPath)) {
                return $fullPath;
            }
        }

        foreach (Defaults::WORDPRESS_SEARCH_PATHS as $searchPath) {
            $fullPath = $this->projectRoot . '/' . $searchPath;
            if ($this->isWordPressInstallation($fullPath)) {
                return $fullPath;
            }
        }

        return null;
    }

    private function resolvePath(string $path): string
    {
        if ($path !== '' && $path[0] === '/') {
            return rtrim($path, '/');
        }

        return rtrim($this->projectRoot . '/' . $path, '/');
    }

    private function isWordPressInstallation(string $path): bool
    {
        return file_exists($path . '/wp-config.php') || file_exists($path . '/wp-load.php');
    }
}
