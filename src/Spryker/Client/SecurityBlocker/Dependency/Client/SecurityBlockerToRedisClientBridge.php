<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Client\SecurityBlocker\Dependency\Client;

use Generated\Shared\Transfer\RedisConfigurationTransfer;

class SecurityBlockerToRedisClientBridge implements SecurityBlockerToRedisClientInterface
{
    /**
     * @var \Spryker\Client\Redis\RedisClientInterface
     */
    protected $redisClient;

    /**
     * @param \Spryker\Client\Redis\RedisClientInterface $redisClient
     */
    public function __construct($redisClient)
    {
        $this->redisClient = $redisClient;
    }

    public function get(string $connectionKey, string $key): ?string
    {
        return $this->redisClient->get($connectionKey, $key);
    }

    /**
     * @param array<string> $keys
     *
     * @return array<string|null>
     */
    public function mget(string $connectionKey, array $keys): array
    {
        return $this->redisClient->mget($connectionKey, $keys);
    }

    public function incr(string $connectionKey, string $key): int
    {
        return $this->redisClient->incr($connectionKey, $key);
    }

    public function setex(string $connectionKey, string $key, int $seconds, string $value): bool
    {
        return $this->redisClient->setex($connectionKey, $key, $seconds, $value);
    }

    public function set(
        string $connectionKey,
        string $key,
        string $value,
        ?string $expireResolution = null,
        ?int $expireTTL = null,
        ?string $flag = null
    ): bool {
        return $this->redisClient->set($connectionKey, $key, $value, $expireResolution, $expireTTL, $flag);
    }

    /**
     * @param array<string> $keys
     */
    public function del(string $connectionKey, array $keys): int
    {
        return $this->redisClient->del($connectionKey, $keys);
    }

    public function ttl(string $connectionKey, string $key): int
    {
        return $this->redisClient->ttl($connectionKey, $key);
    }

    public function expire(string $connectionKey, string $key, int $seconds): bool
    {
        return $this->redisClient->expire($connectionKey, $key, $seconds);
    }

    public function eval(string $connectionKey, string $script, int $numKeys, string ...$keysOrArgs): bool
    {
        return $this->redisClient->eval($connectionKey, $script, $numKeys, ...$keysOrArgs);
    }

    public function setupConnection(string $connectionKey, RedisConfigurationTransfer $configurationTransfer): void
    {
        $this->redisClient->setupConnection($connectionKey, $configurationTransfer);
    }
}
