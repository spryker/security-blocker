<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Client\SecurityBlocker\Logger;

interface LoginAttemptAuditLoggerInterface
{
    public function addAccountLockedAuditLog(string $realm, string $accountKeyHash, int $numberOfAttempts, int $blockedFor): void;

    public function addGlobalChallengeThresholdReachedAuditLog(string $realm, int $numberOfFailures, int $window): void;
}
