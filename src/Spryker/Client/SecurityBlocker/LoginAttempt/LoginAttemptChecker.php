<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Client\SecurityBlocker\LoginAttempt;

use Generated\Shared\Transfer\SecurityBlockerConfigurationSettingsTransfer;
use Generated\Shared\Transfer\SecurityBlockerLoginRealmConfigurationTransfer;
use Generated\Shared\Transfer\SecurityCheckAuthContextTransfer;
use Generated\Shared\Transfer\SecurityCheckAuthResponseTransfer;
use Spryker\Client\SecurityBlocker\Redis\SecurityBlockerRedisWrapperInterface;
use Spryker\Client\SecurityBlocker\Resolver\ConfigurationResolverInterface;
use Spryker\Client\SecurityBlocker\Resolver\LoginRealmConfigurationResolverInterface;
use Spryker\Client\SecurityBlocker\Storage\KeyBuilder\LoginAttemptKeyBuilderInterface;
use Spryker\Client\SecurityBlocker\Storage\KeyBuilder\SecurityBlockerStorageKeyBuilderInterface;

class LoginAttemptChecker implements LoginAttemptCheckerInterface
{
    /**
     * @param list<\Spryker\Client\SecurityBlockerExtension\Dependency\Plugin\SecurityBlockerLoginChallengeStrategyPluginInterface> $securityBlockerLoginChallengeStrategyPlugins
     */
    public function __construct(
        protected LoginRealmConfigurationResolverInterface $loginRealmConfigurationResolver,
        protected ConfigurationResolverInterface $configurationResolver,
        protected SecurityBlockerStorageKeyBuilderInterface $securityBlockerStorageKeyBuilder,
        protected LoginAttemptKeyBuilderInterface $loginAttemptKeyBuilder,
        protected SecurityBlockerRedisWrapperInterface $securityBlockerRedisWrapper,
        protected array $securityBlockerLoginChallengeStrategyPlugins,
        protected LoginAttemptCheckResultRequestStorageInterface $loginAttemptCheckResultRequestStorage
    ) {
    }

    public function checkLoginAttempt(SecurityCheckAuthContextTransfer $securityCheckAuthContextTransfer): SecurityCheckAuthResponseTransfer
    {
        $securityCheckAuthResponseTransfer = $this->loginAttemptCheckResultRequestStorage->findCheckResult($securityCheckAuthContextTransfer);

        if ($securityCheckAuthResponseTransfer !== null) {
            return $securityCheckAuthResponseTransfer;
        }

        $securityCheckAuthResponseTransfer = $this->readLoginAttempt($securityCheckAuthContextTransfer);
        $this->loginAttemptCheckResultRequestStorage->saveCheckResult($securityCheckAuthContextTransfer, $securityCheckAuthResponseTransfer);

        return $securityCheckAuthResponseTransfer;
    }

    protected function readLoginAttempt(SecurityCheckAuthContextTransfer $securityCheckAuthContextTransfer): SecurityCheckAuthResponseTransfer
    {
        $realm = $securityCheckAuthContextTransfer->getRealmOrFail();
        $securityBlockerLoginRealmConfigurationTransfer = $this->loginRealmConfigurationResolver->resolve($realm);
        $securityBlockerConfigurationSettingsTransfer = $this->configurationResolver
            ->getSecurityBlockerConfigurationSettingsForType($securityCheckAuthContextTransfer->getTypeOrFail());

        $ipCount = $this->readCount($this->securityBlockerStorageKeyBuilder->getStorageKey($securityCheckAuthContextTransfer));
        [$globalCount, $accountCount] = $this->readRealmCounts($realm, (string)$securityCheckAuthContextTransfer->getLoginIdentifier());

        $isIpBlocked = $this->isThresholdReached($ipCount, (int)$securityBlockerConfigurationSettingsTransfer->getNumberOfAttempts());
        $isAccountBlocked = $this->isThresholdReached($accountCount, $securityBlockerLoginRealmConfigurationTransfer->getAccountNumberOfAttemptsOrFail());
        $isBlocked = $isIpBlocked || $isAccountBlocked;

        return (new SecurityCheckAuthResponseTransfer())
            ->setSecurityCheckAuthContext($securityCheckAuthContextTransfer)
            ->setNumberOfAttempts(max($ipCount, $accountCount))
            ->setIsBlocked($isBlocked)
            ->setBlockedFor($this->getBlockedFor($isIpBlocked, $isAccountBlocked, $securityBlockerConfigurationSettingsTransfer, $securityBlockerLoginRealmConfigurationTransfer))
            ->setIsChallengeRequired(!$isBlocked && (
                $this->isChallengeRequired($ipCount, $accountCount, $globalCount, $securityBlockerConfigurationSettingsTransfer, $securityBlockerLoginRealmConfigurationTransfer)
                || $this->isChallengeRequiredByStrategyPlugins($securityCheckAuthContextTransfer)
            ));
    }

    /**
     * @return array{0: int, 1: int} The realm-wide count and the account count, `0` for an empty login identifier.
     */
    protected function readRealmCounts(string $realm, string $loginIdentifier): array
    {
        $keys = [$this->loginAttemptKeyBuilder->getGlobalKey($realm)];
        $accountKeyHash = $this->loginAttemptKeyBuilder->findAccountKeyHash($loginIdentifier);

        if ($accountKeyHash !== null) {
            $keys[] = $this->loginAttemptKeyBuilder->getAccountKey($realm, $accountKeyHash);
        }

        $counts = $this->securityBlockerRedisWrapper->mget($keys);

        return [(int)($counts[0] ?? 0), (int)($counts[1] ?? 0)];
    }

    protected function readCount(string $key): int
    {
        return (int)$this->securityBlockerRedisWrapper->get($key);
    }

    protected function isThresholdReached(int $count, int $threshold): bool
    {
        return $threshold > 0 && $count >= $threshold;
    }

    protected function getBlockedFor(
        bool $isIpBlocked,
        bool $isAccountBlocked,
        SecurityBlockerConfigurationSettingsTransfer $securityBlockerConfigurationSettingsTransfer,
        SecurityBlockerLoginRealmConfigurationTransfer $securityBlockerLoginRealmConfigurationTransfer
    ): int {
        return max(
            $isIpBlocked ? (int)$securityBlockerConfigurationSettingsTransfer->getBlockFor() : 0,
            $isAccountBlocked ? $securityBlockerLoginRealmConfigurationTransfer->getAccountBlockForOrFail() : 0,
        );
    }

    protected function isChallengeRequired(
        int $ipCount,
        int $accountCount,
        int $globalCount,
        SecurityBlockerConfigurationSettingsTransfer $securityBlockerConfigurationSettingsTransfer,
        SecurityBlockerLoginRealmConfigurationTransfer $securityBlockerLoginRealmConfigurationTransfer
    ): bool {
        $ipChallengeNumberOfAttempts = $this->getChallengeThresholdBelowLock(
            $securityBlockerLoginRealmConfigurationTransfer->getIpChallengeNumberOfAttemptsOrFail(),
            (int)$securityBlockerConfigurationSettingsTransfer->getNumberOfAttempts(),
        );
        $accountChallengeNumberOfAttempts = $this->getChallengeThresholdBelowLock(
            $securityBlockerLoginRealmConfigurationTransfer->getAccountChallengeNumberOfAttemptsOrFail(),
            $securityBlockerLoginRealmConfigurationTransfer->getAccountNumberOfAttemptsOrFail(),
        );

        return $this->isThresholdReached($ipCount, $ipChallengeNumberOfAttempts)
            || $this->isThresholdReached($accountCount, $accountChallengeNumberOfAttempts)
            || $this->isThresholdReached($globalCount, $securityBlockerLoginRealmConfigurationTransfer->getGlobalChallengeNumberOfAttemptsOrFail());
    }

    protected function getChallengeThresholdBelowLock(int $challengeThreshold, int $lockThreshold): int
    {
        if ($challengeThreshold <= 0 || $lockThreshold <= 0) {
            return $challengeThreshold;
        }

        return min($challengeThreshold, $lockThreshold - 1);
    }

    protected function isChallengeRequiredByStrategyPlugins(SecurityCheckAuthContextTransfer $securityCheckAuthContextTransfer): bool
    {
        foreach ($this->securityBlockerLoginChallengeStrategyPlugins as $securityBlockerLoginChallengeStrategyPlugin) {
            if ($securityBlockerLoginChallengeStrategyPlugin->isChallengeRequired($securityCheckAuthContextTransfer)) {
                return true;
            }
        }

        return false;
    }
}
