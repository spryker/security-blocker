<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Client\SecurityBlocker\Storage\Writer;

use Spryker\Client\SecurityBlocker\Redis\SecurityBlockerRedisWrapperInterface;
use Spryker\Client\SecurityBlocker\Storage\KeyBuilder\LoginAttemptKeyBuilderInterface;

class IpCounterShareWriter implements IpCounterShareWriterInterface
{
    protected const string SUBTRACTION_CLAIM_VALUE = '1';

    protected const int SUBTRACTION_CLAIM_TTL = 10;

    public function __construct(
        protected LoginAttemptKeyBuilderInterface $loginAttemptKeyBuilder,
        protected AtomicCounterWriterInterface $atomicCounterWriter,
        protected SecurityBlockerRedisWrapperInterface $securityBlockerRedisWrapper
    ) {
    }

    public function incrementShare(string $ipCounterKey, string $accountKeyHash): void
    {
        $ipCounterTtl = $this->securityBlockerRedisWrapper->ttl($ipCounterKey);

        if ($ipCounterTtl <= 0) {
            return;
        }

        $this->atomicCounterWriter->increment(
            $this->loginAttemptKeyBuilder->getIpCounterShareKey($ipCounterKey, $accountKeyHash),
            $ipCounterTtl,
            0,
            0,
        );
    }

    public function subtractShare(string $ipCounterKey, string $accountKeyHash): void
    {
        $ipCounterShareClaimKey = $this->loginAttemptKeyBuilder->getIpCounterShareClaimKey($ipCounterKey, $accountKeyHash);

        if (!$this->securityBlockerRedisWrapper->setIfNotExists($ipCounterShareClaimKey, static::SUBTRACTION_CLAIM_VALUE, static::SUBTRACTION_CLAIM_TTL)) {
            return;
        }

        $ipCounterShareKey = $this->loginAttemptKeyBuilder->getIpCounterShareKey($ipCounterKey, $accountKeyHash);
        $share = (int)$this->securityBlockerRedisWrapper->get($ipCounterShareKey);

        if ($share > 0) {
            $this->atomicCounterWriter->decrement($ipCounterKey, $share);
        }

        $this->securityBlockerRedisWrapper->del($ipCounterShareKey);
        $this->securityBlockerRedisWrapper->del($ipCounterShareClaimKey);
    }
}
