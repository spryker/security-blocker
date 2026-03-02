<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Client\SecurityBlocker\Redis;

interface SecurityBlockerRedisWrapperInterface
{
    public function get(string $key): ?string;

    public function incr(string $key): int;

    public function setex(string $key, int $seconds, string $value): bool;
}
