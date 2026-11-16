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
     * - Discards the login attempt checks kept on the current request.
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

    /**
     * Specification:
     * - Reads the login protection state for a login attempt, never writes to the storage.
     * - Requires `SecurityCheckAuthContextTransfer.type` and `SecurityCheckAuthContextTransfer.realm` to be provided.
     * - Takes the realm thresholds from the `SecurityBlockerLoginRealmConfigurationExpanderPluginInterface` plugin stack, a realm
     *   no plugin configures gets `SecurityBlockerConfig::getDefaultLoginRealmConfiguration()` and a logged warning.
     * - Normalizes `SecurityCheckAuthContextTransfer.loginIdentifier` (trims, folds case, strips accents) and reads the realm-wide
     *   account counter, which does not depend on the IP.
     * - Reads the per-IP counter of the type (`{type}:{ip}:{account}`) with the per-type settings.
     * - Returns `isBlocked=true` when the IP counter reached the type limit or the account counter reached the realm limit;
     *   `blockedFor` is the longest lock of the blocking dimensions.
     * - Returns `isChallengeRequired=true` (only when not blocked) when the IP, the account or the realm-wide failure counter
     *   reached its challenge threshold; a threshold of `0` disables that dimension.
     * - Otherwise returns `isChallengeRequired=true` (only when not blocked) when a
     *   `SecurityBlockerLoginChallengeStrategyPluginInterface` plugin requires a challenge; the plugins can only add a challenge.
     * - Reads the account and the realm-wide counter with one Redis `MGET`: both keys carry the realm as Redis Cluster hash tag.
     * - Skips the account dimension when `loginIdentifier` is empty.
     * - Ends the per-IP counter key as `SecurityCheckAuthContextTransfer.ipCounterKeyStrategy` defines: `account` for
     *   `SecurityBlockerConfig::IP_COUNTER_KEY_STRATEGY_IP` (also when empty), the hash of the normalized `loginIdentifier` for
     *   `IP_COUNTER_KEY_STRATEGY_IP_AND_USERNAME`; the same applies to every method that reads or writes that counter. An unknown
     *   value fails with an `InvalidArgumentException`.
     * - Keeps the result on the current request of the application's request stack: a later call with an equal
     *   `SecurityCheckAuthContextTransfer` in the same request returns it without reading the counters, until a method of this
     *   client writes the login counters. Without a current request (console, queue) every call reads the counters.
     *
     * @api
     *
     * @throws \Spryker\Client\SecurityBlocker\Exception\SecurityBlockerException
     * @throws \InvalidArgumentException
     */
    public function checkLoginAttempt(SecurityCheckAuthContextTransfer $securityCheckAuthContextTransfer): SecurityCheckAuthResponseTransfer;

    /**
     * Specification:
     * - Checks the login attempt as `checkLoginAttempt()` does, including the result kept on the current request, and returns its
     *   `isChallengeRequired`.
     * - Returns `true` and logs an error when the attempt cannot be checked, for example when the key-value storage fails or
     *   `ipCounterKeyStrategy` is unknown, so the login form asks for a CAPTCHA (fail closed).
     * - Never throws.
     *
     * @api
     */
    public function isLoginChallengeRequired(SecurityCheckAuthContextTransfer $securityCheckAuthContextTransfer): bool;

    /**
     * Specification:
     * - Counts a login attempt against the realm-wide account counter BEFORE the credentials are checked, so a burst of
     *   parallel attempts cannot pass the limit.
     * - Requires `SecurityCheckAuthContextTransfer.realm` to be provided, does nothing when `loginIdentifier` is empty.
     * - Starts the account lock (`accountBlockFor`) exactly once, when the count reaches the realm limit, and writes one
     *   `login_account_locked` entry to the security audit log with the realm and the hashed key only.
     * - Returns `isBlocked=true` when this attempt exceeds the realm limit; the caller must not authenticate then.
     * - Discards the login attempt checks kept on the current request.
     *
     * @api
     *
     * @throws \Spryker\Client\SecurityBlocker\Exception\SecurityBlockerException
     */
    public function reserveLoginAttempt(SecurityCheckAuthContextTransfer $securityCheckAuthContextTransfer): SecurityCheckAuthResponseTransfer;

    /**
     * Specification:
     * - Requires `SecurityCheckAuthContextTransfer.type` and `SecurityCheckAuthContextTransfer.realm` to be provided.
     * - Increments the per-IP counter of the type (`{type}:{ip}:{account}`) atomically with the per-type settings.
     * - Also counts how many of the per-IP counter's failures the normalized `loginIdentifier` caused, for
     *   `registerSuccessfulLoginAttempt()`, that share expires when the per-IP counter's window ends.
     * - Increments the realm-wide failure counter (fixed window, never locks) and writes one
     *   `login_global_challenge_threshold_reached` entry to the security audit log when it reaches the threshold.
     * - Does not touch the account counter: the attempt was already counted by `reserveLoginAttempt()`.
     * - Returns `isBlocked=true` when the IP counter reached the type limit.
     * - Discards the login attempt checks kept on the current request.
     *
     * @api
     *
     * @throws \Spryker\Client\SecurityBlocker\Exception\SecurityBlockerException
     */
    public function registerFailedLoginAttempt(SecurityCheckAuthContextTransfer $securityCheckAuthContextTransfer): SecurityCheckAuthResponseTransfer;

    /**
     * Specification:
     * - Requires `SecurityCheckAuthContextTransfer.realm` to be provided, does nothing when `loginIdentifier` is empty.
     * - Requires `SecurityCheckAuthContextTransfer.type` to be provided for a non-empty `loginIdentifier`.
     * - Subtracts the failures the normalized `loginIdentifier` caused from the per-IP counter of the type, atomically, and
     *   removes the counter when no failure is left; the failures of other login identifiers stay counted. Does not
     *   recreate an expired counter. Parallel calls for the same login identifier and IP subtract its failures once.
     * - Deletes the realm-wide account counter of the normalized `loginIdentifier`.
     * - Never resets the realm-wide failure counter.
     * - Returns `isBlocked=false`.
     * - Discards the login attempt checks kept on the current request.
     *
     * @api
     *
     * @throws \Spryker\Client\SecurityBlocker\Exception\SecurityBlockerException
     */
    public function registerSuccessfulLoginAttempt(SecurityCheckAuthContextTransfer $securityCheckAuthContextTransfer): SecurityCheckAuthResponseTransfer;
}
