<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Client\SecurityBlocker\LoginAttempt;

use Generated\Shared\Transfer\SecurityCheckAuthContextTransfer;
use Generated\Shared\Transfer\SecurityCheckAuthResponseTransfer;
use Spryker\Client\SecurityBlocker\Logger\LoginAttemptAuditLoggerInterface;
use Spryker\Client\SecurityBlocker\Resolver\LoginRealmConfigurationResolverInterface;
use Spryker\Client\SecurityBlocker\Storage\KeyBuilder\LoginAttemptKeyBuilderInterface;
use Spryker\Client\SecurityBlocker\Storage\Writer\AtomicCounterWriterInterface;

class LoginAttemptReserver implements LoginAttemptReserverInterface
{
    public function __construct(
        protected LoginRealmConfigurationResolverInterface $loginRealmConfigurationResolver,
        protected LoginAttemptKeyBuilderInterface $loginAttemptKeyBuilder,
        protected AtomicCounterWriterInterface $atomicCounterWriter,
        protected LoginAttemptAuditLoggerInterface $loginAttemptAuditLogger,
        protected LoginAttemptCheckResultRequestStorageInterface $loginAttemptCheckResultRequestStorage
    ) {
    }

    public function reserveLoginAttempt(SecurityCheckAuthContextTransfer $securityCheckAuthContextTransfer): SecurityCheckAuthResponseTransfer
    {
        $this->loginAttemptCheckResultRequestStorage->removeCheckResults();

        $securityCheckAuthResponseTransfer = (new SecurityCheckAuthResponseTransfer())
            ->setSecurityCheckAuthContext($securityCheckAuthContextTransfer)
            ->setIsBlocked(false);

        $realm = $securityCheckAuthContextTransfer->getRealmOrFail();
        $securityBlockerLoginRealmConfigurationTransfer = $this->loginRealmConfigurationResolver->resolve($realm);
        $accountKeyHash = $this->loginAttemptKeyBuilder->findAccountKeyHash((string)$securityCheckAuthContextTransfer->getLoginIdentifier());

        if ($accountKeyHash === null) {
            return $securityCheckAuthResponseTransfer;
        }

        $accountNumberOfAttempts = $securityBlockerLoginRealmConfigurationTransfer->getAccountNumberOfAttemptsOrFail();
        $accountBlockFor = $securityBlockerLoginRealmConfigurationTransfer->getAccountBlockForOrFail();
        $count = $this->atomicCounterWriter->increment(
            $this->loginAttemptKeyBuilder->getAccountKey($realm, $accountKeyHash),
            $securityBlockerLoginRealmConfigurationTransfer->getAccountTtlOrFail(),
            $accountNumberOfAttempts,
            $accountBlockFor,
        );

        if ($accountNumberOfAttempts > 0 && $count === $accountNumberOfAttempts) {
            $this->loginAttemptAuditLogger->addAccountLockedAuditLog($realm, $accountKeyHash, $count, $accountBlockFor);
        }

        if ($accountNumberOfAttempts === 0 || $count <= $accountNumberOfAttempts) {
            return $securityCheckAuthResponseTransfer->setNumberOfAttempts($count);
        }

        return $securityCheckAuthResponseTransfer
            ->setNumberOfAttempts($count)
            ->setIsBlocked(true)
            ->setBlockedFor($accountBlockFor);
    }
}
