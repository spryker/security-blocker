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
