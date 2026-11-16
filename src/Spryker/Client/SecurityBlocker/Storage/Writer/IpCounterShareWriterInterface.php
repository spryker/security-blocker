<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Client\SecurityBlocker\Storage\Writer;

interface IpCounterShareWriterInterface
{
    /**
     * @throws \Spryker\Client\SecurityBlocker\Exception\SecurityBlockerException
     */
    public function incrementShare(string $ipCounterKey, string $accountKeyHash): void;

    /**
     * @throws \Spryker\Client\SecurityBlocker\Exception\SecurityBlockerException
     */
    public function subtractShare(string $ipCounterKey, string $accountKeyHash): void;
}
