<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Client\SecurityBlocker\Client;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\SecurityBlockerLoginRealmConfigurationTransfer;
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
 * @group ReserveLoginAttemptTest
 * Add your own group annotations below this line
 */
class ReserveLoginAttemptTest extends Unit
{
    protected const string TYPE_CUSTOMER = 'customer';

    protected const string IP = '203.0.113.1';

    protected const string LOGIN_IDENTIFIER_VICTIM = 'victim@example.com';

    protected SecurityBlockerClientTester $tester;

    protected InMemorySecurityBlockerRedisClient $inMemorySecurityBlockerRedisClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inMemorySecurityBlockerRedisClient = $this->tester->haveInMemorySecurityBlockerRedisClient();
        $this->tester->haveLoginProtectionThresholds();
    }

    /**
     * Reservations happen before authentication, so a parallel burst cannot authenticate more often than the limit.
     */
    public function testReserveLoginAttemptAllowsExactlyTheLimitAndBlocksTheNextAttempt(): void
    {
        // Arrange
        $lastAllowedResponseTransfer = $this->tester->reserveLoginAttempts($this->createVictimContext(), SecurityBlockerClientTester::ACCOUNT_NUMBER_OF_ATTEMPTS);

        // Act
        $securityCheckAuthResponseTransfer = $this->tester->getSecurityBlockerClient()->reserveLoginAttempt($this->createVictimContext());

        // Assert
        $this->assertFalse($lastAllowedResponseTransfer->getIsBlocked());
        $this->assertTrue($securityCheckAuthResponseTransfer->getIsBlocked());
        $this->assertSame(SecurityBlockerClientTester::ACCOUNT_BLOCK_FOR, $securityCheckAuthResponseTransfer->getBlockedFor());
        $this->assertSame(SecurityBlockerClientTester::ACCOUNT_NUMBER_OF_ATTEMPTS + 1, $securityCheckAuthResponseTransfer->getNumberOfAttempts());
    }

    public function testReserveLoginAttemptStartsTheWindowWithTheFirstAttempt(): void
    {
        // Arrange
        $this->inMemorySecurityBlockerRedisClient->setCurrentTime(SecurityBlockerClientTester::CURRENT_TIME);

        // Act
        $this->tester->getSecurityBlockerClient()->reserveLoginAttempt($this->createVictimContext());

        // Assert
        $this->assertSame(SecurityBlockerClientTester::CURRENT_TIME + SecurityBlockerClientTester::ACCOUNT_TTL, $this->getAccountKeyExpiresAt());
    }

    public function testReserveLoginAttemptStartsTheLockOnceAndNeverExtendsIt(): void
    {
        // Arrange
        $this->inMemorySecurityBlockerRedisClient->setCurrentTime(SecurityBlockerClientTester::CURRENT_TIME);
        $this->tester->reserveLoginAttempts($this->createVictimContext(), SecurityBlockerClientTester::ACCOUNT_NUMBER_OF_ATTEMPTS);
        $this->inMemorySecurityBlockerRedisClient->setCurrentTime(SecurityBlockerClientTester::CURRENT_TIME + SecurityBlockerClientTester::LATER_ATTEMPTS_DELAY);

        // Act
        $this->tester->reserveLoginAttempts($this->createVictimContext(), 10);

        // Assert
        $this->assertSame(SecurityBlockerClientTester::CURRENT_TIME + SecurityBlockerClientTester::ACCOUNT_BLOCK_FOR, $this->getAccountKeyExpiresAt());
    }

    public function testReserveLoginAttemptCountsAcrossIps(): void
    {
        // Arrange
        for ($i = 0; $i < SecurityBlockerClientTester::ACCOUNT_NUMBER_OF_ATTEMPTS; $i++) {
            $this->tester->getSecurityBlockerClient()->reserveLoginAttempt($this->createVictimContext()->setIp(sprintf('192.0.2.%d', $i)));
        }

        // Act
        $securityCheckAuthResponseTransfer = $this->tester->getSecurityBlockerClient()->reserveLoginAttempt($this->createVictimContext()->setIp('198.51.100.99'));

        // Assert
        $this->assertTrue($securityCheckAuthResponseTransfer->getIsBlocked());
    }

    public function testReserveLoginAttemptDoesNotTouchTheIpOrTheRealmWideCounter(): void
    {
        // Act
        $this->tester->reserveLoginAttempts($this->createVictimContext(), 3);

        // Assert
        $storedKeys = $this->inMemorySecurityBlockerRedisClient->getStoredKeys($this->getRedisConnectionKey());
        $this->assertCount(1, $storedKeys);
        $this->assertStringStartsWith(sprintf(SecurityBlockerClientTester::ACCOUNT_STORAGE_KEY_PATTERN, SecurityBlockerClientTester::REALM, ''), $storedKeys[0]);
    }

    public function testReserveLoginAttemptDoesNothingForAnEmptyIdentifier(): void
    {
        // Arrange
        $emptyIdentifierContextTransfer = $this->createVictimContext()->setLoginIdentifier(" \u{00A0}");

        // Act
        $securityCheckAuthResponseTransfer = $this->tester->reserveLoginAttempts($emptyIdentifierContextTransfer, SecurityBlockerClientTester::ACCOUNT_NUMBER_OF_ATTEMPTS + 1);

        // Assert
        $this->assertFalse($securityCheckAuthResponseTransfer->getIsBlocked());
        $this->assertSame([], $this->inMemorySecurityBlockerRedisClient->getStoredKeys($this->getRedisConnectionKey()));
    }

    public function testReserveLoginAttemptNeverBlocksWhenTheAccountLimitIsZero(): void
    {
        // Arrange
        $this->tester->haveLoginRealmConfiguration(SecurityBlockerClientTester::REALM, [
            SecurityBlockerLoginRealmConfigurationTransfer::ACCOUNT_NUMBER_OF_ATTEMPTS => 0,
        ]);

        // Act
        $securityCheckAuthResponseTransfer = $this->tester->reserveLoginAttempts($this->createVictimContext(), SecurityBlockerClientTester::ACCOUNT_NUMBER_OF_ATTEMPTS * 2);

        // Assert
        $this->assertFalse($securityCheckAuthResponseTransfer->getIsBlocked());
    }

    protected function createVictimContext(): SecurityCheckAuthContextTransfer
    {
        return $this->tester->createLoginAttemptContext(SecurityBlockerClientTester::REALM, static::TYPE_CUSTOMER, static::IP, static::LOGIN_IDENTIFIER_VICTIM);
    }

    protected function getAccountKeyExpiresAt(): ?int
    {
        return $this->inMemorySecurityBlockerRedisClient->getExpiresAt(
            $this->getRedisConnectionKey(),
            sprintf(SecurityBlockerClientTester::ACCOUNT_STORAGE_KEY_PATTERN, SecurityBlockerClientTester::REALM, hash('sha256', static::LOGIN_IDENTIFIER_VICTIM)),
        );
    }

    protected function getRedisConnectionKey(): string
    {
        return (new ClientSecurityBlockerConfig())->getRedisConnectionKey();
    }
}
