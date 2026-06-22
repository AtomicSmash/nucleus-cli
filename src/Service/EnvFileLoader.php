<?php

namespace Nucleus\Service;

class EnvFileLoader
{
    private string $projectRoot;

    public function __construct(string $projectRoot)
    {
        $this->projectRoot = rtrim($projectRoot, '/');
    }

    public function exists(): bool
    {
        return file_exists($this->getEnvPath());
    }

    public function getEnvPath(): string
    {
        return $this->projectRoot . '/.env';
    }

    /**
     * @return array<string, string>
     */
    public function load(): array
    {
        $path = $this->getEnvPath();
        if (!file_exists($path)) {
            return [];
        }

        $values = [];
        $lines = file($path, FILE_IGNORE_NEW_LINES);

        if ($lines === false) {
            return [];
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }

            if (strpos($line, '#') !== false) {
                $line = trim(explode('#', $line, 2)[0]);
            }

            if (strpos($line, '=') === false) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            if ($value !== '' && (
                ($value[0] === '"' && substr($value, -1) === '"')
                || ($value[0] === "'" && substr($value, -1) === "'")
            )) {
                $value = substr($value, 1, -1);
            }

            $values[$key] = $value;
        }

        return $values;
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $values = $this->load();
        return $values[$key] ?? $default;
    }
}
