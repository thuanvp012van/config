<?php

namespace Penguin\Component\Config;

interface RepositoryInterface
{
    /**
     * Get config by name.
     *
     * @param string $name
     * @return mixed
     */
    public function get(string $name): mixed;

    /**
     * Set config.
     *
     * @param string $name
     * @param mixed $config
     * @return $this
     */
    public function set(string $name, mixed $config): static;

    /**
     * Set original config.
     *
     * @param string $name
     * @param mixed $config
     * @return $this
     */
    public function setOriginal(string $name, mixed $config): static;

    /**
     * Check has config.
     *
     * @param string $name
     * @return bool
     */
    public function has(string $name): bool;

    /**
     * Copy config.
     *
     * @param string $path
     * @param string $to
     * @return bool
     *
     * @throws \Penguin\Component\Config\Exception\ConfigNotFoundException If config file not found
     */
    public function copy(string $from, string $to): bool;
}