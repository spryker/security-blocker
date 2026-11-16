<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Client\SecurityBlocker;

use Generated\Shared\Transfer\RedisConfigurationTransfer;
use Generated\Shared\Transfer\RedisCredentialsTransfer;
use Generated\Shared\Transfer\SecurityBlockerConfigurationSettingsTransfer;
use Generated\Shared\Transfer\SecurityBlockerLoginRealmConfigurationTransfer;
use Spryker\Client\Kernel\AbstractBundleConfig;
use Spryker\Shared\SecurityBlocker\SecurityBlockerConstants;

class SecurityBlockerConfig extends AbstractBundleConfig
{
    /**
     * Specification:
     * - Value of `SecurityCheckAuthContextTransfer.ipCounterKeyStrategy`, also used when the property is empty.
     * - The per-IP counter key is `{type}:{ip}:{account}`, with the `account` of the caller.
     *
     * @api
     */
    public const string IP_COUNTER_KEY_STRATEGY_IP = 'ip';

    /**
     * Specification:
     * - Value of `SecurityCheckAuthContextTransfer.ipCounterKeyStrategy`.
     * - The per-IP counter key ends with the SHA-256 hash of the normalized `loginIdentifier` (trimmed, case-folded,
     *   accents stripped) instead of `account`, so an IP address has one counter per login identifier and case variants
     *   share it. Uses `account` when `loginIdentifier` is empty.
     *
     * @api
     */
    public const string IP_COUNTER_KEY_STRATEGY_IP_AND_USERNAME = 'ip-and-username';

    /**
     * @deprecated Use {@link \Spryker\Client\SecurityBlockerStorefrontAgent\SecurityBlockerStorefrontAgentConfig::SECURITY_BLOCKER_AGENT_ENTITY_TYPE} instead.
     *
     * @var string
     */
    protected const SECURITY_BLOCKER_AGENT_ENTITY_TYPE = 'agent';

    /**
     * @var string
     */
    protected const STORAGE_REDIS_CONNECTION_KEY = 'SECURITY_BLOCKER_REDIS';

    /**
     * @var int
     */
    protected const DEFAULT_BLOCKING_TTL = 600;

    /**
     * @var int
     */
    protected const DEFAULT_BLOCK_FOR = 300;

    /**
     * @var int
     */
    protected const DEFAULT_BLOCKING_NUMBER_OF_ATTEMPTS = 10;

    protected const int DEFAULT_ACCOUNT_BLOCKING_NUMBER_OF_ATTEMPTS = 9;

    protected const int DEFAULT_ACCOUNT_BLOCKING_TTL = 900;

    protected const int DEFAULT_ACCOUNT_BLOCK_FOR_SECONDS = 360;

    protected const int DEFAULT_ACCOUNT_CHALLENGE_NUMBER_OF_ATTEMPTS = 3;

    protected const int DEFAULT_IP_CHALLENGE_NUMBER_OF_ATTEMPTS = 5;

    protected const int DEFAULT_GLOBAL_CHALLENGE_NUMBER_OF_ATTEMPTS = 100;

    protected const int DEFAULT_GLOBAL_CHALLENGE_TTL = 300;

    protected const int LOGIN_IDENTIFIER_MAX_LENGTH = 1024;

    protected const string LOGIN_IDENTIFIER_SHARP_S_EQUIVALENT = 's';

    protected const bool IS_SECURITY_AUDIT_LOG_ENABLED = false;

    /**
     * Specification:
     * - Returns redis connection key used by the module.
     *
     * @api
     */
    public function getRedisConnectionKey(): string
    {
        return static::STORAGE_REDIS_CONNECTION_KEY;
    }

    /**
     * Specification:
     * - Returns redis connection configuration used by the module.
     *
     * @api
     */
    public function getRedisConnectionConfiguration(): RedisConfigurationTransfer
    {
        return (new RedisConfigurationTransfer())
            ->setDataSourceNames($this->getDataSourceNames())
            ->setConnectionCredentials($this->getConnectionCredentials())
            ->setClientOptions($this->getConnectionOptions());
    }

    /**
     * @return array<string>
     */
    protected function getDataSourceNames(): array
    {
        return $this->get(SecurityBlockerConstants::SECURITY_BLOCKER_REDIS_DATA_SOURCE_NAMES, []);
    }

    protected function getConnectionCredentials(): RedisCredentialsTransfer
    {
        return (new RedisCredentialsTransfer())
            ->setScheme($this->getZedSessionScheme())
            ->setHost($this->get(SecurityBlockerConstants::SECURITY_BLOCKER_REDIS_HOST))
            ->setPort($this->get(SecurityBlockerConstants::SECURITY_BLOCKER_REDIS_PORT))
            ->setUsername($this->get(SecurityBlockerConstants::SECURITY_BLOCKER_REDIS_USER, false))
            ->setDatabase($this->get(SecurityBlockerConstants::SECURITY_BLOCKER_REDIS_DATABASE))
            ->setPassword($this->get(SecurityBlockerConstants::SECURITY_BLOCKER_REDIS_PASSWORD, false))
            ->setIsPersistent($this->get(SecurityBlockerConstants::SECURITY_BLOCKER_REDIS_PERSISTENT_CONNECTION, false));
    }

    /**
     * @return array<string, mixed>
     */
    protected function getConnectionOptions(): array
    {
        return $this->get(SecurityBlockerConstants::SECURITY_BLOCKER_REDIS_CONNECTION_OPTIONS, []);
    }

    /**
     * Specification:
     * - Returns the security configuration per type.
     *
     * @api
     *
     * @deprecated Exists for BC reasons. Use {@link \Spryker\Client\SecurityBlockerStorefrontAgent\Plugin\SecurityBlocker\AgentSecurityBlockerConfigurationSettingsExpanderPlugin}.
     *
     * @return array<string, \Generated\Shared\Transfer\SecurityBlockerConfigurationSettingsTransfer>
     */
    public function getSecurityBlockerConfigurationSettings(): array
    {
        $sharedConfig = $this->getConfig();

        $ttl = $sharedConfig::hasValue(SecurityBlockerConstants::SECURITY_BLOCKER_AGENT_BLOCKING_TTL)
            ? $this->get(SecurityBlockerConstants::SECURITY_BLOCKER_AGENT_BLOCKING_TTL) : null;
        $blockFor = $sharedConfig::hasValue(SecurityBlockerConstants::SECURITY_BLOCKER_AGENT_BLOCK_FOR)
            ? $this->get(SecurityBlockerConstants::SECURITY_BLOCKER_AGENT_BLOCK_FOR) : null;
        $numberOfAttempts = $sharedConfig::hasValue(SecurityBlockerConstants::SECURITY_BLOCKER_AGENT_BLOCKING_NUMBER_OF_ATTEMPTS)
            ? $this->get(SecurityBlockerConstants::SECURITY_BLOCKER_AGENT_BLOCKING_NUMBER_OF_ATTEMPTS) : null;

        return [
            static::SECURITY_BLOCKER_AGENT_ENTITY_TYPE => (new SecurityBlockerConfigurationSettingsTransfer())
                ->setTtl($ttl)
                ->setBlockFor($blockFor)
                ->setNumberOfAttempts($numberOfAttempts),
        ];
    }

    /**
     * Specification:
     * - Returns the default security configuration.
     * - Used as fallback for all the entity types if the type-specific configuration is not given.
     *
     * @api
     */
    public function getDefaultSecurityBlockerConfigurationSettings(): SecurityBlockerConfigurationSettingsTransfer
    {
        return (new SecurityBlockerConfigurationSettingsTransfer())
            ->setTtl($this->get(SecurityBlockerConstants::SECURITY_BLOCKER_BLOCKING_TTL, static::DEFAULT_BLOCKING_TTL))
            ->setBlockFor($this->get(SecurityBlockerConstants::SECURITY_BLOCKER_BLOCK_FOR, static::DEFAULT_BLOCK_FOR))
            ->setNumberOfAttempts(
                $this->get(SecurityBlockerConstants::SECURITY_BLOCKER_BLOCKING_NUMBER_OF_ATTEMPTS, static::DEFAULT_BLOCKING_NUMBER_OF_ATTEMPTS),
            );
    }

    /**
     * Specification:
     * - Returns the login protection settings of a realm that no login realm configuration expander plugin configures.
     * - The realm property is not set; it is the realm the settings are resolved for.
     *
     * @api
     */
    public function getDefaultLoginRealmConfiguration(): SecurityBlockerLoginRealmConfigurationTransfer
    {
        return (new SecurityBlockerLoginRealmConfigurationTransfer())
            ->setAccountNumberOfAttempts(static::DEFAULT_ACCOUNT_BLOCKING_NUMBER_OF_ATTEMPTS)
            ->setAccountTtl(static::DEFAULT_ACCOUNT_BLOCKING_TTL)
            ->setAccountBlockFor(static::DEFAULT_ACCOUNT_BLOCK_FOR_SECONDS)
            ->setAccountChallengeNumberOfAttempts(static::DEFAULT_ACCOUNT_CHALLENGE_NUMBER_OF_ATTEMPTS)
            ->setIpChallengeNumberOfAttempts(static::DEFAULT_IP_CHALLENGE_NUMBER_OF_ATTEMPTS)
            ->setGlobalChallengeNumberOfAttempts(static::DEFAULT_GLOBAL_CHALLENGE_NUMBER_OF_ATTEMPTS)
            ->setGlobalTtl(static::DEFAULT_GLOBAL_CHALLENGE_TTL);
    }

    /**
     * Specification:
     * - Returns the maximum number of characters of a login identifier that are taken into account when counting login attempts per account.
     *
     * @api
     */
    public function getLoginIdentifierMaxLength(): int
    {
        return static::LOGIN_IDENTIFIER_MAX_LENGTH;
    }

    /**
     * Specification:
     * - Returns the characters a sharp s (`ß`) of a login identifier is replaced with when counting login attempts per account.
     * - Must match how the database compares `ß`: `s` for `utf8mb3_general_ci` (the default), `ss` for `utf8_unicode_ci`.
     *
     * @api
     */
    public function getLoginIdentifierSharpSEquivalent(): string
    {
        return static::LOGIN_IDENTIFIER_SHARP_S_EQUIVALENT;
    }

    /**
     * Specification:
     * - Returns `true` when account locks and realm-wide CAPTCHA activations are written to the `security` audit log channel.
     * - Disabled by default.
     *
     * @api
     */
    public function isSecurityAuditLogEnabled(): bool
    {
        return static::IS_SECURITY_AUDIT_LOG_ENABLED;
    }

    /**
     * @deprecated Use $this->get(SecurityBlockerConstants::SECURITY_BLOCKER_REDIS_SCHEME) instead. Added for BC reason only.
     */
    protected function getZedSessionScheme(): string
    {
        return $this->get(SecurityBlockerConstants::SECURITY_BLOCKER_REDIS_SCHEME, false) ?:
            $this->get(SecurityBlockerConstants::SECURITY_BLOCKER_REDIS_PROTOCOL);
    }
}
