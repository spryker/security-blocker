<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Client\SecurityBlocker;

use Spryker\Client\Kernel\AbstractFactory;
use Spryker\Client\SecurityBlocker\Dependency\Client\SecurityBlockerToRedisClientInterface;
use Spryker\Client\SecurityBlocker\Redis\SecurityBlockerRedisWrapper;
use Spryker\Client\SecurityBlocker\Redis\SecurityBlockerRedisWrapperInterface;
use Spryker\Client\SecurityBlocker\Resolver\ConfigurationResolver;
use Spryker\Client\SecurityBlocker\Resolver\ConfigurationResolverInterface;
use Spryker\Client\SecurityBlocker\Storage\Checker\SecurityBlockerStorageChecker;
use Spryker\Client\SecurityBlocker\Storage\Checker\SecurityBlockerStorageCheckerInterface;
use Spryker\Client\SecurityBlocker\Storage\KeyBuilder\SecurityBlockerStorageKeyBuilder;
use Spryker\Client\SecurityBlocker\Storage\KeyBuilder\SecurityBlockerStorageKeyBuilderInterface;
use Spryker\Client\SecurityBlocker\Storage\Writer\SecurityBlockerStorageWriter;
use Spryker\Client\SecurityBlocker\Storage\Writer\SecurityBlockerStorageWriterInterface;

/**
 * @method \Spryker\Client\SecurityBlocker\SecurityBlockerConfig getConfig()
 */
class SecurityBlockerFactory extends AbstractFactory
{
    /**
     * @return \Spryker\Client\SecurityBlocker\Storage\Checker\SecurityBlockerStorageCheckerInterface
     */
    public function createSecurityBlockerStorageChecker(): SecurityBlockerStorageCheckerInterface
    {
        return new SecurityBlockerStorageChecker(
            $this->createSecurityBlockerRedisWrapper(),
            $this->createSecurityBlockerStorageKeyBuilder(),
            $this->createConfigurationResolver(),
        );
    }

    /**
     * @return \Spryker\Client\SecurityBlocker\Storage\Writer\SecurityBlockerStorageWriterInterface
     */
    public function createSecurityBlockerStorageWriter(): SecurityBlockerStorageWriterInterface
    {
        return new SecurityBlockerStorageWriter(
            $this->createSecurityBlockerRedisWrapper(),
            $this->createSecurityBlockerStorageKeyBuilder(),
            $this->createConfigurationResolver(),
        );
    }

    /**
     * @return \Spryker\Client\SecurityBlocker\Storage\KeyBuilder\SecurityBlockerStorageKeyBuilderInterface
     */
    public function createSecurityBlockerStorageKeyBuilder(): SecurityBlockerStorageKeyBuilderInterface
    {
        return new SecurityBlockerStorageKeyBuilder();
    }

    /**
     * @return \Spryker\Client\SecurityBlocker\Redis\SecurityBlockerRedisWrapperInterface
     */
    public function createSecurityBlockerRedisWrapper(): SecurityBlockerRedisWrapperInterface
    {
        return new SecurityBlockerRedisWrapper(
            $this->getRedisClient(),
            $this->getConfig(),
        );
    }

    /**
     * @return \Spryker\Client\SecurityBlocker\Resolver\ConfigurationResolverInterface
     */
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
     * @return \Spryker\Client\SecurityBlocker\Dependency\Client\SecurityBlockerToRedisClientInterface
     */
    public function getRedisClient(): SecurityBlockerToRedisClientInterface
    {
        return $this->getProvidedDependency(SecurityBlockerDependencyProvider::CLIENT_REDIS);
    }
}
