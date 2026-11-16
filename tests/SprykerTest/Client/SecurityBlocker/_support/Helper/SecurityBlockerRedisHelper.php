<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types = 1);

namespace SprykerTest\Client\SecurityBlocker\Helper;

use Codeception\Module;
use Codeception\TestInterface;
use Spryker\Client\SecurityBlocker\SecurityBlockerDependencyProvider;
use Spryker\Client\SecurityBlocker\SecurityBlockerFactory;
use SprykerTest\Shared\Testify\Helper\DependencyHelperTrait;

/**
 * Gives SecurityBlocker somewhere to keep its attempt counters on the docker-free host lane.
 *
 * The login and token paths run
 * {@see \Spryker\Client\SecurityBlocker\SecurityBlockerClient} on every attempt, and it reaches
 * Redis directly through {@see \Spryker\Client\SecurityBlocker\Redis\SecurityBlockerRedisWrapper}
 * rather than through the Storage client, so `SqliteStorageHelper` does not cover it and the
 * request dies on `Class "Redis" not found`. Note the wrapper opens its connection from the
 * constructor, so the failure happens on the first attempt, not on the first block.
 *
 * Binds {@see \SprykerTest\Client\SecurityBlocker\Helper\InMemorySecurityBlockerRedisClient}, which keeps the
 * real counter semantics, so blocking behaviour stays genuinely covered.
 *
 * The binding is scoped to the SecurityBlocker client factory on purpose: an unscoped `CLIENT_REDIS`
 * would be handed to every module that happens to declare a dependency of that name.
 */
class SecurityBlockerRedisHelper extends Module
{
    use DependencyHelperTrait;

    protected const string CONFIG_KEY_BIND_BEFORE_EACH_TEST = 'bindBeforeEachTest';

    /**
     * @var array<string, mixed>
     */
    protected array $config = [
        self::CONFIG_KEY_BIND_BEFORE_EACH_TEST => true,
    ];

    /**
     * A fresh client per test, so one test's attempt counters cannot block the next one.
     * Suites whose tests bind their own Redis double (e.g. a mock) switch this off and call
     * {@see haveInMemorySecurityBlockerRedisClient()} where they want the in-memory one.
     */
    public function _before(TestInterface $test): void // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
    {
        if (!$this->config[static::CONFIG_KEY_BIND_BEFORE_EACH_TEST]) {
            return;
        }

        $this->haveInMemorySecurityBlockerRedisClient();
    }

    /**
     * Binds a fresh in-memory client and returns it, so a test can assert the stored counters and their expiry.
     */
    public function haveInMemorySecurityBlockerRedisClient(): InMemorySecurityBlockerRedisClient
    {
        $inMemorySecurityBlockerRedisClient = new InMemorySecurityBlockerRedisClient();

        $this->getDependencyHelper()->setDependency(
            SecurityBlockerDependencyProvider::CLIENT_REDIS,
            $inMemorySecurityBlockerRedisClient,
            SecurityBlockerFactory::class,
        );

        return $inMemorySecurityBlockerRedisClient;
    }
}
