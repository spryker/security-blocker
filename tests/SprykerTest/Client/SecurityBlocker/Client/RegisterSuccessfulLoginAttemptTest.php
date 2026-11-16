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
 * @group RegisterSuccessfulLoginAttemptTest
 * Add your own group annotations below this line
 */
class RegisterSuccessfulLoginAttemptTest extends Unit
{
    protected const string TYPE_CUSTOMER = 'customer';

    protected const string IP = '203.0.113.1';

    protected const string LOGIN_IDENTIFIER_VICTIM = 'victim@example.com';

    protected const string LOGIN_IDENTIFIER_OTHER_ACCOUNT = 'other@example.com';

    protected const string IP_COUNTER_SHARE_CLAIM_STORAGE_KEY_PATTERN = 'kv:login-ip-share-claim:%s:%s';

    protected SecurityBlockerClientTester $tester;

    protected InMemorySecurityBlockerRedisClient $inMemorySecurityBlockerRedisClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inMemorySecurityBlockerRedisClient = $this->tester->haveInMemorySecurityBlockerRedisClient();
        $this->tester->haveLoginProtectionThresholds();
    }

    public function testRegisterSuccessfulLoginAttemptSubtractsOnlyItsOwnFailuresFromTheSharedIpCounter(): void
    {
        // Arrange
        $this->tester->reserveLoginAttempts($this->createVictimContext(), 3);
        $this->tester->registerFailedLoginAttempts($this->createVictimContext(), 3);
        $this->tester->registerFailedLoginAttempts($this->createOtherAccountContext(), 2);

        // Act
        $this->tester->getSecurityBlockerClient()->registerSuccessfulLoginAttempt($this->createVictimContext());

        // Assert
        $this->assertNull($this->getStoredValue(sprintf(SecurityBlockerClientTester::ACCOUNT_STORAGE_KEY_PATTERN, SecurityBlockerClientTester::REALM, hash('sha256', static::LOGIN_IDENTIFIER_VICTIM))));
        $this->assertSame('2', $this->getStoredValue($this->getSharedIpCounterKey()));
        $this->assertSame('5', $this->getStoredValue(sprintf(SecurityBlockerClientTester::GLOBAL_STORAGE_KEY_PATTERN, SecurityBlockerClientTester::REALM)));
    }

    public function testRegisterSuccessfulLoginAttemptEndsTheChallengeItsOwnFailuresStarted(): void
    {
        // Arrange
        $this->tester->reserveLoginAttempts($this->createVictimContext(), SecurityBlockerClientTester::IP_CHALLENGE_NUMBER_OF_ATTEMPTS);
        $this->tester->registerFailedLoginAttempts($this->createVictimContext(), SecurityBlockerClientTester::IP_CHALLENGE_NUMBER_OF_ATTEMPTS);

        // Act
        $this->tester->getSecurityBlockerClient()->registerSuccessfulLoginAttempt($this->createVictimContext());

        // Assert
        $this->assertNull($this->getStoredValue($this->getSharedIpCounterKey()));
        $this->assertFalse($this->tester->getSecurityBlockerClient()->checkLoginAttempt($this->createVictimContext()->setLoginIdentifier(null))->getIsChallengeRequired());
        $this->assertFalse($this->tester->getSecurityBlockerClient()->checkLoginAttempt($this->createVictimContext())->getIsChallengeRequired());
    }

    public function testRegisterSuccessfulLoginAttemptRemovesTheIpCounterKeyedByItsUsername(): void
    {
        // Arrange
        $securityCheckAuthContextTransfer = $this->createVictimContext()
            ->setIpCounterKeyStrategy(ClientSecurityBlockerConfig::IP_COUNTER_KEY_STRATEGY_IP_AND_USERNAME);
        $this->tester->registerFailedLoginAttempts($securityCheckAuthContextTransfer, SecurityBlockerClientTester::IP_CHALLENGE_NUMBER_OF_ATTEMPTS);

        // Act
        $this->tester->getSecurityBlockerClient()->registerSuccessfulLoginAttempt($securityCheckAuthContextTransfer);

        // Assert
        $this->assertNull($this->getStoredValue(sprintf('kv:%s:%s:%s', static::TYPE_CUSTOMER, static::IP, hash('sha256', static::LOGIN_IDENTIFIER_VICTIM))));
        $this->assertFalse($this->tester->getSecurityBlockerClient()->checkLoginAttempt($securityCheckAuthContextTransfer)->getIsChallengeRequired());
    }

    public function testRegisterSuccessfulLoginAttemptSubtractsItsFailuresOnlyOnce(): void
    {
        // Arrange
        $this->tester->registerFailedLoginAttempts($this->createVictimContext(), 2);
        $this->tester->registerFailedLoginAttempts($this->createOtherAccountContext(), 3);
        $this->tester->getSecurityBlockerClient()->registerSuccessfulLoginAttempt($this->createVictimContext());

        // Act
        $this->tester->getSecurityBlockerClient()->registerSuccessfulLoginAttempt($this->createVictimContext());

        // Assert
        $this->assertSame('3', $this->getStoredValue($this->getSharedIpCounterKey()));
    }

    public function testRegisterSuccessfulLoginAttemptLeavesTheIpCounterToAConcurrentSuccessThatAlreadyClaimedTheSubtraction(): void
    {
        // Arrange
        $this->tester->registerFailedLoginAttempts($this->createVictimContext(), 2);
        $this->tester->registerFailedLoginAttempts($this->createOtherAccountContext(), 3);
        $this->inMemorySecurityBlockerRedisClient->set(
            $this->getRedisConnectionKey(),
            sprintf(static::IP_COUNTER_SHARE_CLAIM_STORAGE_KEY_PATTERN, hash('sha256', static::LOGIN_IDENTIFIER_VICTIM), sprintf('%s:%s:', static::TYPE_CUSTOMER, static::IP)),
            '1',
        );

        // Act
        $this->tester->getSecurityBlockerClient()->registerSuccessfulLoginAttempt($this->createVictimContext());

        // Assert
        $this->assertSame('5', $this->getStoredValue($this->getSharedIpCounterKey()));
    }

    public function testRegisterSuccessfulLoginAttemptDoesNotRecreateAnExpiredIpCounter(): void
    {
        // Arrange
        $this->tester->registerFailedLoginAttempts($this->createVictimContext(), 2);
        $this->inMemorySecurityBlockerRedisClient->del($this->getRedisConnectionKey(), [$this->getSharedIpCounterKey()]);

        // Act
        $this->tester->getSecurityBlockerClient()->registerSuccessfulLoginAttempt($this->createVictimContext());

        // Assert
        $this->assertNull($this->getStoredValue($this->getSharedIpCounterKey()));
    }

    public function testRegisterSuccessfulLoginAttemptWithACaseVariantResetsTheCanonicalCounter(): void
    {
        // Arrange
        $this->tester->reserveLoginAttempts($this->createVictimContext(), 3);

        // Act
        $this->tester->getSecurityBlockerClient()->registerSuccessfulLoginAttempt($this->createVictimContext()->setLoginIdentifier('VICTIM@example.COM'));

        // Assert
        $this->assertNull($this->getStoredValue(sprintf(SecurityBlockerClientTester::ACCOUNT_STORAGE_KEY_PATTERN, SecurityBlockerClientTester::REALM, hash('sha256', static::LOGIN_IDENTIFIER_VICTIM))));
    }

    public function testRegisterSuccessfulLoginAttemptKeepsAnAccountWithIntermittentSuccessesOpen(): void
    {
        // Arrange
        $this->tester->reserveLoginAttempts($this->createVictimContext(), SecurityBlockerClientTester::ACCOUNT_NUMBER_OF_ATTEMPTS - 1);
        $this->tester->getSecurityBlockerClient()->registerSuccessfulLoginAttempt($this->createVictimContext());

        // Act
        $securityCheckAuthResponseTransfer = $this->tester->reserveLoginAttempts($this->createVictimContext(), SecurityBlockerClientTester::ACCOUNT_NUMBER_OF_ATTEMPTS - 1);

        // Assert
        $this->assertFalse($securityCheckAuthResponseTransfer->getIsBlocked());
    }

    public function testRegisterSuccessfulLoginAttemptDoesNothingForAnEmptyIdentifier(): void
    {
        // Arrange
        $this->tester->registerFailedLoginAttempts($this->createVictimContext(), 1);
        $storedKeys = $this->inMemorySecurityBlockerRedisClient->getStoredKeys($this->getRedisConnectionKey());

        // Act
        $securityCheckAuthResponseTransfer = $this->tester->getSecurityBlockerClient()->registerSuccessfulLoginAttempt($this->createVictimContext()->setLoginIdentifier(''));

        // Assert
        $this->assertFalse($securityCheckAuthResponseTransfer->getIsBlocked());
        $this->assertSame($storedKeys, $this->inMemorySecurityBlockerRedisClient->getStoredKeys($this->getRedisConnectionKey()));
    }

    protected function createVictimContext(): SecurityCheckAuthContextTransfer
    {
        return $this->tester->createLoginAttemptContext(SecurityBlockerClientTester::REALM, static::TYPE_CUSTOMER, static::IP, static::LOGIN_IDENTIFIER_VICTIM);
    }

    protected function createOtherAccountContext(): SecurityCheckAuthContextTransfer
    {
        return $this->tester->createLoginAttemptContext(SecurityBlockerClientTester::REALM, static::TYPE_CUSTOMER, static::IP, static::LOGIN_IDENTIFIER_OTHER_ACCOUNT);
    }

    protected function getSharedIpCounterKey(): string
    {
        return sprintf('kv:%s:%s:', static::TYPE_CUSTOMER, static::IP);
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
