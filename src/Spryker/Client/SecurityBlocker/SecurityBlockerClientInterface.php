<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Client\SecurityBlocker;

use Generated\Shared\Transfer\SecurityCheckAttemptContextTransfer;
use Generated\Shared\Transfer\SecurityCheckAttemptResponseTransfer;
use Generated\Shared\Transfer\SecurityCheckAuthContextTransfer;
use Generated\Shared\Transfer\SecurityCheckAuthResponseTransfer;

interface SecurityBlockerClientInterface
{
    /**
     * Specification:
     * - Saves a failed login attempt based on the data provided in the `SecurityCheckAuthContextTransfer`.
     * - Returns `isBlocked=true` if the account has exceeded the configured number of login attempts and any further attempt to log in will be blocked.
     * - Returns `isBlocked=false` if the account has not yet exceeded the allowed number of attempts to login.
     * - The TTL and number of attempts configuration for storing records are provided per type of the entity.
     * - Requires the `SecurityCheckAuthContextTransfer.type` to be provided.
     *
     * @api
     *
     * @throws \Spryker\Client\SecurityBlocker\Exception\SecurityBlockerException
     */
    public function incrementLoginAttemptCount(SecurityCheckAuthContextTransfer $securityCheckAuthContextTransfer): SecurityCheckAuthResponseTransfer;

    /**
     * Specification:
     * - Gets account blocking status based on the data provided in the `SecurityCheckAuthContextTransfer`.
     * - Returns `isBlocked=true` if the account has exceeded the configured number of login attempts.
     * - Returns `isBlocked=false` if the account has not yet exceeded the allowed number of attempts to login.
     * - The TTL and number of attempts configuration for the decision are provided per type of the entity.
     * - Requires the `SecurityCheckAuthContextTransfer.type` to be provided.
     *
     * @api
     */
    public function isAccountBlocked(SecurityCheckAuthContextTransfer $securityCheckAuthContextTransfer): SecurityCheckAuthResponseTransfer;

    /**
     * Specification:
     * - Requires `SecurityCheckAttemptContextTransfer.type`, `SecurityCheckAttemptContextTransfer.identifier` and `SecurityCheckAttemptContextTransfer.ttl` to be set.
     * - Increments by one the attempt counter of the `type` and `identifier` pair.
     * - Returns the new value of the counter in `SecurityCheckAttemptResponseTransfer.numberOfAttempts`.
     * - Does not decide if the attempt is blocked: the caller compares the returned value with its own limit.
     * - Sets the time to live of the counter to `ttl` seconds when the counter has no time to live.
     * - Does not change the time to live of a counter that already has one, so the counter is removed `ttl` seconds after the first attempt.
     * - Raises a `ttl` below `60` seconds, also `0` and negative values, to `60` seconds, so the counter always expires.
     * - Throws `SecurityBlockerException` when the counter cannot be incremented or its time to live cannot be set.
     *
     * @api
     *
     * @throws \Spryker\Client\SecurityBlocker\Exception\SecurityBlockerException
     */
    public function incrementAttemptCount(SecurityCheckAttemptContextTransfer $securityCheckAttemptContextTransfer): SecurityCheckAttemptResponseTransfer;
}
