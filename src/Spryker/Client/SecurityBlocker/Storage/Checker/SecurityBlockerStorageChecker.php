<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Client\SecurityBlocker\Storage\Checker;

use Generated\Shared\Transfer\SecurityCheckAuthContextTransfer;
use Generated\Shared\Transfer\SecurityCheckAuthResponseTransfer;
use Spryker\Client\SecurityBlocker\Redis\SecurityBlockerRedisWrapperInterface;
use Spryker\Client\SecurityBlocker\Resolver\ConfigurationResolverInterface;
use Spryker\Client\SecurityBlocker\Storage\KeyBuilder\SecurityBlockerStorageKeyBuilderInterface;

class SecurityBlockerStorageChecker implements SecurityBlockerStorageCheckerInterface
{
    /**
     * @var \Spryker\Client\SecurityBlocker\Redis\SecurityBlockerRedisWrapperInterface
     */
    protected $securityBlockerRedisWrapper;

    /**
     * @var \Spryker\Client\SecurityBlocker\Storage\KeyBuilder\SecurityBlockerStorageKeyBuilderInterface
     */
    protected $securityBlockerStorageKeyBuilder;

    /**
     * @var \Spryker\Client\SecurityBlocker\Resolver\ConfigurationResolverInterface
     */
    protected $configurationResolver;

    public function __construct(
        SecurityBlockerRedisWrapperInterface $securityBlockerRedisWrapper,
        SecurityBlockerStorageKeyBuilderInterface $securityBlockerStorageKeyBuilder,
        ConfigurationResolverInterface $configurationResolver
    ) {
        $this->securityBlockerRedisWrapper = $securityBlockerRedisWrapper;
        $this->securityBlockerStorageKeyBuilder = $securityBlockerStorageKeyBuilder;
        $this->configurationResolver = $configurationResolver;
    }

    public function isAccountBlocked(SecurityCheckAuthContextTransfer $securityCheckAuthContextTransfer): SecurityCheckAuthResponseTransfer
    {
        $storageKey = $this->securityBlockerStorageKeyBuilder->getStorageKey($securityCheckAuthContextTransfer);
        $numberOfAttempts = (int)$this->securityBlockerRedisWrapper->get($storageKey);

        $securityCheckAuthResponseTransfer = (new SecurityCheckAuthResponseTransfer())
            ->setSecurityCheckAuthContext($securityCheckAuthContextTransfer)
            ->setIsBlocked(false);

        if (!$numberOfAttempts) {
            return $securityCheckAuthResponseTransfer;
        }

        $securityBlockerConfigurationSettingsTransfer = $this->configurationResolver
            ->getSecurityBlockerConfigurationSettingsForType($securityCheckAuthContextTransfer->getTypeOrFail());

        return $securityCheckAuthResponseTransfer
            ->setNumberOfAttempts($numberOfAttempts)
            ->setBlockedFor($securityBlockerConfigurationSettingsTransfer->getBlockFor())
            ->setIsBlocked($numberOfAttempts >= $securityBlockerConfigurationSettingsTransfer->getNumberOfAttempts());
    }
}
