<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Client\SecurityBlocker\Logger;

use Psr\Log\LoggerInterface;
use Spryker\Client\SecurityBlocker\SecurityBlockerConfig;

class LoginAttemptAuditLogger implements LoginAttemptAuditLoggerInterface
{
    /**
     * @uses \Spryker\Shared\Log\Handler\TagFilterBufferedStreamHandler::RECORD_KEY_CONTEXT_TAGS
     */
    protected const string RECORD_KEY_CONTEXT_TAGS = 'tags';

    protected const string TAG_ACCOUNT_LOCKED = 'login_account_locked';

    protected const string TAG_GLOBAL_CHALLENGE_THRESHOLD_REACHED = 'login_global_challenge_threshold_reached';

    public function __construct(
        protected LoggerInterface $logger,
        protected SecurityBlockerConfig $securityBlockerConfig
    ) {
    }

    public function addAccountLockedAuditLog(string $realm, string $accountKeyHash, int $numberOfAttempts, int $blockedFor): void
    {
        if (!$this->securityBlockerConfig->isSecurityAuditLogEnabled()) {
            return;
        }

        $this->logger->warning('Login account locked', [
            static::RECORD_KEY_CONTEXT_TAGS => [static::TAG_ACCOUNT_LOCKED],
            'realm' => $realm,
            'account_key_hash' => $accountKeyHash,
            'number_of_attempts' => $numberOfAttempts,
            'blocked_for' => $blockedFor,
        ]);
    }

    public function addGlobalChallengeThresholdReachedAuditLog(string $realm, int $numberOfFailures, int $window): void
    {
        if (!$this->securityBlockerConfig->isSecurityAuditLogEnabled()) {
            return;
        }

        $this->logger->warning('Login challenge required realm-wide', [
            static::RECORD_KEY_CONTEXT_TAGS => [static::TAG_GLOBAL_CHALLENGE_THRESHOLD_REACHED],
            'realm' => $realm,
            'number_of_failures' => $numberOfFailures,
            'window' => $window,
        ]);
    }
}
