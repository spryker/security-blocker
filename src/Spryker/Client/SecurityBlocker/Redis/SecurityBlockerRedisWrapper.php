<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Client\SecurityBlocker\Redis;

use Spryker\Client\SecurityBlocker\Dependency\Client\SecurityBlockerToRedisClientInterface;
use Spryker\Client\SecurityBlocker\SecurityBlockerConfig;

class SecurityBlockerRedisWrapper implements SecurityBlockerRedisWrapperInterface
{
    /**
     * @var string
     */
    protected const KV_PREFIX = 'kv:';

    protected const string EXPIRE_RESOLUTION_SECONDS = 'EX';

    protected const string FLAG_ONLY_IF_NOT_EXISTS = 'NX';

    /**
     * @var \Spryker\Client\SecurityBlocker\Dependency\Client\SecurityBlockerToRedisClientInterface
     */
    protected $redisClient;

    /**
     * @var \Spryker\Client\SecurityBlocker\SecurityBlockerConfig
     */
    protected $securityBlockerConfig;

    public function __construct(
        SecurityBlockerToRedisClientInterface $redisClient,
        SecurityBlockerConfig $securityBlockerConfig
    ) {
        $this->redisClient = $redisClient;
        $this->securityBlockerConfig = $securityBlockerConfig;

        $this->setupConnection();
    }

    public function get(string $key): ?string
    {
        return $this->redisClient->get(
            $this->securityBlockerConfig->getRedisConnectionKey(),
            $this->getStorageKey($key),
        );
    }

    /**
     * @param list<string> $keys
     *
     * @return list<string|null>
     */
    public function mget(array $keys): array
    {
        $values = $this->redisClient->mget(
            $this->securityBlockerConfig->getRedisConnectionKey(),
            array_map(fn (string $key): string => $this->getStorageKey($key), $keys),
        );

        return array_values($values);
    }

    public function incr(string $key): int
    {
        return $this->redisClient->incr(
            $this->securityBlockerConfig->getRedisConnectionKey(),
            $this->getStorageKey($key),
        );
    }

    public function setex(string $key, int $seconds, string $value): bool
    {
        return $this->redisClient->setex(
            $this->securityBlockerConfig->getRedisConnectionKey(),
            $this->getStorageKey($key),
            $seconds,
            $value,
        );
    }

    public function setIfNotExists(string $key, string $value, int $seconds): bool
    {
        return $this->redisClient->set(
            $this->securityBlockerConfig->getRedisConnectionKey(),
            $this->getStorageKey($key),
            $value,
            static::EXPIRE_RESOLUTION_SECONDS,
            $seconds,
            static::FLAG_ONLY_IF_NOT_EXISTS,
        );
    }

    public function del(string $key): int
    {
        return $this->redisClient->del(
            $this->securityBlockerConfig->getRedisConnectionKey(),
            [$this->getStorageKey($key)],
        );
    }

    public function ttl(string $key): int
    {
        return $this->redisClient->ttl(
            $this->securityBlockerConfig->getRedisConnectionKey(),
            $this->getStorageKey($key),
        );
    }

    public function expire(string $key, int $seconds): bool
    {
        return $this->redisClient->expire(
            $this->securityBlockerConfig->getRedisConnectionKey(),
            $this->getStorageKey($key),
            $seconds,
        );
    }

    /**
     * @param list<string> $keys
     * @param list<string> $arguments
     */
    public function eval(string $script, array $keys, array $arguments): bool
    {
        return $this->redisClient->eval(
            $this->securityBlockerConfig->getRedisConnectionKey(),
            $script,
            count($keys),
            ...array_map(fn (string $key): string => $this->getStorageKey($key), $keys),
            ...$arguments,
        );
    }

    protected function setupConnection(): void
    {
        $this->redisClient->setupConnection(
            $this->securityBlockerConfig->getRedisConnectionKey(),
            $this->securityBlockerConfig->getRedisConnectionConfiguration(),
        );
    }

    protected function getStorageKey(string $key = '*'): string
    {
        return static::KV_PREFIX . $key;
    }
}
