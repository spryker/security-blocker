<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Client\SecurityBlocker\Storage\Writer;

use Generated\Shared\Transfer\SecurityBlockerConfigurationSettingsTransfer;
use Generated\Shared\Transfer\SecurityCheckAuthContextTransfer;
use Generated\Shared\Transfer\SecurityCheckAuthResponseTransfer;
use Spryker\Client\SecurityBlocker\Exception\SecurityBlockerException;
use Spryker\Client\SecurityBlocker\Redis\SecurityBlockerRedisWrapperInterface;
use Spryker\Client\SecurityBlocker\Resolver\ConfigurationResolverInterface;
use Spryker\Client\SecurityBlocker\Storage\KeyBuilder\SecurityBlockerStorageKeyBuilderInterface;

class SecurityBlockerStorageWriter implements SecurityBlockerStorageWriterInterface
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

    /**
     * @param \Spryker\Client\SecurityBlocker\Redis\SecurityBlockerRedisWrapperInterface $securityBlockerRedisWrapper
     * @param \Spryker\Client\SecurityBlocker\Storage\KeyBuilder\SecurityBlockerStorageKeyBuilderInterface $securityBlockerStorageKeyBuilder
     * @param \Spryker\Client\SecurityBlocker\Resolver\ConfigurationResolverInterface $configurationResolver
     */
    public function __construct(
        SecurityBlockerRedisWrapperInterface $securityBlockerRedisWrapper,
        SecurityBlockerStorageKeyBuilderInterface $securityBlockerStorageKeyBuilder,
        ConfigurationResolverInterface $configurationResolver
    ) {
        $this->securityBlockerRedisWrapper = $securityBlockerRedisWrapper;
        $this->securityBlockerStorageKeyBuilder = $securityBlockerStorageKeyBuilder;
        $this->configurationResolver = $configurationResolver;
    }

    /**
     * @param \Generated\Shared\Transfer\SecurityCheckAuthContextTransfer $securityCheckAuthContextTransfer
     *
     * @throws \Spryker\Client\SecurityBlocker\Exception\SecurityBlockerException
     *
     * @return \Generated\Shared\Transfer\SecurityCheckAuthResponseTransfer
     */
    public function incrementLoginAttemptCount(SecurityCheckAuthContextTransfer $securityCheckAuthContextTransfer): SecurityCheckAuthResponseTransfer
    {
        $securityBlockerConfigurationSettingsTransfer = $this->configurationResolver
            ->getSecurityBlockerConfigurationSettingsForType($securityCheckAuthContextTransfer->getTypeOrFail());
        $key = $this->securityBlockerStorageKeyBuilder->getStorageKey($securityCheckAuthContextTransfer);

        $newValue = $this->updateStorage($key, $securityBlockerConfigurationSettingsTransfer);

        if (!$newValue) {
            throw new SecurityBlockerException(
                sprintf('Could not set redisKey: "%s" with value: "%s"', $key, $newValue),
            );
        }

        return (new SecurityCheckAuthResponseTransfer())
            ->setSecurityCheckAuthContext($securityCheckAuthContextTransfer)
            ->setNumberOfAttempts($newValue)
            ->setIsBlocked($newValue >= $securityBlockerConfigurationSettingsTransfer->getNumberOfAttempts());
    }

    /**
     * @param string $storageKey
     * @param \Generated\Shared\Transfer\SecurityBlockerConfigurationSettingsTransfer $securityBlockerConfigurationSettingsTransfer
     *
     * @throws \Spryker\Client\SecurityBlocker\Exception\SecurityBlockerException
     *
     * @return int
     */
    protected function updateStorage(
        string $storageKey,
        SecurityBlockerConfigurationSettingsTransfer $securityBlockerConfigurationSettingsTransfer
    ): int {
        $existingValue = (int)$this->securityBlockerRedisWrapper->get($storageKey);
        $newValue = $existingValue + 1;

        if ($existingValue && $newValue < $securityBlockerConfigurationSettingsTransfer->getNumberOfAttempts()) {
            $incrResult = $this->securityBlockerRedisWrapper->incr($storageKey);

            if (!$incrResult) {
                throw new SecurityBlockerException(
                    sprintf('Could not increment redisKey: "%s"', $storageKey),
                );
            }

            return $incrResult;
        }

        $ttl = !$existingValue
            ? $securityBlockerConfigurationSettingsTransfer->getTtlOrFail()
            : $securityBlockerConfigurationSettingsTransfer->getBlockForOrFail();

        $setResult = $this->securityBlockerRedisWrapper->setex($storageKey, $ttl, (string)$newValue);

        if (!$setResult) {
            throw new SecurityBlockerException(
                sprintf('Could not set redisKey: "%s" with value: "%s"', $storageKey, $newValue),
            );
        }

        return $newValue;
    }
}
