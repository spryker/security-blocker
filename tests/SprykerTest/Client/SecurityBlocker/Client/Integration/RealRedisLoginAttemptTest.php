<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Client\SecurityBlocker\Client\Integration;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\SecurityCheckAuthContextTransfer;
use Spryker\Client\SecurityBlocker\Redis\SecurityBlockerRedisWrapperInterface;
use Spryker\Client\SecurityBlocker\SecurityBlockerFactory;
use SprykerTest\Client\SecurityBlocker\SecurityBlockerClientTester;
use Throwable;

/**
 * Runs the account counter against the Redis server and adapter the project is configured with: the in-memory double
 * cannot show how a real adapter answers `SET NX`, returns `INCR` results, or keeps a TTL.
 *
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group Client
 * @group SecurityBlocker
 * @group Client
 * @group Integration
 * @group RealRedisLoginAttemptTest
 * Add your own group annotations below this line
 */
class RealRedisLoginAttemptTest extends Unit
{
    protected SecurityBlockerClientTester $tester;

    protected SecurityCheckAuthContextTransfer $securityCheckAuthContextTransfer;

    protected bool $isRedisReachable = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tester->haveLoginProtectionThresholds();
        $this->securityCheckAuthContextTransfer = $this->tester->createLoginAttemptContext(
            SecurityBlockerClientTester::REALM,
            'customer',
            '203.0.113.1',
            sprintf('real-redis-%s@example.com', uniqid('', true)),
        );

        try {
            $this->tester->getSecurityBlockerClient()->checkLoginAttempt($this->securityCheckAuthContextTransfer);
            $this->isRedisReachable = true;
        } catch (Throwable $throwable) {
            $this->markTestSkipped(sprintf(
                'Needs the security blocker Redis (SecurityBlockerConstants::SECURITY_BLOCKER_REDIS_HOST): %s',
                $throwable->getMessage(),
            ));
        }
    }

    /**
     * PHPUnit runs tearDown() for a test skipped in setUp() too; without Redis there is nothing to clean up.
     */
    protected function tearDown(): void
    {
        if ($this->isRedisReachable) {
            $this->tester->getSecurityBlockerClient()->registerSuccessfulLoginAttempt($this->securityCheckAuthContextTransfer);
        }

        parent::tearDown();
    }

    public function testReservationsCountExactlyAndBlockOnceTheAccountLimitIsExceeded(): void
    {
        // Arrange
        $numberOfAttempts = [];

        for ($i = 0; $i < SecurityBlockerClientTester::ACCOUNT_NUMBER_OF_ATTEMPTS; $i++) {
            $securityCheckAuthResponseTransfer = $this->tester->getSecurityBlockerClient()->reserveLoginAttempt($this->securityCheckAuthContextTransfer);
            $this->assertFalse($securityCheckAuthResponseTransfer->getIsBlocked());
            $numberOfAttempts[] = $securityCheckAuthResponseTransfer->getNumberOfAttempts();
        }

        // Act
        $blockedResponseTransfer = $this->tester->getSecurityBlockerClient()->reserveLoginAttempt($this->securityCheckAuthContextTransfer);
        $checkResponseTransfer = $this->tester->getSecurityBlockerClient()->checkLoginAttempt($this->securityCheckAuthContextTransfer);

        // Assert
        $this->assertSame(range(1, SecurityBlockerClientTester::ACCOUNT_NUMBER_OF_ATTEMPTS), $numberOfAttempts);
        $this->assertTrue($blockedResponseTransfer->getIsBlocked());
        $this->assertSame(SecurityBlockerClientTester::ACCOUNT_NUMBER_OF_ATTEMPTS + 1, $blockedResponseTransfer->getNumberOfAttempts());
        $this->assertTrue($checkResponseTransfer->getIsBlocked());
    }

    public function testSuccessfulLoginSubtractsOnlyItsOwnFailuresFromTheIpCounter(): void
    {
        // Arrange
        $securityCheckAuthContextTransfer = $this->createContextOnUnusedIp();
        $this->tester->registerFailedLoginAttempts($securityCheckAuthContextTransfer, 3);
        $this->tester->registerFailedLoginAttempts($this->createOtherAccountContext($securityCheckAuthContextTransfer), 2);

        // Act
        $this->tester->getSecurityBlockerClient()->registerSuccessfulLoginAttempt($securityCheckAuthContextTransfer);

        // Assert
        $this->assertSame(2, $this->readIpCount($securityCheckAuthContextTransfer));
    }

    public function testSuccessfulLoginRemovesTheIpCounterWhenOnlyItsOwnFailuresAreCounted(): void
    {
        // Arrange
        $securityCheckAuthContextTransfer = $this->createContextOnUnusedIp();
        $this->tester->registerFailedLoginAttempts($securityCheckAuthContextTransfer, 3);

        // Act
        $this->tester->getSecurityBlockerClient()->registerSuccessfulLoginAttempt($securityCheckAuthContextTransfer);

        // Assert
        $this->assertNull($this->findStoredIpCount($securityCheckAuthContextTransfer));
    }

    /**
     * A decrement of a missing key would recreate it as a negative count without an expiry, which would cancel the
     * next window's failures for good.
     */
    public function testSuccessfulLoginDoesNotRecreateAnExpiredIpCounter(): void
    {
        // Arrange
        $securityCheckAuthContextTransfer = $this->createContextOnUnusedIp();
        $this->tester->registerFailedLoginAttempts($securityCheckAuthContextTransfer, 2);
        $this->createSecurityBlockerRedisWrapper()->del($this->getIpCounterKey($securityCheckAuthContextTransfer));

        // Act
        $this->tester->getSecurityBlockerClient()->registerSuccessfulLoginAttempt($securityCheckAuthContextTransfer);

        // Assert
        $this->assertNull($this->findStoredIpCount($securityCheckAuthContextTransfer));
    }

    public function testSuccessfulLoginDeletesTheAccountCounter(): void
    {
        // Arrange
        $this->tester->reserveLoginAttempts($this->securityCheckAuthContextTransfer, SecurityBlockerClientTester::ACCOUNT_NUMBER_OF_ATTEMPTS);

        // Act
        $this->tester->getSecurityBlockerClient()->registerSuccessfulLoginAttempt($this->securityCheckAuthContextTransfer);

        // Assert
        $securityCheckAuthResponseTransfer = $this->tester->getSecurityBlockerClient()->reserveLoginAttempt($this->securityCheckAuthContextTransfer);
        $this->assertFalse($securityCheckAuthResponseTransfer->getIsBlocked());
        $this->assertSame(1, $securityCheckAuthResponseTransfer->getNumberOfAttempts());
    }

    protected function createContextOnUnusedIp(): SecurityCheckAuthContextTransfer
    {
        return (clone $this->securityCheckAuthContextTransfer)->setIp(sprintf('2001:db8::%x:%x', random_int(0, 0xffff), random_int(0, 0xffff)));
    }

    protected function createOtherAccountContext(SecurityCheckAuthContextTransfer $securityCheckAuthContextTransfer): SecurityCheckAuthContextTransfer
    {
        return (clone $securityCheckAuthContextTransfer)->setLoginIdentifier(sprintf('real-redis-other-%s@example.com', uniqid('', true)));
    }

    protected function readIpCount(SecurityCheckAuthContextTransfer $securityCheckAuthContextTransfer): int
    {
        return (int)$this->tester->getSecurityBlockerClient()
            ->checkLoginAttempt((clone $securityCheckAuthContextTransfer)->setLoginIdentifier(null))
            ->getNumberOfAttempts();
    }

    protected function findStoredIpCount(SecurityCheckAuthContextTransfer $securityCheckAuthContextTransfer): ?string
    {
        return $this->createSecurityBlockerRedisWrapper()->get($this->getIpCounterKey($securityCheckAuthContextTransfer));
    }

    protected function getIpCounterKey(SecurityCheckAuthContextTransfer $securityCheckAuthContextTransfer): string
    {
        return (new SecurityBlockerFactory())->createSecurityBlockerStorageKeyBuilder()->getStorageKey($securityCheckAuthContextTransfer);
    }

    protected function createSecurityBlockerRedisWrapper(): SecurityBlockerRedisWrapperInterface
    {
        return (new SecurityBlockerFactory())->createSecurityBlockerRedisWrapper();
    }
}
