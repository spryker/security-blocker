<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Client\SecurityBlocker\Dependency\Client;

use Generated\Shared\Transfer\RedisConfigurationTransfer;

interface SecurityBlockerToRedisClientInterface
{
    public function get(string $connectionKey, string $key): ?string;

    /**
     * @param array<string> $keys
     *
     * @return array<string|null>
     */
    public function mget(string $connectionKey, array $keys): array;

    public function incr(string $connectionKey, string $key): int;

    public function setex(string $connectionKey, string $key, int $seconds, string $value): bool;

    public function set(
        string $connectionKey,
        string $key,
        string $value,
        ?string $expireResolution = null,
        ?int $expireTTL = null,
        ?string $flag = null
    ): bool;

    /**
     * @param array<string> $keys
     */
    public function del(string $connectionKey, array $keys): int;

    public function ttl(string $connectionKey, string $key): int;

    public function expire(string $connectionKey, string $key, int $seconds): bool;

    public function eval(string $connectionKey, string $script, int $numKeys, string ...$keysOrArgs): bool;

    public function setupConnection(string $connectionKey, RedisConfigurationTransfer $configurationTransfer): void;
}
