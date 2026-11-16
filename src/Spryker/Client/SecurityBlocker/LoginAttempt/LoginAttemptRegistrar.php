<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Client\SecurityBlocker\LoginAttempt;

use Generated\Shared\Transfer\SecurityBlockerLoginRealmConfigurationTransfer;
use Generated\Shared\Transfer\SecurityCheckAuthContextTransfer;
use Generated\Shared\Transfer\SecurityCheckAuthResponseTransfer;
use Spryker\Client\SecurityBlocker\Logger\LoginAttemptAuditLoggerInterface;
use Spryker\Client\SecurityBlocker\Redis\SecurityBlockerRedisWrapperInterface;
use Spryker\Client\SecurityBlocker\Resolver\ConfigurationResolverInterface;
use Spryker\Client\SecurityBlocker\Resolver\LoginRealmConfigurationResolverInterface;
use Spryker\Client\SecurityBlocker\Storage\KeyBuilder\LoginAttemptKeyBuilderInterface;
use Spryker\Client\SecurityBlocker\Storage\KeyBuilder\SecurityBlockerStorageKeyBuilderInterface;
use Spryker\Client\SecurityBlocker\Storage\Writer\AtomicCounterWriterInterface;
use Spryker\Client\SecurityBlocker\Storage\Writer\IpCounterShareWriterInterface;

class LoginAttemptRegistrar implements LoginAttemptRegistrarInterface
{
    public function __construct(
        protected LoginRealmConfigurationResolverInterface $loginRealmConfigurationResolver,
        protected ConfigurationResolverInterface $configurationResolver,
        protected SecurityBlockerStorageKeyBuilderInterface $securityBlockerStorageKeyBuilder,
        protected LoginAttemptKeyBuilderInterface $loginAttemptKeyBuilder,
        protected AtomicCounterWriterInterface $atomicCounterWriter,
        protected IpCounterShareWriterInterface $ipCounterShareWriter,
        protected SecurityBlockerRedisWrapperInterface $securityBlockerRedisWrapper,
        protected LoginAttemptAuditLoggerInterface $loginAttemptAuditLogger,
        protected LoginAttemptCheckResultRequestStorageInterface $loginAttemptCheckResultRequestStorage
    ) {
    }

    public function registerFailedLoginAttempt(SecurityCheckAuthContextTransfer $securityCheckAuthContextTransfer): SecurityCheckAuthResponseTransfer
    {
        $this->loginAttemptCheckResultRequestStorage->removeCheckResults();

        $realm = $securityCheckAuthContextTransfer->getRealmOrFail();
        $securityBlockerLoginRealmConfigurationTransfer = $this->loginRealmConfigurationResolver->resolve($realm);
        $securityBlockerConfigurationSettingsTransfer = $this->configurationResolver
            ->getSecurityBlockerConfigurationSettingsForType($securityCheckAuthContextTransfer->getTypeOrFail());
        $ipNumberOfAttempts = (int)$securityBlockerConfigurationSettingsTransfer->getNumberOfAttempts();

        $ipCounterKey = $this->securityBlockerStorageKeyBuilder->getStorageKey($securityCheckAuthContextTransfer);
        $ipCount = $this->atomicCounterWriter->increment(
            $ipCounterKey,
            $securityBlockerConfigurationSettingsTransfer->getTtlOrFail(),
            $ipNumberOfAttempts,
            $securityBlockerConfigurationSettingsTransfer->getBlockForOrFail(),
        );
        $this->incrementIpCounterShare($ipCounterKey, (string)$securityCheckAuthContextTransfer->getLoginIdentifier());

        $realmIpCount = $this->incrementRealmIpCounter($realm, (string)$securityCheckAuthContextTransfer->getIp(), $securityBlockerLoginRealmConfigurationTransfer);

        if ($this->isFeedingRealmWideCounter($securityBlockerLoginRealmConfigurationTransfer, $realmIpCount)) {
            $this->incrementRealmWideCounter($realm, $securityBlockerLoginRealmConfigurationTransfer);
        }

        return (new SecurityCheckAuthResponseTransfer())
            ->setSecurityCheckAuthContext($securityCheckAuthContextTransfer)
            ->setNumberOfAttempts($ipCount)
            ->setIsBlocked($ipNumberOfAttempts > 0 && $ipCount >= $ipNumberOfAttempts);
    }

    public function registerSuccessfulLoginAttempt(SecurityCheckAuthContextTransfer $securityCheckAuthContextTransfer): SecurityCheckAuthResponseTransfer
    {
        $this->loginAttemptCheckResultRequestStorage->removeCheckResults();

        $securityCheckAuthResponseTransfer = (new SecurityCheckAuthResponseTransfer())
            ->setSecurityCheckAuthContext($securityCheckAuthContextTransfer)
            ->setIsBlocked(false);

        $realm = $securityCheckAuthContextTransfer->getRealmOrFail();
        $accountKeyHash = $this->loginAttemptKeyBuilder->findAccountKeyHash((string)$securityCheckAuthContextTransfer->getLoginIdentifier());

        if ($accountKeyHash === null) {
            return $securityCheckAuthResponseTransfer;
        }

        $this->ipCounterShareWriter->subtractShare(
            $this->securityBlockerStorageKeyBuilder->getStorageKey($securityCheckAuthContextTransfer),
            $accountKeyHash,
        );
        $this->securityBlockerRedisWrapper->del($this->loginAttemptKeyBuilder->getAccountKey($realm, $accountKeyHash));

        return $securityCheckAuthResponseTransfer;
    }

    protected function incrementIpCounterShare(string $ipCounterKey, string $loginIdentifier): void
    {
        $accountKeyHash = $this->loginAttemptKeyBuilder->findAccountKeyHash($loginIdentifier);

        if ($accountKeyHash === null) {
            return;
        }

        $this->ipCounterShareWriter->incrementShare($ipCounterKey, $accountKeyHash);
    }

    protected function incrementRealmIpCounter(
        string $realm,
        string $ip,
        SecurityBlockerLoginRealmConfigurationTransfer $securityBlockerLoginRealmConfigurationTransfer
    ): int {
        return $this->atomicCounterWriter->increment(
            $this->loginAttemptKeyBuilder->getIpKey($realm, $ip),
            $securityBlockerLoginRealmConfigurationTransfer->getGlobalTtlOrFail(),
            0,
            0,
        );
    }

    protected function isFeedingRealmWideCounter(
        SecurityBlockerLoginRealmConfigurationTransfer $securityBlockerLoginRealmConfigurationTransfer,
        int $realmIpCount
    ): bool {
        $ipChallengeNumberOfAttempts = (int)$securityBlockerLoginRealmConfigurationTransfer->getIpChallengeNumberOfAttempts();

        return $ipChallengeNumberOfAttempts <= 0 || $realmIpCount <= $ipChallengeNumberOfAttempts;
    }

    protected function incrementRealmWideCounter(
        string $realm,
        SecurityBlockerLoginRealmConfigurationTransfer $securityBlockerLoginRealmConfigurationTransfer
    ): void {
        $globalChallengeNumberOfAttempts = $securityBlockerLoginRealmConfigurationTransfer->getGlobalChallengeNumberOfAttemptsOrFail();
        $globalTtl = $securityBlockerLoginRealmConfigurationTransfer->getGlobalTtlOrFail();
        $globalCount = $this->atomicCounterWriter->increment($this->loginAttemptKeyBuilder->getGlobalKey($realm), $globalTtl, 0, 0);

        if ($globalChallengeNumberOfAttempts > 0 && $globalCount === $globalChallengeNumberOfAttempts) {
            $this->loginAttemptAuditLogger->addGlobalChallengeThresholdReachedAuditLog($realm, $globalCount, $globalTtl);
        }
    }
}
