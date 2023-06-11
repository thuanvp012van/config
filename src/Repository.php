<?php

namespace Penguin\Component\Config;

use Penguin\Component\Config\Exception\ConfigNotFoundException;
use stdClass;

class Repository
{
    /**
     * Store the configs.
     *
     * @var array<string, mixed>
     */
    protected array $configs = [];

    /**
     * Get config by name.
     *
     * @param string $name
     * @return mixed
     */
    public function get(string $name): mixed
    {
        if (!$this->has($name)) {
            return null;
        }

        return $this->configs[$name];
    }

    /**
     * Set config.
     *
     * @param string $name
     * @param mixed $config
     * @return $this
     */
    public function set(string $name, mixed $config): static
    {
        if (is_array($config) || $config instanceof stdClass) {
            $config = $this->toObject($config);
            foreach ($this->configs as $key => &$value) {
                if (strpos($key, $name) !== false) {
                    unset($value);
                } else if (strpos($name, $key) !== false && $name !== $key) {
                    $keys = explode('.', str_replace("$key.", '', $name));
                    $this->removeRecursive($value, $keys);
                    $this->addRecursive($value, $keys, $config);
                }
            }
            $this->configs = array_merge($this->configs, $this->extractConfig($name, $config));
        } else {
            $this->configs[$name] = $config;
        }
        return $this;
    }

    /**
     * Remove config by recursion.
     *
     * @param stdClass &$config
     * @param array $keys
     * @param int $level
     * @return stdClass $config
     */
    protected function removeRecursive(stdClass &$config, array $keys, int $level = 0): stdClass
    {
        if ($level >= count($keys)) {
            return $config;
        }

        if (isset($config->{$keys[$level]}) && $level === count($keys) - 1) {
            unset($config->{$keys[$level]});
        } elseif (isset($config->{$keys[$level]})) {
            $config->{$keys[$level]} = $this->removeRecursive($config->{$keys[$level]}, $keys, $level + 1);
        }

        return $config;
    }

    /**
     * Add config by recursion.
     *
     * @param stdClass &$config
     * @param array $keys
     * @param mixed &$value
     * @param int $level
     * @return void
     */
    protected function addRecursive(stdClass &$config, array $keys, mixed &$value, int $level = 0): void
    {
        if ($level === count($keys) - 1) {
            $config->{$keys[$level]} = &$value;
        } else {
            if (!isset($config->{$keys[$level]})) {
                $config->{$keys[$level]} = new stdClass;
            }
            $this->addRecursive($config->{$keys[$level]}, $keys, $value, $level + 1);
        }
    }

    /**
     * Check has config.
     *
     * @param string $name
     * @return bool
     */
    public function has(string $name): bool
    {
        return isset(($this->configs[$name]));
    }

    /**
     * Copy config.
     *
     * @param string $path
     * @param string $to
     * @return bool
     *
     * @throws \Penguin\Component\Config\Exception\ConfigNotFoundException If config file not found
     */
    public function copy(string $from, string $to): bool
    {
        if (!file_exists($from)) {
            throw new ConfigNotFoundException("Config file $from not found");
        }

        return copy($from, $to);
    }

    /**
     * Convert list into object.
     *
     * @param stdClass|array $list
     * @return stdClass<string, mixed>
     */
    protected function toObject(stdClass|array &$list): stdClass
    {
        $obj = new \stdClass();
        foreach ($list as $key => &$value) {
            if (is_array($value)) {
                $obj->$key = $this->toObject($value);
            } else {
                $obj->$key = $value;
            }
        }

        return $obj;
    }

    /**
     * Extract config.
     *
     * @param string $configName
     * @param stdClass<string, mixed> &$config
     * @return array<string, mixed> $configsExtracted
     */
    protected function extractConfig(string $configName, stdClass &$config): array
    {
        $configsExtracted = [];
        $configsExtracted[$configName] = &$config;
        foreach ($config as $key => &$value) {
            if ($value instanceof stdClass) {
                $configsExtracted = [...$configsExtracted, ...$this->extractConfig("$configName.$key", $value)];
            }

            $configsExtracted["$configName.$key"] = &$value;
        }

        return $configsExtracted;
    }
}