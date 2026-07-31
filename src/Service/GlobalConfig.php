<?php

namespace Nucleus\Service;

use Symfony\Component\Yaml\Yaml;

class GlobalConfig
{
    public const DEVELOPER_DEFAULT_KEYS = [
        'username',
        'email_address',
        'first_name',
        'last_name',
        'job_title',
        'team',
    ];

    private string $path;

    public function __construct(?string $path = null)
    {
        $this->path = $path ?? self::resolvePath();
    }

    public static function resolvePath(): string
    {
        $home = getenv('HOME') ?: getenv('USERPROFILE') ?: '';

        return $home . '/.config/nucleus/config.yml';
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

    /**
     * @return array<string, string>
     */
    public function getDeveloperDefaults(): array
    {
        $config = $this->load();
        $defaults = $config['developer_defaults'] ?? [];

        if (!is_array($defaults)) {
            return [];
        }

        $result = [];
        foreach (self::DEVELOPER_DEFAULT_KEYS as $key) {
            $value = $defaults[$key] ?? null;
            if (is_string($value) && $value !== '') {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    public function getDeveloperDefault(string $key, ?string $fallback = null): ?string
    {
        $defaults = $this->getDeveloperDefaults();

        return $defaults[$key] ?? $fallback;
    }

    public function getDeveloperFullName(?string $fallback = null): ?string
    {
        $defaults = $this->getDeveloperDefaults();
        $parts = array_filter([
            $defaults['first_name'] ?? null,
            $defaults['last_name'] ?? null,
        ], static function ($value) {
            return is_string($value) && $value !== '';
        });

        if (empty($parts)) {
            return $fallback;
        }

        return implode(' ', $parts);
    }
}
