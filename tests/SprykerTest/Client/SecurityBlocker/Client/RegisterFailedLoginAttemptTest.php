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
use Spryker\Shared\Kernel\Transfer\Exception\NullValueException;
use SprykerTest\Client\SecurityBlocker\Helper\InMemorySecurityBlockerRedisClient;
use SprykerTest\Client\SecurityBlocker\SecurityBlockerClientTester;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group Client
 * @group SecurityBlocker
 * @group Client
 * @group RegisterFailedLoginAttemptTest
 * Add your own group annotations below this line
 */
class RegisterFailedLoginAttemptTest extends Unit
{
    protected const string TYPE_CUSTOMER = 'customer';

    protected const string TYPE_OTHER_SURFACE = 'other-surface';

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

    public function testRegisterFailedLoginAttemptRequiresType(): void
    {
        // Arrange
        $securityCheckAuthContextTransfer = $this->createVictimContext()->setType(null);

        // Assert
        $this->expectException(NullValueException::class);

        // Act
        $this->tester->getSecurityBlockerClient()->registerFailedLoginAttempt($securityCheckAuthContextTransfer);
    }

    public function testRegisterFailedLoginAttemptRequiresRealm(): void
    {
        // Arrange
        $securityCheckAuthContextTransfer = $this->createVictimContext()->setRealm(null);

        // Assert
        $this->expectException(NullValueException::class);

        // Act
        $this->tester->getSecurityBlockerClient()->registerFailedLoginAttempt($securityCheckAuthContextTransfer);
    }

    public function testRegisterFailedLoginAttemptIncrementsTheIpAndTheRealmWideCounterButNotTheAccount(): void
    {
        // Act
        $this->tester->registerFailedLoginAttempts($this->createVictimContext(), 2);

        // Assert
        $this->assertSame('2', $this->getStoredValue(sprintf('kv:%s:%s:', static::TYPE_CUSTOMER, static::IP)));
        $this->assertSame('2', $this->getStoredValue(sprintf(SecurityBlockerClientTester::GLOBAL_STORAGE_KEY_PATTERN, SecurityBlockerClientTester::REALM)));
        $this->assertNull($this->getStoredValue(sprintf(SecurityBlockerClientTester::ACCOUNT_STORAGE_KEY_PATTERN, SecurityBlockerClientTester::REALM, hash('sha256', static::LOGIN_IDENTIFIER_VICTIM))));
    }

    public function testRegisterFailedLoginAttemptStopsFeedingTheRealmWideCounterOnceTheIpChallengeThresholdIsPassed(): void
    {
        // Act
        $this->tester->registerFailedLoginAttempts($this->createVictimContext(), SecurityBlockerClientTester::IP_CHALLENGE_NUMBER_OF_ATTEMPTS + 3);

        // Assert
        $this->assertSame(
            (string)SecurityBlockerClientTester::IP_CHALLENGE_NUMBER_OF_ATTEMPTS,
            $this->getStoredValue(sprintf(SecurityBlockerClientTester::GLOBAL_STORAGE_KEY_PATTERN, SecurityBlockerClientTester::REALM)),
        );
    }

    public function testRegisterFailedLoginAttemptCapsWhatOneIpFeedsToTheRealmWideCounterWhenEveryAttemptUsesANewAccount(): void
    {
        // Act
        $this->registerFailedLoginAttemptsWithNewAccounts(SecurityBlockerClientTester::IP_CHALLENGE_NUMBER_OF_ATTEMPTS + 3);

        // Assert
        $this->assertSame(
            (string)SecurityBlockerClientTester::IP_CHALLENGE_NUMBER_OF_ATTEMPTS,
            $this->getStoredValue(sprintf(SecurityBlockerClientTester::GLOBAL_STORAGE_KEY_PATTERN, SecurityBlockerClientTester::REALM)),
        );
    }

    public function testRegisterFailedLoginAttemptCapsWhatOneIpFeedsToTheRealmWideCounterAcrossTheLoginTypesOfTheRealm(): void
    {
        // Arrange
        $this->inMemorySecurityBlockerRedisClient->setCurrentTime(SecurityBlockerClientTester::CURRENT_TIME);
        $this->tester->registerFailedLoginAttempts($this->createVictimContext(), SecurityBlockerClientTester::IP_CHALLENGE_NUMBER_OF_ATTEMPTS);

        // Act
        $this->tester->registerFailedLoginAttempts($this->createVictimContext()->setType(static::TYPE_OTHER_SURFACE), 3);

        // Assert
        $this->assertSame(
            (string)SecurityBlockerClientTester::IP_CHALLENGE_NUMBER_OF_ATTEMPTS,
            $this->getStoredValue(sprintf(SecurityBlockerClientTester::GLOBAL_STORAGE_KEY_PATTERN, SecurityBlockerClientTester::REALM)),
        );
        $this->assertSame(
            SecurityBlockerClientTester::CURRENT_TIME + SecurityBlockerClientTester::GLOBAL_CHALLENGE_TTL,
            $this->inMemorySecurityBlockerRedisClient->getExpiresAt($this->getRedisConnectionKey(), sprintf(SecurityBlockerClientTester::IP_STORAGE_KEY_PATTERN, SecurityBlockerClientTester::REALM, static::IP)),
        );
    }

    public function testRegisterFailedLoginAttemptFeedsEveryFailureToTheRealmWideCounterWhenTheIpChallengeIsDisabled(): void
    {
        // Arrange
        $this->tester->haveLoginRealmConfiguration(SecurityBlockerClientTester::REALM, [
            SecurityBlockerLoginRealmConfigurationTransfer::IP_CHALLENGE_NUMBER_OF_ATTEMPTS => 0,
        ]);

        // Act
        $this->tester->registerFailedLoginAttempts($this->createVictimContext(), SecurityBlockerClientTester::IP_CHALLENGE_NUMBER_OF_ATTEMPTS + 3);

        // Assert
        $this->assertSame(
            (string)(SecurityBlockerClientTester::IP_CHALLENGE_NUMBER_OF_ATTEMPTS + 3),
            $this->getStoredValue(sprintf(SecurityBlockerClientTester::GLOBAL_STORAGE_KEY_PATTERN, SecurityBlockerClientTester::REALM)),
        );
    }

    public function testRegisterFailedLoginAttemptKeepsTheExistingIpKeyFormatIncludingTheAccountPart(): void
    {
        // Arrange
        $securityCheckAuthContextTransfer = $this->createVictimContext()->setAccount(static::LOGIN_IDENTIFIER_VICTIM);

        // Act
        $this->tester->getSecurityBlockerClient()->registerFailedLoginAttempt($securityCheckAuthContextTransfer);

        // Assert
        $this->assertSame('1', $this->getStoredValue(sprintf('kv:%s:%s:%s', static::TYPE_CUSTOMER, static::IP, static::LOGIN_IDENTIFIER_VICTIM)));
    }

    public function testRegisterFailedLoginAttemptExpiresTheShareOfTheLoginIdentifierTogetherWithTheIpCounter(): void
    {
        // Arrange
        $this->inMemorySecurityBlockerRedisClient->setCurrentTime(SecurityBlockerClientTester::CURRENT_TIME);
        $this->tester->registerFailedLoginAttempts($this->createVictimContext()->setLoginIdentifier('other@example.com'), 1);
        $this->inMemorySecurityBlockerRedisClient->setCurrentTime(SecurityBlockerClientTester::CURRENT_TIME + SecurityBlockerClientTester::LATER_ATTEMPTS_DELAY);

        // Act
        $this->tester->getSecurityBlockerClient()->registerFailedLoginAttempt($this->createVictimContext());

        // Assert
        $this->assertSame(
            $this->inMemorySecurityBlockerRedisClient->getExpiresAt($this->getRedisConnectionKey(), sprintf('kv:%s:%s:', static::TYPE_CUSTOMER, static::IP)),
            $this->inMemorySecurityBlockerRedisClient->getExpiresAt(
                $this->getRedisConnectionKey(),
                sprintf('kv:login-ip-share:%s:%s:%s:', hash('sha256', static::LOGIN_IDENTIFIER_VICTIM), static::TYPE_CUSTOMER, static::IP),
            ),
        );
    }

    public function testRegisterFailedLoginAttemptBlocksTheIpAtTheTypeLimitAndStartsTheLock(): void
    {
        // Arrange
        $this->inMemorySecurityBlockerRedisClient->setCurrentTime(SecurityBlockerClientTester::CURRENT_TIME);

        // Act
        $securityCheckAuthResponseTransfer = $this->tester->registerFailedLoginAttempts($this->createVictimContext(), SecurityBlockerClientTester::IP_NUMBER_OF_ATTEMPTS);

        // Assert
        $this->assertTrue($securityCheckAuthResponseTransfer->getIsBlocked());
        $this->assertSame(SecurityBlockerClientTester::IP_NUMBER_OF_ATTEMPTS, $securityCheckAuthResponseTransfer->getNumberOfAttempts());
        $this->assertSame(
            SecurityBlockerClientTester::CURRENT_TIME + SecurityBlockerClientTester::IP_BLOCK_FOR,
            $this->inMemorySecurityBlockerRedisClient->getExpiresAt($this->getRedisConnectionKey(), sprintf('kv:%s:%s:', static::TYPE_CUSTOMER, static::IP)),
        );
    }

    public function testRegisterFailedLoginAttemptStartsTheRealmWideWindowWithTheFirstFailure(): void
    {
        // Arrange
        $this->inMemorySecurityBlockerRedisClient->setCurrentTime(SecurityBlockerClientTester::CURRENT_TIME);

        // Act
        $this->tester->registerFailedLoginAttempts($this->createVictimContext(), 2);

        // Assert
        $this->assertSame(
            SecurityBlockerClientTester::CURRENT_TIME + SecurityBlockerClientTester::GLOBAL_CHALLENGE_TTL,
            $this->inMemorySecurityBlockerRedisClient->getExpiresAt($this->getRedisConnectionKey(), sprintf(SecurityBlockerClientTester::GLOBAL_STORAGE_KEY_PATTERN, SecurityBlockerClientTester::REALM)),
        );
    }

    public function testRealmWideCounterNeverStartsALockHoweverManyFailuresAreRegistered(): void
    {
        // Arrange
        $this->inMemorySecurityBlockerRedisClient->setCurrentTime(SecurityBlockerClientTester::CURRENT_TIME);
        $failureCount = SecurityBlockerClientTester::GLOBAL_CHALLENGE_NUMBER_OF_ATTEMPTS + 50;

        for ($i = 0; $i < $failureCount; $i++) {
            $this->tester->getSecurityBlockerClient()->registerFailedLoginAttempt(
                $this->tester->createLoginAttemptContext(SecurityBlockerClientTester::REALM, static::TYPE_CUSTOMER, sprintf('10.0.%d.%d', intdiv($i, 250), $i % 250), sprintf('sprayed-%d@example.com', $i)),
            );
        }

        // Act
        $securityCheckAuthResponseTransfer = $this->tester->getSecurityBlockerClient()->checkLoginAttempt(
            $this->tester->createLoginAttemptContext(SecurityBlockerClientTester::REALM, static::TYPE_CUSTOMER, '192.0.2.250', 'fresh@example.com'),
        );

        // Assert
        $this->assertFalse($securityCheckAuthResponseTransfer->getIsBlocked());
        $this->assertTrue($securityCheckAuthResponseTransfer->getIsChallengeRequired());
        $this->assertSame((string)$failureCount, $this->getStoredValue(sprintf(SecurityBlockerClientTester::GLOBAL_STORAGE_KEY_PATTERN, SecurityBlockerClientTester::REALM)));
        $this->assertSame(
            SecurityBlockerClientTester::CURRENT_TIME + SecurityBlockerClientTester::GLOBAL_CHALLENGE_TTL,
            $this->inMemorySecurityBlockerRedisClient->getExpiresAt($this->getRedisConnectionKey(), sprintf(SecurityBlockerClientTester::GLOBAL_STORAGE_KEY_PATTERN, SecurityBlockerClientTester::REALM)),
        );
    }

    protected function createVictimContext(): SecurityCheckAuthContextTransfer
    {
        return $this->tester->createLoginAttemptContext(SecurityBlockerClientTester::REALM, static::TYPE_CUSTOMER, static::IP, static::LOGIN_IDENTIFIER_VICTIM);
    }

    protected function registerFailedLoginAttemptsWithNewAccounts(int $times): void
    {
        for ($i = 0; $i < $times; $i++) {
            $loginIdentifier = sprintf('sprayed-%d@example.com', $i);

            $this->tester->getSecurityBlockerClient()->registerFailedLoginAttempt(
                $this->createVictimContext()->setLoginIdentifier($loginIdentifier)->setAccount($loginIdentifier),
            );
        }
    }

    protected function getStoredValue(string $storageKey): ?string
    {
        return $this->inMemorySecurityBlockerRedisClient->get($this->getRedisConnectionKey(), $storageKey);
    }

    protected function getRedisConnectionKey(): string
    {
        return (new ClientSecurityBlockerConfig())->getRedisConnectionKey();
    }
}
