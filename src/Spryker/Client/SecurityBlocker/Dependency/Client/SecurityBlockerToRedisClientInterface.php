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

    public function incr(string $connectionKey, string $key): int;

    public function setex(string $connectionKey, string $key, int $seconds, string $value): bool;

    public function setupConnection(string $connectionKey, RedisConfigurationTransfer $configurationTransfer): void;
}
