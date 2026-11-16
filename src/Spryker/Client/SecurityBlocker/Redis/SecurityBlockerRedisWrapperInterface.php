<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Client\SecurityBlocker\Redis;

interface SecurityBlockerRedisWrapperInterface
{
    public function get(string $key): ?string;

    /**
     * @param list<string> $keys
     *
     * @return list<string|null>
     */
    public function mget(array $keys): array;

    public function incr(string $key): int;

    public function setex(string $key, int $seconds, string $value): bool;

    public function setIfNotExists(string $key, string $value, int $seconds): bool;

    public function del(string $key): int;

    public function ttl(string $key): int;

    public function expire(string $key, int $seconds): bool;

    /**
     * @param list<string> $keys
     * @param list<string> $arguments
     */
    public function eval(string $script, array $keys, array $arguments): bool;
}
