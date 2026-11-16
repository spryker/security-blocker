<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Client\SecurityBlocker\Storage\Writer;

interface AtomicCounterWriterInterface
{
    /**
     * @throws \Spryker\Client\SecurityBlocker\Exception\SecurityBlockerException
     */
    public function increment(string $key, int $windowTtl, int $lockThreshold, int $lockTtl): int;

    /**
     * @throws \Spryker\Client\SecurityBlocker\Exception\SecurityBlockerException
     */
    public function decrement(string $key, int $amount): void;
}
