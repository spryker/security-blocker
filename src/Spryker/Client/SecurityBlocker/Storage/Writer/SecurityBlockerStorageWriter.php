<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Client\SecurityBlocker\Storage\Writer;

use Generated\Shared\Transfer\SecurityBlockerConfigurationSettingsTransfer;
use Generated\Shared\Transfer\SecurityCheckAttemptContextTransfer;
use Generated\Shared\Transfer\SecurityCheckAttemptResponseTransfer;
use Generated\Shared\Transfer\SecurityCheckAuthContextTransfer;
use Generated\Shared\Transfer\SecurityCheckAuthResponseTransfer;
use Spryker\Client\SecurityBlocker\Exception\SecurityBlockerException;
use Spryker\Client\SecurityBlocker\LoginAttempt\LoginAttemptCheckResultRequestStorageInterface;
use Spryker\Client\SecurityBlocker\Redis\SecurityBlockerRedisWrapperInterface;
use Spryker\Client\SecurityBlocker\Resolver\ConfigurationResolverInterface;
use Spryker\Client\SecurityBlocker\Storage\KeyBuilder\SecurityBlockerStorageKeyBuilderInterface;

class SecurityBlockerStorageWriter implements SecurityBlockerStorageWriterInterface
{
    protected const int TTL_NO_EXPIRATION = -1;

    protected const int MIN_ATTEMPT_TTL_SECONDS = 60;

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
        ConfigurationResolverInterface $configurationResolver,
        protected LoginAttemptCheckResultRequestStorageInterface $loginAttemptCheckResultRequestStorage
    ) {
        $this->securityBlockerRedisWrapper = $securityBlockerRedisWrapper;
        $this->securityBlockerStorageKeyBuilder = $securityBlockerStorageKeyBuilder;
        $this->configurationResolver = $configurationResolver;
    }

    /**
     * @throws \Spryker\Client\SecurityBlocker\Exception\SecurityBlockerException
     */
    public function incrementLoginAttemptCount(SecurityCheckAuthContextTransfer $securityCheckAuthContextTransfer): SecurityCheckAuthResponseTransfer
    {
        $this->loginAttemptCheckResultRequestStorage->removeCheckResults();

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
     * @throws \Spryker\Client\SecurityBlocker\Exception\SecurityBlockerException
     */
    public function incrementAttemptCount(SecurityCheckAttemptContextTransfer $securityCheckAttemptContextTransfer): SecurityCheckAttemptResponseTransfer
    {
        $key = $this->securityBlockerStorageKeyBuilder->getAttemptStorageKey($securityCheckAttemptContextTransfer);
        $numberOfAttempts = $this->securityBlockerRedisWrapper->incr($key);

        if (!$numberOfAttempts) {
            throw new SecurityBlockerException(
                sprintf('Could not increment redisKey: "%s"', $key),
            );
        }

        $this->setAttemptTtl($key, $securityCheckAttemptContextTransfer->getTtlOrFail());

        return (new SecurityCheckAttemptResponseTransfer())
            ->setNumberOfAttempts($numberOfAttempts);
    }

    /**
     * @throws \Spryker\Client\SecurityBlocker\Exception\SecurityBlockerException
     */
    protected function setAttemptTtl(string $key, int $ttl): void
    {
        if ($this->securityBlockerRedisWrapper->ttl($key) !== static::TTL_NO_EXPIRATION) {
            return;
        }

        if (!$this->securityBlockerRedisWrapper->expire($key, max($ttl, static::MIN_ATTEMPT_TTL_SECONDS))) {
            throw new SecurityBlockerException(
                sprintf('Could not set ttl for redisKey: "%s"', $key),
            );
        }
    }

    /**
     * @throws \Spryker\Client\SecurityBlocker\Exception\SecurityBlockerException
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
