<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Client\SecurityBlocker\Client;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\SecurityBlockerLoginRealmConfigurationTransfer;
use Generated\Shared\Transfer\SecurityCheckAuthContextTransfer;
use Spryker\Client\SecurityBlockerExtension\Dependency\Plugin\SecurityBlockerLoginChallengeStrategyPluginInterface;
use Spryker\Shared\Kernel\Transfer\Exception\NullValueException;
use Spryker\Shared\SecurityBlocker\SecurityBlockerConstants;
use SprykerTest\Client\SecurityBlocker\SecurityBlockerClientTester;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group Client
 * @group SecurityBlocker
 * @group Client
 * @group CheckLoginAttemptTest
 * Add your own group annotations below this line
 */
class CheckLoginAttemptTest extends Unit
{
    protected const string TYPE_CUSTOMER = 'customer';

    protected const string IP_ATTACKER_FIRST = '203.0.113.1';

    protected const string IP_ATTACKER_SECOND = '198.51.100.7';

    protected const string LOGIN_IDENTIFIER_VICTIM = 'victim@example.com';

    protected const string UNCONFIGURED_REALM = 'unconfigured-realm';

    /**
     * @uses \Spryker\Client\SecurityBlocker\SecurityBlockerConfig::DEFAULT_ACCOUNT_BLOCKING_NUMBER_OF_ATTEMPTS
     */
    protected const int DEFAULT_ACCOUNT_NUMBER_OF_ATTEMPTS = 9;

    protected SecurityBlockerClientTester $tester;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tester->haveInMemorySecurityBlockerRedisClient();
        $this->tester->haveLoginProtectionThresholds();
    }

    public function testCheckLoginAttemptRequiresRealm(): void
    {
        // Arrange
        $securityCheckAuthContextTransfer = (new SecurityCheckAuthContextTransfer())
            ->setType(static::TYPE_CUSTOMER)
            ->setIp(static::IP_ATTACKER_FIRST);

        // Assert
        $this->expectException(NullValueException::class);

        // Act
        $this->tester->getSecurityBlockerClient()->checkLoginAttempt($securityCheckAuthContextTransfer);
    }

    /**
     * A realm whose login realm configuration expander plugin is not wired keeps its per-account lock.
     */
    public function testCheckLoginAttemptLocksAnUnconfiguredRealmWithTheModuleDefaults(): void
    {
        // Arrange
        $securityCheckAuthContextTransfer = $this->tester->createLoginAttemptContext(static::UNCONFIGURED_REALM, static::TYPE_CUSTOMER, static::IP_ATTACKER_FIRST, static::LOGIN_IDENTIFIER_VICTIM);
        $this->tester->reserveLoginAttempts($securityCheckAuthContextTransfer, static::DEFAULT_ACCOUNT_NUMBER_OF_ATTEMPTS);

        // Act
        $securityCheckAuthResponseTransfer = $this->tester->getSecurityBlockerClient()->checkLoginAttempt($securityCheckAuthContextTransfer);

        // Assert
        $this->assertTrue($securityCheckAuthResponseTransfer->getIsBlocked());
    }

    public function testCheckLoginAttemptIsOpenWithoutPreviousAttempts(): void
    {
        // Act
        $securityCheckAuthResponseTransfer = $this->tester->getSecurityBlockerClient()->checkLoginAttempt($this->createVictimContext(static::IP_ATTACKER_FIRST));

        // Assert
        $this->assertFalse($securityCheckAuthResponseTransfer->getIsBlocked());
        $this->assertFalse($securityCheckAuthResponseTransfer->getIsChallengeRequired());
    }

    /**
     * The pentest scenario: the limit is exhausted from one IP, the next attempt comes from another.
     */
    public function testCheckLoginAttemptKeepsTheAccountBlockedWhenTheIpRotates(): void
    {
        // Arrange
        $this->tester->reserveLoginAttempts($this->createVictimContext(static::IP_ATTACKER_FIRST), SecurityBlockerClientTester::ACCOUNT_NUMBER_OF_ATTEMPTS);

        // Act
        $securityCheckAuthResponseTransfer = $this->tester->getSecurityBlockerClient()->checkLoginAttempt($this->createVictimContext(static::IP_ATTACKER_SECOND));

        // Assert
        $this->assertTrue($securityCheckAuthResponseTransfer->getIsBlocked());
        $this->assertSame(SecurityBlockerClientTester::ACCOUNT_BLOCK_FOR, $securityCheckAuthResponseTransfer->getBlockedFor());
    }

    public function testCheckLoginAttemptIsOpenOneAttemptBeforeTheAccountLimit(): void
    {
        // Arrange
        $this->tester->reserveLoginAttempts($this->createVictimContext(static::IP_ATTACKER_FIRST), SecurityBlockerClientTester::ACCOUNT_NUMBER_OF_ATTEMPTS - 1);

        // Act
        $securityCheckAuthResponseTransfer = $this->tester->getSecurityBlockerClient()->checkLoginAttempt($this->createVictimContext(static::IP_ATTACKER_SECOND));

        // Assert
        $this->assertFalse($securityCheckAuthResponseTransfer->getIsBlocked());
    }

    public function testCheckLoginAttemptCountsCaseAndAccentVariantsAsTheSameAccount(): void
    {
        // Arrange
        $variantContextTransfer = $this->tester->createLoginAttemptContext(
            SecurityBlockerClientTester::REALM,
            static::TYPE_CUSTOMER,
            static::IP_ATTACKER_FIRST,
            " V\u{00CD}CTIM@Example.com ",
        );
        $this->tester->reserveLoginAttempts($variantContextTransfer, SecurityBlockerClientTester::ACCOUNT_NUMBER_OF_ATTEMPTS);

        // Act
        $securityCheckAuthResponseTransfer = $this->tester->getSecurityBlockerClient()->checkLoginAttempt($this->createVictimContext(static::IP_ATTACKER_SECOND));

        // Assert
        $this->assertTrue($securityCheckAuthResponseTransfer->getIsBlocked());
    }

    /**
     * The blocker never looks an account up, so an identifier nobody registered must behave exactly like a real one.
     */
    public function testCheckLoginAttemptAnswersIdenticallyForAnyIdentifierWithTheSameCount(): void
    {
        // Arrange
        $this->tester->reserveLoginAttempts($this->createVictimContext(static::IP_ATTACKER_FIRST), SecurityBlockerClientTester::ACCOUNT_CHALLENGE_NUMBER_OF_ATTEMPTS);
        $unknownContextTransfer = $this->tester->createLoginAttemptContext(SecurityBlockerClientTester::REALM, static::TYPE_CUSTOMER, static::IP_ATTACKER_SECOND, 'nobody-registered-this@example.com');
        $this->tester->reserveLoginAttempts($unknownContextTransfer, SecurityBlockerClientTester::ACCOUNT_CHALLENGE_NUMBER_OF_ATTEMPTS);

        // Act
        $victimResponseTransfer = $this->tester->getSecurityBlockerClient()->checkLoginAttempt($this->createVictimContext('192.0.2.10'));
        $unknownResponseTransfer = $this->tester->getSecurityBlockerClient()->checkLoginAttempt($unknownContextTransfer->setIp('192.0.2.10'));

        // Assert
        $this->assertTrue($victimResponseTransfer->getIsChallengeRequired());
        $this->assertSame($victimResponseTransfer->getIsBlocked(), $unknownResponseTransfer->getIsBlocked());
        $this->assertSame($victimResponseTransfer->getIsChallengeRequired(), $unknownResponseTransfer->getIsChallengeRequired());
        $this->assertSame($victimResponseTransfer->getNumberOfAttempts(), $unknownResponseTransfer->getNumberOfAttempts());
    }

    public function testCheckLoginAttemptIsBlockedWhenTheIpReachedTheTypeLimit(): void
    {
        // Arrange
        for ($i = 0; $i < SecurityBlockerClientTester::IP_NUMBER_OF_ATTEMPTS; $i++) {
            $this->tester->getSecurityBlockerClient()->registerFailedLoginAttempt(
                $this->tester->createLoginAttemptContext(SecurityBlockerClientTester::REALM, static::TYPE_CUSTOMER, static::IP_ATTACKER_FIRST, sprintf('sprayed-%d@example.com', $i)),
            );
        }

        // Act
        $securityCheckAuthResponseTransfer = $this->tester->getSecurityBlockerClient()->checkLoginAttempt($this->createVictimContext(static::IP_ATTACKER_FIRST));

        // Assert
        $this->assertTrue($securityCheckAuthResponseTransfer->getIsBlocked());
        $this->assertSame(SecurityBlockerClientTester::IP_BLOCK_FOR, $securityCheckAuthResponseTransfer->getBlockedFor());
    }

    public function testCheckLoginAttemptRequiresChallengeOnceTheAccountChallengeThresholdIsReached(): void
    {
        // Arrange
        $this->tester->reserveLoginAttempts($this->createVictimContext(static::IP_ATTACKER_FIRST), SecurityBlockerClientTester::ACCOUNT_CHALLENGE_NUMBER_OF_ATTEMPTS);

        // Act
        $securityCheckAuthResponseTransfer = $this->tester->getSecurityBlockerClient()->checkLoginAttempt($this->createVictimContext(static::IP_ATTACKER_SECOND));

        // Assert
        $this->assertFalse($securityCheckAuthResponseTransfer->getIsBlocked());
        $this->assertTrue($securityCheckAuthResponseTransfer->getIsChallengeRequired());
    }

    public function testCheckLoginAttemptRequiresChallengeOnceTheIpChallengeThresholdIsReached(): void
    {
        // Arrange
        for ($i = 0; $i < SecurityBlockerClientTester::IP_CHALLENGE_NUMBER_OF_ATTEMPTS; $i++) {
            $this->tester->getSecurityBlockerClient()->registerFailedLoginAttempt(
                $this->tester->createLoginAttemptContext(SecurityBlockerClientTester::REALM, static::TYPE_CUSTOMER, static::IP_ATTACKER_FIRST, sprintf('sprayed-%d@example.com', $i)),
            );
        }

        // Act
        $securityCheckAuthResponseTransfer = $this->tester->getSecurityBlockerClient()->checkLoginAttempt($this->createVictimContext(static::IP_ATTACKER_FIRST));

        // Assert
        $this->assertFalse($securityCheckAuthResponseTransfer->getIsBlocked());
        $this->assertTrue($securityCheckAuthResponseTransfer->getIsChallengeRequired());
    }

    /**
     * A challenge threshold at or above the lock would lock the account before any challenge is asked.
     */
    public function testCheckLoginAttemptRequiresChallengeOneAttemptBeforeTheAccountLockWhenTheAccountChallengeThresholdIsNotBelowIt(): void
    {
        // Arrange
        $this->tester->haveLoginRealmConfiguration(SecurityBlockerClientTester::REALM, [
            SecurityBlockerLoginRealmConfigurationTransfer::ACCOUNT_CHALLENGE_NUMBER_OF_ATTEMPTS => SecurityBlockerClientTester::ACCOUNT_NUMBER_OF_ATTEMPTS + 3,
        ]);
        $this->tester->reserveLoginAttempts($this->createVictimContext(static::IP_ATTACKER_FIRST), SecurityBlockerClientTester::ACCOUNT_NUMBER_OF_ATTEMPTS - 1);

        // Act
        $securityCheckAuthResponseTransfer = $this->tester->getSecurityBlockerClient()->checkLoginAttempt($this->createVictimContext(static::IP_ATTACKER_SECOND));

        // Assert
        $this->assertFalse($securityCheckAuthResponseTransfer->getIsBlocked());
        $this->assertTrue($securityCheckAuthResponseTransfer->getIsChallengeRequired());
    }

    /**
     * The per-IP challenge and the IP lock of the login type read the same counter.
     */
    public function testCheckLoginAttemptRequiresChallengeOneAttemptBeforeTheIpLockWhenTheIpChallengeThresholdIsNotBelowIt(): void
    {
        // Arrange
        $this->tester->haveLoginRealmConfiguration(SecurityBlockerClientTester::REALM, [
            SecurityBlockerLoginRealmConfigurationTransfer::IP_CHALLENGE_NUMBER_OF_ATTEMPTS => SecurityBlockerClientTester::IP_NUMBER_OF_ATTEMPTS + 5,
        ]);
        $this->registerSprayedFailedLoginAttempts(static::IP_ATTACKER_FIRST, SecurityBlockerClientTester::IP_NUMBER_OF_ATTEMPTS - 1);

        // Act
        $securityCheckAuthResponseTransfer = $this->tester->getSecurityBlockerClient()->checkLoginAttempt($this->createVictimContext(static::IP_ATTACKER_FIRST));

        // Assert
        $this->assertFalse($securityCheckAuthResponseTransfer->getIsBlocked());
        $this->assertTrue($securityCheckAuthResponseTransfer->getIsChallengeRequired());
    }

    /**
     * An account lock after the first failed login leaves no attempt for a challenge, so the first login must not ask for one.
     */
    public function testCheckLoginAttemptDoesNotRequireChallengeOnTheFirstAttemptWhenTheAccountLocksAfterOneAttempt(): void
    {
        // Arrange
        $this->tester->haveLoginRealmConfiguration(SecurityBlockerClientTester::REALM, [
            SecurityBlockerLoginRealmConfigurationTransfer::ACCOUNT_NUMBER_OF_ATTEMPTS => 1,
            SecurityBlockerLoginRealmConfigurationTransfer::ACCOUNT_CHALLENGE_NUMBER_OF_ATTEMPTS => 1,
        ]);

        // Act
        $securityCheckAuthResponseTransfer = $this->tester->getSecurityBlockerClient()->checkLoginAttempt($this->createVictimContext(static::IP_ATTACKER_FIRST));

        // Assert
        $this->assertFalse($securityCheckAuthResponseTransfer->getIsBlocked());
        $this->assertFalse($securityCheckAuthResponseTransfer->getIsChallengeRequired());
    }

    public function testCheckLoginAttemptKeepsTheIpChallengeThresholdWhenTheIpLockIsOff(): void
    {
        // Arrange
        $this->tester->setConfig(SecurityBlockerConstants::SECURITY_BLOCKER_BLOCKING_NUMBER_OF_ATTEMPTS, 0);
        $this->registerSprayedFailedLoginAttempts(static::IP_ATTACKER_FIRST, SecurityBlockerClientTester::IP_CHALLENGE_NUMBER_OF_ATTEMPTS);

        // Act
        $securityCheckAuthResponseTransfer = $this->tester->getSecurityBlockerClient()->checkLoginAttempt($this->createVictimContext(static::IP_ATTACKER_FIRST));

        // Assert
        $this->assertFalse($securityCheckAuthResponseTransfer->getIsBlocked());
        $this->assertTrue($securityCheckAuthResponseTransfer->getIsChallengeRequired());
    }

    /**
     * Password spraying rotates both the IP and the account, so neither per-key counter ever moves.
     */
    public function testCheckLoginAttemptRequiresChallengeForAFreshIpAndAccountOnceTheRealmWideThresholdIsReached(): void
    {
        // Arrange
        $this->tester->haveLoginRealmConfiguration(SecurityBlockerClientTester::REALM, [
            SecurityBlockerLoginRealmConfigurationTransfer::GLOBAL_CHALLENGE_NUMBER_OF_ATTEMPTS => 3,
        ]);

        for ($i = 0; $i < 3; $i++) {
            $this->tester->getSecurityBlockerClient()->registerFailedLoginAttempt(
                $this->tester->createLoginAttemptContext(SecurityBlockerClientTester::REALM, static::TYPE_CUSTOMER, sprintf('192.0.2.%d', $i), sprintf('sprayed-%d@example.com', $i)),
            );
        }

        // Act
        $securityCheckAuthResponseTransfer = $this->tester->getSecurityBlockerClient()->checkLoginAttempt($this->createVictimContext('192.0.2.200'));

        // Assert
        $this->assertFalse($securityCheckAuthResponseTransfer->getIsBlocked());
        $this->assertTrue($securityCheckAuthResponseTransfer->getIsChallengeRequired());
    }

    public function testCheckLoginAttemptNeverRequiresChallengeWhenAllChallengeThresholdsAreZero(): void
    {
        // Arrange
        $this->tester->haveLoginRealmConfiguration(SecurityBlockerClientTester::REALM, [
            SecurityBlockerLoginRealmConfigurationTransfer::ACCOUNT_CHALLENGE_NUMBER_OF_ATTEMPTS => 0,
            SecurityBlockerLoginRealmConfigurationTransfer::IP_CHALLENGE_NUMBER_OF_ATTEMPTS => 0,
            SecurityBlockerLoginRealmConfigurationTransfer::GLOBAL_CHALLENGE_NUMBER_OF_ATTEMPTS => 0,
        ]);
        $this->tester->reserveLoginAttempts($this->createVictimContext(static::IP_ATTACKER_FIRST), SecurityBlockerClientTester::ACCOUNT_NUMBER_OF_ATTEMPTS - 1);
        $this->tester->registerFailedLoginAttempts($this->createVictimContext(static::IP_ATTACKER_FIRST), SecurityBlockerClientTester::IP_NUMBER_OF_ATTEMPTS - 1);

        // Act
        $securityCheckAuthResponseTransfer = $this->tester->getSecurityBlockerClient()->checkLoginAttempt($this->createVictimContext(static::IP_ATTACKER_FIRST));

        // Assert
        $this->assertFalse($securityCheckAuthResponseTransfer->getIsBlocked());
        $this->assertFalse($securityCheckAuthResponseTransfer->getIsChallengeRequired());
    }

    public function testCheckLoginAttemptDoesNotAskForAChallengeWhenAlreadyBlocked(): void
    {
        // Arrange
        $this->tester->reserveLoginAttempts($this->createVictimContext(static::IP_ATTACKER_FIRST), SecurityBlockerClientTester::ACCOUNT_NUMBER_OF_ATTEMPTS);

        // Act
        $securityCheckAuthResponseTransfer = $this->tester->getSecurityBlockerClient()->checkLoginAttempt($this->createVictimContext(static::IP_ATTACKER_FIRST));

        // Assert
        $this->assertTrue($securityCheckAuthResponseTransfer->getIsBlocked());
        $this->assertFalse($securityCheckAuthResponseTransfer->getIsChallengeRequired());
    }

    public function testCheckLoginAttemptSkipsTheAccountDimensionForAnEmptyIdentifier(): void
    {
        // Arrange
        $emptyIdentifierContextTransfer = $this->tester->createLoginAttemptContext(SecurityBlockerClientTester::REALM, static::TYPE_CUSTOMER, static::IP_ATTACKER_FIRST, '   ');

        // Act
        $securityCheckAuthResponseTransfer = $this->tester->getSecurityBlockerClient()->checkLoginAttempt($emptyIdentifierContextTransfer);

        // Assert
        $this->assertFalse($securityCheckAuthResponseTransfer->getIsBlocked());
        $this->assertFalse($securityCheckAuthResponseTransfer->getIsChallengeRequired());
    }

    public function testCheckLoginAttemptReadsTheAccountAndRealmWideCountersInOneRoundTrip(): void
    {
        // Arrange
        $inMemorySecurityBlockerRedisClient = $this->tester->haveInMemorySecurityBlockerRedisClient();

        // Act
        $this->tester->getSecurityBlockerClient()->checkLoginAttempt($this->createVictimContext(static::IP_ATTACKER_FIRST));

        // Assert
        $this->assertSame(['get', 'mget'], $inMemorySecurityBlockerRedisClient->getExecutedCommands());
    }

    public function testCheckLoginAttemptRequiresChallengeBelowEveryThresholdWhenAStrategyPluginRequiresOne(): void
    {
        // Arrange
        $securityCheckAuthContextTransfer = $this->createVictimContext(static::IP_ATTACKER_FIRST);
        $securityBlockerLoginChallengeStrategyPluginMock = $this->createMock(SecurityBlockerLoginChallengeStrategyPluginInterface::class);
        $securityBlockerLoginChallengeStrategyPluginMock->expects($this->once())
            ->method('isChallengeRequired')
            ->with($securityCheckAuthContextTransfer)
            ->willReturn(true);
        $this->tester->haveLoginChallengeStrategyPlugins([$securityBlockerLoginChallengeStrategyPluginMock]);

        // Act
        $securityCheckAuthResponseTransfer = $this->tester->getSecurityBlockerClient()->checkLoginAttempt($securityCheckAuthContextTransfer);

        // Assert
        $this->assertFalse($securityCheckAuthResponseTransfer->getIsBlocked());
        $this->assertTrue($securityCheckAuthResponseTransfer->getIsChallengeRequired());
    }

    /**
     * Strategy plugins can only add a challenge: a project plugin must never weaken the counter-based protection.
     */
    public function testCheckLoginAttemptKeepsTheCounterChallengeWhenNoStrategyPluginRequiresOne(): void
    {
        // Arrange
        $this->tester->reserveLoginAttempts($this->createVictimContext(static::IP_ATTACKER_FIRST), SecurityBlockerClientTester::ACCOUNT_CHALLENGE_NUMBER_OF_ATTEMPTS);
        $this->tester->haveLoginChallengeStrategyPlugins([$this->createStrategyPluginReturning(false)]);

        // Act
        $securityCheckAuthResponseTransfer = $this->tester->getSecurityBlockerClient()->checkLoginAttempt($this->createVictimContext(static::IP_ATTACKER_SECOND));

        // Assert
        $this->assertTrue($securityCheckAuthResponseTransfer->getIsChallengeRequired());
    }

    public function testCheckLoginAttemptDoesNotAskForAChallengeWhenBlockedAlthoughAStrategyPluginRequiresOne(): void
    {
        // Arrange
        $this->tester->reserveLoginAttempts($this->createVictimContext(static::IP_ATTACKER_FIRST), SecurityBlockerClientTester::ACCOUNT_NUMBER_OF_ATTEMPTS);
        $this->tester->haveLoginChallengeStrategyPlugins([$this->createStrategyPluginReturning(true)]);

        // Act
        $securityCheckAuthResponseTransfer = $this->tester->getSecurityBlockerClient()->checkLoginAttempt($this->createVictimContext(static::IP_ATTACKER_FIRST));

        // Assert
        $this->assertTrue($securityCheckAuthResponseTransfer->getIsBlocked());
        $this->assertFalse($securityCheckAuthResponseTransfer->getIsChallengeRequired());
    }

    protected function createStrategyPluginReturning(bool $isChallengeRequired): SecurityBlockerLoginChallengeStrategyPluginInterface
    {
        $securityBlockerLoginChallengeStrategyPluginMock = $this->createMock(SecurityBlockerLoginChallengeStrategyPluginInterface::class);
        $securityBlockerLoginChallengeStrategyPluginMock->method('isChallengeRequired')->willReturn($isChallengeRequired);

        return $securityBlockerLoginChallengeStrategyPluginMock;
    }

    protected function registerSprayedFailedLoginAttempts(string $ip, int $times): void
    {
        for ($i = 0; $i < $times; $i++) {
            $this->tester->getSecurityBlockerClient()->registerFailedLoginAttempt(
                $this->tester->createLoginAttemptContext(SecurityBlockerClientTester::REALM, static::TYPE_CUSTOMER, $ip, sprintf('sprayed-%d@example.com', $i)),
            );
        }
    }

    protected function createVictimContext(string $ip): SecurityCheckAuthContextTransfer
    {
        return $this->tester->createLoginAttemptContext(SecurityBlockerClientTester::REALM, static::TYPE_CUSTOMER, $ip, static::LOGIN_IDENTIFIER_VICTIM);
    }
}
