<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Client\SecurityBlocker\Client;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\SecurityCheckAuthContextTransfer;
use Spryker\Client\SecurityBlocker\SecurityBlockerConfig as ClientSecurityBlockerConfig;
use SprykerTest\Client\SecurityBlocker\Helper\InMemorySecurityBlockerRedisClient;
use SprykerTest\Client\SecurityBlocker\SecurityBlockerClientTester;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group Client
 * @group SecurityBlocker
 * @group Client
 * @group LegacyLoginAttemptContractTest
 * Add your own group annotations below this line
 */
class LegacyLoginAttemptContractTest extends Unit
{
    protected const string TYPE_CUSTOMER = 'customer';

    protected const string IP = '203.0.113.1';

    protected const string ACCOUNT_PASSWORD_RESET = 'password-reset';

    protected SecurityBlockerClientTester $tester;

    protected InMemorySecurityBlockerRedisClient $inMemorySecurityBlockerRedisClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inMemorySecurityBlockerRedisClient = $this->tester->haveInMemorySecurityBlockerRedisClient();
        $this->tester->haveLoginProtectionThresholds();
    }

    /**
     * Password-reset, change-email and cart-code callers rely on the per-IP key and must not start feeding the account lock.
     */
    public function testIncrementLoginAttemptCountWritesOnlyTheExistingIpKey(): void
    {
        // Act
        $this->tester->getSecurityBlockerClient()->incrementLoginAttemptCount($this->createPasswordResetContext());

        // Assert
        $this->assertSame(
            [sprintf('kv:%s:%s:%s', static::TYPE_CUSTOMER, static::IP, static::ACCOUNT_PASSWORD_RESET)],
            $this->inMemorySecurityBlockerRedisClient->getStoredKeys($this->getRedisConnectionKey()),
        );
    }

    public function testIsAccountBlockedKeepsReadingTheExistingIpKey(): void
    {
        // Arrange
        for ($i = 0; $i < SecurityBlockerClientTester::IP_NUMBER_OF_ATTEMPTS; $i++) {
            $this->tester->getSecurityBlockerClient()->incrementLoginAttemptCount($this->createPasswordResetContext());
        }

        // Act
        $securityCheckAuthResponseTransfer = $this->tester->getSecurityBlockerClient()->isAccountBlocked($this->createPasswordResetContext());

        // Assert
        $this->assertTrue($securityCheckAuthResponseTransfer->getIsBlocked());
    }

    public function testLoginAttemptMethodsNeverWriteTheKeyOfTheLegacyCallers(): void
    {
        // Arrange
        $loginContextTransfer = $this->tester->createLoginAttemptContext(SecurityBlockerClientTester::REALM, static::TYPE_CUSTOMER, static::IP, 'victim@example.com');

        // Act
        $this->tester->getSecurityBlockerClient()->reserveLoginAttempt($loginContextTransfer);
        $this->tester->getSecurityBlockerClient()->registerFailedLoginAttempt($loginContextTransfer);
        $this->tester->getSecurityBlockerClient()->registerSuccessfulLoginAttempt($loginContextTransfer);

        // Assert
        $this->assertNull($this->inMemorySecurityBlockerRedisClient->get(
            $this->getRedisConnectionKey(),
            sprintf('kv:%s:%s:%s', static::TYPE_CUSTOMER, static::IP, static::ACCOUNT_PASSWORD_RESET),
        ));
    }

    protected function createPasswordResetContext(): SecurityCheckAuthContextTransfer
    {
        return (new SecurityCheckAuthContextTransfer())
            ->setType(static::TYPE_CUSTOMER)
            ->setIp(static::IP)
            ->setAccount(static::ACCOUNT_PASSWORD_RESET);
    }

    protected function getRedisConnectionKey(): string
    {
        return (new ClientSecurityBlockerConfig())->getRedisConnectionKey();
    }
}
