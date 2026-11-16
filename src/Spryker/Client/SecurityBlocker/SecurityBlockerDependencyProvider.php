<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Client\SecurityBlocker;

use Spryker\Client\Kernel\AbstractDependencyProvider;
use Spryker\Client\Kernel\Container;
use Spryker\Client\SecurityBlocker\Dependency\Client\SecurityBlockerToRedisClientBridge;
use Spryker\Service\Container\ContainerTrait;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @method \Spryker\Client\SecurityBlocker\SecurityBlockerConfig getConfig()
 */
class SecurityBlockerDependencyProvider extends AbstractDependencyProvider
{
    use ContainerTrait;

    /**
     * @var string
     */
    public const CLIENT_REDIS = 'CLIENT_REDIS';

    /**
     * @var string
     */
    public const PLUGINS_SECURITY_BLOCKER_CONFIGURATION_SETTINGS_EXPANDER = 'PLUGINS_SECURITY_BLOCKER_CONFIGURATION_SETTINGS_EXPANDER';

    public const string PLUGINS_SECURITY_BLOCKER_LOGIN_REALM_CONFIGURATION_EXPANDER = 'PLUGINS_SECURITY_BLOCKER_LOGIN_REALM_CONFIGURATION_EXPANDER';

    public const string PLUGINS_SECURITY_BLOCKER_LOGIN_CHALLENGE_STRATEGY = 'PLUGINS_SECURITY_BLOCKER_LOGIN_CHALLENGE_STRATEGY';

    /**
     * @uses \Spryker\Yves\Http\Plugin\Application\HttpApplicationPlugin::SERVICE_REQUEST_STACK
     */
    public const string SERVICE_REQUEST_STACK = 'request_stack';

    public function provideServiceLayerDependencies(Container $container): Container
    {
        $container = parent::provideServiceLayerDependencies($container);
        $container = $this->addRedisClient($container);
        $container = $this->addSecurityBlockerConfigurationSettingsExpanderPlugins($container);
        $container = $this->addSecurityBlockerLoginRealmConfigurationExpanderPlugins($container);
        $container = $this->addSecurityBlockerLoginChallengeStrategyPlugins($container);
        $container = $this->addRequestStack($container);

        return $container;
    }

    protected function addRedisClient(Container $container): Container
    {
        $container->set(static::CLIENT_REDIS, function (Container $container) {
            return new SecurityBlockerToRedisClientBridge(
                $container->getLocator()->redis()->client(),
            );
        });

        return $container;
    }

    protected function addRequestStack(Container $container): Container
    {
        $container->set(static::SERVICE_REQUEST_STACK, function (Container $container): RequestStack {
            if ($container->hasApplicationService(static::SERVICE_REQUEST_STACK)) {
                return $container->getApplicationService(static::SERVICE_REQUEST_STACK);
            }

            return $this->findDependencyInjectionRequestStack() ?? new RequestStack();
        });

        return $container;
    }

    protected function findDependencyInjectionRequestStack(): ?RequestStack
    {
        $requestStack = $this->findService(static::SERVICE_REQUEST_STACK);

        return $requestStack instanceof RequestStack ? $requestStack : null;
    }

    protected function addSecurityBlockerConfigurationSettingsExpanderPlugins(Container $container): Container
    {
        $container->set(static::PLUGINS_SECURITY_BLOCKER_CONFIGURATION_SETTINGS_EXPANDER, function () {
            return $this->getSecurityBlockerConfigurationSettingsExpanderPlugins();
        });

        return $container;
    }

    protected function addSecurityBlockerLoginRealmConfigurationExpanderPlugins(Container $container): Container
    {
        $container->set(static::PLUGINS_SECURITY_BLOCKER_LOGIN_REALM_CONFIGURATION_EXPANDER, function () {
            return $this->getSecurityBlockerLoginRealmConfigurationExpanderPlugins();
        });

        return $container;
    }

    protected function addSecurityBlockerLoginChallengeStrategyPlugins(Container $container): Container
    {
        $container->set(static::PLUGINS_SECURITY_BLOCKER_LOGIN_CHALLENGE_STRATEGY, function () {
            return $this->getSecurityBlockerLoginChallengeStrategyPlugins();
        });

        return $container;
    }

    /**
     * @return list<\Spryker\Client\SecurityBlockerExtension\Dependency\Plugin\SecurityBlockerConfigurationSettingsExpanderPluginInterface>
     */
    protected function getSecurityBlockerConfigurationSettingsExpanderPlugins(): array
    {
        return [];
    }

    /**
     * @return list<\Spryker\Client\SecurityBlockerExtension\Dependency\Plugin\SecurityBlockerLoginRealmConfigurationExpanderPluginInterface>
     */
    protected function getSecurityBlockerLoginRealmConfigurationExpanderPlugins(): array
    {
        return [];
    }

    /**
     * @return list<\Spryker\Client\SecurityBlockerExtension\Dependency\Plugin\SecurityBlockerLoginChallengeStrategyPluginInterface>
     */
    protected function getSecurityBlockerLoginChallengeStrategyPlugins(): array
    {
        return [];
    }
}
