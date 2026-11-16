<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Client\SecurityBlocker\Storage\Writer;

use Spryker\Client\SecurityBlocker\Exception\SecurityBlockerException;
use Spryker\Client\SecurityBlocker\Redis\SecurityBlockerRedisWrapperInterface;

class AtomicCounterWriter implements AtomicCounterWriterInterface
{
    protected const string INITIAL_COUNT = '1';

    protected const string SCRIPT_DECREMENT = <<<'LUA'
        local count = tonumber(redis.call('GET', KEYS[1]))
        if count == nil then
            return 1
        end
        if count <= tonumber(ARGV[1]) then
            redis.call('DEL', KEYS[1])
            return 1
        end
        redis.call('DECRBY', KEYS[1], ARGV[1])
        return 1
        LUA;

    public function __construct(protected SecurityBlockerRedisWrapperInterface $securityBlockerRedisWrapper)
    {
    }

    /**
     * @throws \Spryker\Client\SecurityBlocker\Exception\SecurityBlockerException
     */
    public function decrement(string $key, int $amount): void
    {
        if ($this->securityBlockerRedisWrapper->eval(static::SCRIPT_DECREMENT, [$key], [(string)$amount])) {
            return;
        }

        throw new SecurityBlockerException(sprintf('Could not decrement redisKey: "%s"', $key));
    }

    public function increment(string $key, int $windowTtl, int $lockThreshold, int $lockTtl): int
    {
        $count = $this->incrementCount($key, $windowTtl);

        if ($lockThreshold > 0 && $count === $lockThreshold) {
            $this->expire($key, $lockTtl);
        }

        return $count;
    }

    /**
     * @throws \Spryker\Client\SecurityBlocker\Exception\SecurityBlockerException
     */
    protected function incrementCount(string $key, int $windowTtl): int
    {
        if ($this->securityBlockerRedisWrapper->setIfNotExists($key, static::INITIAL_COUNT, $windowTtl)) {
            return 1;
        }

        $count = $this->securityBlockerRedisWrapper->incr($key);

        if ($count < 1) {
            throw new SecurityBlockerException(sprintf('Could not increment redisKey: "%s"', $key));
        }

        if ($count === 1) {
            $this->expire($key, $windowTtl);
        }

        return $count;
    }

    /**
     * @throws \Spryker\Client\SecurityBlocker\Exception\SecurityBlockerException
     */
    protected function expire(string $key, int $ttl): void
    {
        if ($this->securityBlockerRedisWrapper->expire($key, $ttl)) {
            return;
        }

        throw new SecurityBlockerException(sprintf('Could not set the TTL of redisKey: "%s"', $key));
    }
}
