<?php

namespace Penguin\Component\Config;

use Penguin\Component\Config\Exception\ConfigNotFoundException;
use stdClass;

class Repository implements RepositoryInterface
{
    /**
     * Store the configs.
     *
     * @var array<string, mixed>
     */
    protected array $configs = [];

    /**
     * Store the original configs.
     *
     * @var array<string, mixed>
     */
    protected array $originalConfigs = [];

    /**
     * Get config by name.
     *
     * @param string $name
     * @return mixed
     */
    public function get(string $name): mixed
    {
        if (isset($this->configs[$name])) {
            return $this->configs[$name];
        }

        if (isset($this->originalConfigs[$name])) {
            return $this->originalConfigs[$name];
        }

        return null;
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
        return $this->setConfig('configs', $name, $config);
    }

    /**
     * Set original config.
     *
     * @param string $name
     * @param mixed $config
     * @return $this
     */
    public function setOriginal(string $name, mixed $config): static
    {
        return $this->setConfig('originalConfigs', $name, $config);
    }

    protected function setConfig(string $storeName, string $name, mixed $config): static
    {
        $store = &$this->{$storeName};
        $keys = explode('.', $name);
        if (is_callable($config)) {
            $config = \Closure::fromCallable($config);
            $config = $config->bindTo($this);
            $config = $config();
        }
        if (is_array($config) || $config instanceof stdClass) {
            $config = $this->toObject($config);
            foreach ($store as $key => &$value) {
                if (strpos($key, $name) !== false) {
                    unset($store[$key]);
                } else if (strpos($name, $key) !== false && $name !== $key) {
                    $keys = array_slice($keys, 1);
                    $this->removeRecursive($value, $keys);
                    $this->addRecursive($value, $keys, $config);
                }
            }
            $store = array_merge($store, $this->extractConfig($name, $config));
        } else {
            $firstKey = $keys[0];
            $store[$firstKey] = !empty($store[$firstKey]) ? $store[$firstKey] : new stdClass;
            $this->addRecursiveByKeys($store, $store[$firstKey], $firstKey, array_slice($keys, 1), $config);
        }
        
        return $this;
    }

    /**
     * Add recursive by keys.
     *
     * @param array &$store
     * @param stdClass &$element
     * @param string &$name
     * @param array $keys
     * @param mixed $config
     */
    protected function addRecursiveByKeys(array &$store, stdClass &$element, string $name, array $keys, mixed $config): void
    {
        $key = $keys[0];
        if (count($keys) === 1) {
            $store[$name] = !empty($store[$name]) ? $store[$name] : new stdClass;
            $store[$name]->{$key} = $config;
            $element->{$key} = $config;
        } else {
            $element->{$key} = !empty($element->{$key}) ? $element->{$key} : new stdClass;
            $name .= ".$key";
            $this->addRecursiveByKeys($store, $element->{$key}, $name, array_slice($keys, 1), $config);
        }
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

        $item = &$config->{$keys[$level]};
        if (isset($item) && $level === count($keys) - 1) {
            unset($item);
        } else if (isset($item)) {
            $item = $this->removeRecursive($item, $keys, $level + 1);
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
    protected function addRecursive(stdClass &$config, array $keys, mixed $value, int $level = 0): void
    {
        if ($level === count($keys) - 1) {
            $config->{$keys[$level]} = $value;
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
    protected function extractConfig(string $configName, stdClass $config): array
    {
        $configsExtracted = [];
        $configsExtracted[$configName] = $config;
        foreach ($config as $key => $value) {
            if ($value instanceof stdClass) {
                $configsExtracted = [...$configsExtracted, ...$this->extractConfig("$configName.$key", $value)];
            }

            $configsExtracted["$configName.$key"] = $value;
        }

        return $configsExtracted;
    }
}