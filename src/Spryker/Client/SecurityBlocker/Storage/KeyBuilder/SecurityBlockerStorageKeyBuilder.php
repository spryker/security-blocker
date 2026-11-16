<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Client\SecurityBlocker\Storage\KeyBuilder;

use Generated\Shared\Transfer\SecurityCheckAttemptContextTransfer;
use Generated\Shared\Transfer\SecurityCheckAuthContextTransfer;
use Spryker\Client\SecurityBlocker\SecurityBlockerConfig;
use Spryker\Shared\Kernel\StrategyResolverInterface;

class SecurityBlockerStorageKeyBuilder implements SecurityBlockerStorageKeyBuilderInterface
{
    /**
     * @var string
     */
    protected const KEY_PART_SEPARATOR = ':';

    /**
     * @param \Spryker\Shared\Kernel\StrategyResolverInterface<\Spryker\Client\SecurityBlocker\Storage\KeyBuilder\IpCounterKeyAccountResolverInterface> $ipCounterKeyAccountResolverStrategyResolver
     */
    public function __construct(protected StrategyResolverInterface $ipCounterKeyAccountResolverStrategyResolver)
    {
    }

    public function getStorageKey(SecurityCheckAuthContextTransfer $securityCheckAuthContextTransfer): string
    {
        $ipCounterKeyAccountResolver = $this->ipCounterKeyAccountResolverStrategyResolver->get(
            $securityCheckAuthContextTransfer->getIpCounterKeyStrategy() ?? SecurityBlockerConfig::IP_COUNTER_KEY_STRATEGY_IP,
        );

        return $securityCheckAuthContextTransfer->getTypeOrFail()
        . static::KEY_PART_SEPARATOR
        . $securityCheckAuthContextTransfer->getIp()
        . static::KEY_PART_SEPARATOR
        . $ipCounterKeyAccountResolver->resolveAccount($securityCheckAuthContextTransfer);
    }

    public function getAttemptStorageKey(SecurityCheckAttemptContextTransfer $securityCheckAttemptContextTransfer): string
    {
        return implode(static::KEY_PART_SEPARATOR, [
            $securityCheckAttemptContextTransfer->getTypeOrFail(),
            rawurlencode($securityCheckAttemptContextTransfer->getIdentifierOrFail()),
        ]);
    }
}
