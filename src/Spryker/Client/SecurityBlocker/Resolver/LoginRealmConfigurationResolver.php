<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Client\SecurityBlocker\Resolver;

use Generated\Shared\Transfer\SecurityBlockerLoginRealmConfigurationTransfer;
use Psr\Log\LoggerInterface;
use Spryker\Client\SecurityBlocker\SecurityBlockerConfig;

class LoginRealmConfigurationResolver implements LoginRealmConfigurationResolverInterface
{
    protected const string LOG_MESSAGE_REALM_NOT_CONFIGURED = 'Login realm "%s" is not configured by any SecurityBlockerDependencyProvider::getSecurityBlockerLoginRealmConfigurationExpanderPlugins() plugin: the default login realm configuration applies.';

    /**
     * @param list<\Spryker\Client\SecurityBlockerExtension\Dependency\Plugin\SecurityBlockerLoginRealmConfigurationExpanderPluginInterface> $securityBlockerLoginRealmConfigurationExpanderPlugins
     */
    public function __construct(
        protected array $securityBlockerLoginRealmConfigurationExpanderPlugins,
        protected SecurityBlockerConfig $securityBlockerConfig,
        protected LoggerInterface $logger,
    ) {
    }

    public function resolve(string $realm): SecurityBlockerLoginRealmConfigurationTransfer
    {
        $securityBlockerLoginRealmConfigurationTransfers = $this->executeSecurityBlockerLoginRealmConfigurationExpanderPlugins();

        if (isset($securityBlockerLoginRealmConfigurationTransfers[$realm])) {
            return $securityBlockerLoginRealmConfigurationTransfers[$realm];
        }

        $this->logger->warning(sprintf(static::LOG_MESSAGE_REALM_NOT_CONFIGURED, $realm));

        return $this->securityBlockerConfig->getDefaultLoginRealmConfiguration()->setRealm($realm);
    }

    /**
     * @return array<string, \Generated\Shared\Transfer\SecurityBlockerLoginRealmConfigurationTransfer>
     */
    protected function executeSecurityBlockerLoginRealmConfigurationExpanderPlugins(): array
    {
        $securityBlockerLoginRealmConfigurationTransfers = [];

        foreach ($this->securityBlockerLoginRealmConfigurationExpanderPlugins as $securityBlockerLoginRealmConfigurationExpanderPlugin) {
            $securityBlockerLoginRealmConfigurationTransfers = $securityBlockerLoginRealmConfigurationExpanderPlugin->expand($securityBlockerLoginRealmConfigurationTransfers);
        }

        return $securityBlockerLoginRealmConfigurationTransfers;
    }
}
