<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Client\SecurityBlocker\Client;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\SecurityCheckAuthContextTransfer;
use InvalidArgumentException;
use Spryker\Client\SecurityBlocker\SecurityBlockerConfig;
use SprykerTest\Client\SecurityBlocker\Helper\InMemorySecurityBlockerRedisClient;
use SprykerTest\Client\SecurityBlocker\SecurityBlockerClientTester;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group Client
 * @group SecurityBlocker
 * @group Client
 * @group IpCounterKeyStrategyTest
 * Add your own group annotations below this line
 */
class IpCounterKeyStrategyTest extends Unit
{
    protected const string TYPE_USER = 'user';

    protected const string IP = '203.0.113.7';

    protected const string ACCOUNT = 'account-of-the-caller';

    protected const string LOGIN_IDENTIFIER = ' Admin@Example.COM ';

    protected const string LOGIN_IDENTIFIER_CASE_VARIANT = 'admin@example.com';

    protected const string NORMALIZED_LOGIN_IDENTIFIER = 'admin@example.com';

    protected const string BLANK_LOGIN_IDENTIFIER = '   ';

    protected const string UNKNOWN_IP_COUNTER_KEY_STRATEGY = 'ip-and-unknown';

    protected const string IP_COUNTER_STORAGE_KEY_PATTERN = 'kv:%s:%s:%s';

    protected const string HASH_ALGORITHM = 'sha256';

    protected SecurityBlockerClientTester $tester;

    protected InMemorySecurityBlockerRedisClient $inMemorySecurityBlockerRedisClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inMemorySecurityBlockerRedisClient = $this->tester->haveInMemorySecurityBlockerRedisClient();
        $this->tester->haveLoginProtectionThresholds();
    }

    public function testKeysTheIpCounterWithTheAccountWhenNoStrategyIsGiven(): void
    {
        // Arrange
        $securityCheckAuthContextTransfer = $this->createContext(null, static::LOGIN_IDENTIFIER);

        // Act
        $this->tester->getSecurityBlockerClient()->registerFailedLoginAttempt($securityCheckAuthContextTransfer);

        // Assert
        $this->assertSame('1', $this->findIpCounterValue(static::ACCOUNT));
    }

    public function testKeysTheIpCounterWithTheAccountWithTheIpStrategy(): void
    {
        // Arrange
        $securityCheckAuthContextTransfer = $this->createContext(SecurityBlockerConfig::IP_COUNTER_KEY_STRATEGY_IP, static::LOGIN_IDENTIFIER);

        // Act
        $this->tester->getSecurityBlockerClient()->registerFailedLoginAttempt($securityCheckAuthContextTransfer);

        // Assert
        $this->assertSame('1', $this->findIpCounterValue(static::ACCOUNT));
    }

    public function testCountsCaseVariantsOfTheLoginIdentifierOnOneHashedIpCounterWithTheIpAndUsernameStrategy(): void
    {
        // Arrange
        $securityBlockerClient = $this->tester->getSecurityBlockerClient();

        // Act
        $securityBlockerClient->registerFailedLoginAttempt($this->createContext(SecurityBlockerConfig::IP_COUNTER_KEY_STRATEGY_IP_AND_USERNAME, static::LOGIN_IDENTIFIER));
        $securityBlockerClient->registerFailedLoginAttempt($this->createContext(SecurityBlockerConfig::IP_COUNTER_KEY_STRATEGY_IP_AND_USERNAME, static::LOGIN_IDENTIFIER_CASE_VARIANT));

        // Assert
        $this->assertSame('2', $this->findIpCounterValue(hash(static::HASH_ALGORITHM, static::NORMALIZED_LOGIN_IDENTIFIER)));
        $this->assertNull($this->findIpCounterValue(static::ACCOUNT));
    }

    public function testKeysTheIpCounterWithTheAccountWhenTheLoginIdentifierIsBlankWithTheIpAndUsernameStrategy(): void
    {
        // Arrange
        $securityCheckAuthContextTransfer = $this->createContext(SecurityBlockerConfig::IP_COUNTER_KEY_STRATEGY_IP_AND_USERNAME, static::BLANK_LOGIN_IDENTIFIER);

        // Act
        $this->tester->getSecurityBlockerClient()->registerFailedLoginAttempt($securityCheckAuthContextTransfer);

        // Assert
        $this->assertSame('1', $this->findIpCounterValue(static::ACCOUNT));
    }

    public function testRejectsAnUnknownIpCounterKeyStrategy(): void
    {
        // Arrange
        $securityCheckAuthContextTransfer = $this->createContext(static::UNKNOWN_IP_COUNTER_KEY_STRATEGY, static::LOGIN_IDENTIFIER);

        // Assert
        $this->expectException(InvalidArgumentException::class);

        // Act
        $this->tester->getSecurityBlockerClient()->registerFailedLoginAttempt($securityCheckAuthContextTransfer);
    }

    protected function createContext(?string $ipCounterKeyStrategy, string $loginIdentifier): SecurityCheckAuthContextTransfer
    {
        return $this->tester->createLoginAttemptContext(SecurityBlockerClientTester::REALM, static::TYPE_USER, static::IP, $loginIdentifier)
            ->setAccount(static::ACCOUNT)
            ->setIpCounterKeyStrategy($ipCounterKeyStrategy);
    }

    protected function findIpCounterValue(string $accountKeyPart): ?string
    {
        return $this->inMemorySecurityBlockerRedisClient->get(
            (new SecurityBlockerConfig())->getRedisConnectionKey(),
            sprintf(static::IP_COUNTER_STORAGE_KEY_PATTERN, static::TYPE_USER, static::IP, $accountKeyPart),
        );
    }
}
