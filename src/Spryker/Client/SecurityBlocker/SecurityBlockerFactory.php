<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Client\SecurityBlocker;

use Generated\Shared\Transfer\AuditLoggerConfigCriteriaTransfer;
use Psr\Log\LoggerInterface;
use Spryker\Client\Kernel\AbstractFactory;
use Spryker\Client\SecurityBlocker\Dependency\Client\SecurityBlockerToRedisClientInterface;
use Spryker\Client\SecurityBlocker\Logger\LoginAttemptAuditLogger;
use Spryker\Client\SecurityBlocker\Logger\LoginAttemptAuditLoggerInterface;
use Spryker\Client\SecurityBlocker\LoginAttempt\LoginAttemptChecker;
use Spryker\Client\SecurityBlocker\LoginAttempt\LoginAttemptCheckerInterface;
use Spryker\Client\SecurityBlocker\LoginAttempt\LoginAttemptCheckResultRequestStorage;
use Spryker\Client\SecurityBlocker\LoginAttempt\LoginAttemptCheckResultRequestStorageInterface;
use Spryker\Client\SecurityBlocker\LoginAttempt\LoginAttemptRegistrar;
use Spryker\Client\SecurityBlocker\LoginAttempt\LoginAttemptRegistrarInterface;
use Spryker\Client\SecurityBlocker\LoginAttempt\LoginAttemptReserver;
use Spryker\Client\SecurityBlocker\LoginAttempt\LoginAttemptReserverInterface;
use Spryker\Client\SecurityBlocker\LoginAttempt\LoginChallengeRequirementChecker;
use Spryker\Client\SecurityBlocker\LoginAttempt\LoginChallengeRequirementCheckerInterface;
use Spryker\Client\SecurityBlocker\Normalizer\LoginIdentifierNormalizer;
use Spryker\Client\SecurityBlocker\Normalizer\LoginIdentifierNormalizerInterface;
use Spryker\Client\SecurityBlocker\Redis\SecurityBlockerRedisWrapper;
use Spryker\Client\SecurityBlocker\Redis\SecurityBlockerRedisWrapperInterface;
use Spryker\Client\SecurityBlocker\Resolver\ConfigurationResolver;
use Spryker\Client\SecurityBlocker\Resolver\ConfigurationResolverInterface;
use Spryker\Client\SecurityBlocker\Resolver\LoginRealmConfigurationResolver;
use Spryker\Client\SecurityBlocker\Resolver\LoginRealmConfigurationResolverInterface;
use Spryker\Client\SecurityBlocker\Storage\Checker\SecurityBlockerStorageChecker;
use Spryker\Client\SecurityBlocker\Storage\Checker\SecurityBlockerStorageCheckerInterface;
use Spryker\Client\SecurityBlocker\Storage\KeyBuilder\AccountIpCounterKeyAccountResolver;
use Spryker\Client\SecurityBlocker\Storage\KeyBuilder\IpCounterKeyAccountResolverInterface;
use Spryker\Client\SecurityBlocker\Storage\KeyBuilder\LoginAttemptKeyBuilder;
use Spryker\Client\SecurityBlocker\Storage\KeyBuilder\LoginAttemptKeyBuilderInterface;
use Spryker\Client\SecurityBlocker\Storage\KeyBuilder\LoginIdentifierIpCounterKeyAccountResolver;
use Spryker\Client\SecurityBlocker\Storage\KeyBuilder\SecurityBlockerStorageKeyBuilder;
use Spryker\Client\SecurityBlocker\Storage\KeyBuilder\SecurityBlockerStorageKeyBuilderInterface;
use Spryker\Client\SecurityBlocker\Storage\Writer\AtomicCounterWriter;
use Spryker\Client\SecurityBlocker\Storage\Writer\AtomicCounterWriterInterface;
use Spryker\Client\SecurityBlocker\Storage\Writer\IpCounterShareWriter;
use Spryker\Client\SecurityBlocker\Storage\Writer\IpCounterShareWriterInterface;
use Spryker\Client\SecurityBlocker\Storage\Writer\SecurityBlockerStorageWriter;
use Spryker\Client\SecurityBlocker\Storage\Writer\SecurityBlockerStorageWriterInterface;
use Spryker\Shared\Kernel\StrategyResolver;
use Spryker\Shared\Kernel\StrategyResolverInterface;
use Spryker\Shared\Log\AuditLoggerTrait;
use Spryker\Shared\Log\LoggerTrait;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @method \Spryker\Client\SecurityBlocker\SecurityBlockerConfig getConfig()
 */
class SecurityBlockerFactory extends AbstractFactory
{
    use AuditLoggerTrait;
    use LoggerTrait;

    /**
     * @uses \Spryker\Shared\Log\LogConfig::AUDIT_LOGGER_CHANNEL_NAME_SECURITY
     */
    protected const string AUDIT_LOGGER_CHANNEL_NAME_SECURITY = 'security';

    public function createLoginAttemptChecker(): LoginAttemptCheckerInterface
    {
        return new LoginAttemptChecker(
            $this->createLoginRealmConfigurationResolver(),
            $this->createConfigurationResolver(),
            $this->createSecurityBlockerStorageKeyBuilder(),
            $this->createLoginAttemptKeyBuilder(),
            $this->createSecurityBlockerRedisWrapper(),
            $this->getSecurityBlockerLoginChallengeStrategyPlugins(),
            $this->createLoginAttemptCheckResultRequestStorage(),
        );
    }

    public function createLoginAttemptReserver(): LoginAttemptReserverInterface
    {
        return new LoginAttemptReserver(
            $this->createLoginRealmConfigurationResolver(),
            $this->createLoginAttemptKeyBuilder(),
            $this->createAtomicCounterWriter(),
            $this->createLoginAttemptAuditLogger(),
            $this->createLoginAttemptCheckResultRequestStorage(),
        );
    }

    public function createLoginAttemptRegistrar(): LoginAttemptRegistrarInterface
    {
        return new LoginAttemptRegistrar(
            $this->createLoginRealmConfigurationResolver(),
            $this->createConfigurationResolver(),
            $this->createSecurityBlockerStorageKeyBuilder(),
            $this->createLoginAttemptKeyBuilder(),
            $this->createAtomicCounterWriter(),
            $this->createIpCounterShareWriter(),
            $this->createSecurityBlockerRedisWrapper(),
            $this->createLoginAttemptAuditLogger(),
            $this->createLoginAttemptCheckResultRequestStorage(),
        );
    }

    public function createLoginRealmConfigurationResolver(): LoginRealmConfigurationResolverInterface
    {
        return new LoginRealmConfigurationResolver(
            $this->getSecurityBlockerLoginRealmConfigurationExpanderPlugins(),
            $this->getConfig(),
            $this->getLogger(),
        );
    }

    public function createLoginIdentifierNormalizer(): LoginIdentifierNormalizerInterface
    {
        return new LoginIdentifierNormalizer($this->getConfig());
    }

    public function createLoginAttemptKeyBuilder(): LoginAttemptKeyBuilderInterface
    {
        return new LoginAttemptKeyBuilder($this->createLoginIdentifierNormalizer());
    }

    public function createAtomicCounterWriter(): AtomicCounterWriterInterface
    {
        return new AtomicCounterWriter($this->createSecurityBlockerRedisWrapper());
    }

    public function createIpCounterShareWriter(): IpCounterShareWriterInterface
    {
        return new IpCounterShareWriter(
            $this->createLoginAttemptKeyBuilder(),
            $this->createAtomicCounterWriter(),
            $this->createSecurityBlockerRedisWrapper(),
        );
    }

    public function createLoginAttemptAuditLogger(): LoginAttemptAuditLoggerInterface
    {
        return new LoginAttemptAuditLogger($this->getSecurityAuditLogger(), $this->getConfig());
    }

    public function getSecurityAuditLogger(): LoggerInterface
    {
        return $this->getAuditLogger(
            (new AuditLoggerConfigCriteriaTransfer())->setChannelName(static::AUDIT_LOGGER_CHANNEL_NAME_SECURITY),
        );
    }

    public function createSecurityBlockerStorageChecker(): SecurityBlockerStorageCheckerInterface
    {
        return new SecurityBlockerStorageChecker(
            $this->createSecurityBlockerRedisWrapper(),
            $this->createSecurityBlockerStorageKeyBuilder(),
            $this->createConfigurationResolver(),
        );
    }

    public function createSecurityBlockerStorageWriter(): SecurityBlockerStorageWriterInterface
    {
        return new SecurityBlockerStorageWriter(
            $this->createSecurityBlockerRedisWrapper(),
            $this->createSecurityBlockerStorageKeyBuilder(),
            $this->createConfigurationResolver(),
            $this->createLoginAttemptCheckResultRequestStorage(),
        );
    }

    public function createLoginAttemptCheckResultRequestStorage(): LoginAttemptCheckResultRequestStorageInterface
    {
        return new LoginAttemptCheckResultRequestStorage($this->getRequestStack());
    }

    public function createSecurityBlockerStorageKeyBuilder(): SecurityBlockerStorageKeyBuilderInterface
    {
        return new SecurityBlockerStorageKeyBuilder($this->createIpCounterKeyAccountResolverStrategyResolver());
    }

    /**
     * @return \Spryker\Shared\Kernel\StrategyResolverInterface<\Spryker\Client\SecurityBlocker\Storage\KeyBuilder\IpCounterKeyAccountResolverInterface>
     */
    public function createIpCounterKeyAccountResolverStrategyResolver(): StrategyResolverInterface
    {
        return new StrategyResolver([
            SecurityBlockerConfig::IP_COUNTER_KEY_STRATEGY_IP => fn (): IpCounterKeyAccountResolverInterface => $this->createAccountIpCounterKeyAccountResolver(),
            SecurityBlockerConfig::IP_COUNTER_KEY_STRATEGY_IP_AND_USERNAME => fn (): IpCounterKeyAccountResolverInterface => $this->createLoginIdentifierIpCounterKeyAccountResolver(),
        ]);
    }

    public function createAccountIpCounterKeyAccountResolver(): IpCounterKeyAccountResolverInterface
    {
        return new AccountIpCounterKeyAccountResolver();
    }

    public function createLoginIdentifierIpCounterKeyAccountResolver(): IpCounterKeyAccountResolverInterface
    {
        return new LoginIdentifierIpCounterKeyAccountResolver($this->createLoginAttemptKeyBuilder());
    }

    public function createLoginChallengeRequirementChecker(): LoginChallengeRequirementCheckerInterface
    {
        return new LoginChallengeRequirementChecker($this->createLoginAttemptChecker(), $this->getLogger());
    }

    public function createSecurityBlockerRedisWrapper(): SecurityBlockerRedisWrapperInterface
    {
        return new SecurityBlockerRedisWrapper(
            $this->getRedisClient(),
            $this->getConfig(),
        );
    }

    public function createConfigurationResolver(): ConfigurationResolverInterface
    {
        return new ConfigurationResolver(
            $this->getSecurityBlockerConfigurationSettingsExpanderPlugins(),
            $this->getConfig(),
        );
    }

    /**
     * @return list<\Spryker\Client\SecurityBlockerExtension\Dependency\Plugin\SecurityBlockerConfigurationSettingsExpanderPluginInterface>
     */
    public function getSecurityBlockerConfigurationSettingsExpanderPlugins(): array
    {
        return $this->getProvidedDependency(SecurityBlockerDependencyProvider::PLUGINS_SECURITY_BLOCKER_CONFIGURATION_SETTINGS_EXPANDER);
    }

    /**
     * @return list<\Spryker\Client\SecurityBlockerExtension\Dependency\Plugin\SecurityBlockerLoginRealmConfigurationExpanderPluginInterface>
     */
    public function getSecurityBlockerLoginRealmConfigurationExpanderPlugins(): array
    {
        return $this->getProvidedDependency(SecurityBlockerDependencyProvider::PLUGINS_SECURITY_BLOCKER_LOGIN_REALM_CONFIGURATION_EXPANDER);
    }

    /**
     * @return list<\Spryker\Client\SecurityBlockerExtension\Dependency\Plugin\SecurityBlockerLoginChallengeStrategyPluginInterface>
     */
    public function getSecurityBlockerLoginChallengeStrategyPlugins(): array
    {
        return $this->getProvidedDependency(SecurityBlockerDependencyProvider::PLUGINS_SECURITY_BLOCKER_LOGIN_CHALLENGE_STRATEGY);
    }

    public function getRedisClient(): SecurityBlockerToRedisClientInterface
    {
        return $this->getProvidedDependency(SecurityBlockerDependencyProvider::CLIENT_REDIS);
    }

    public function getRequestStack(): RequestStack
    {
        return $this->getProvidedDependency(SecurityBlockerDependencyProvider::SERVICE_REQUEST_STACK);
    }
}
