<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Client\SecurityBlocker\Client;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\SecurityCheckAttemptContextTransfer;
use Spryker\Client\SecurityBlocker\Dependency\Client\SecurityBlockerToRedisClientInterface;
use Spryker\Client\SecurityBlocker\Exception\SecurityBlockerException;
use Spryker\Client\SecurityBlocker\SecurityBlockerClientInterface;
use Spryker\Client\SecurityBlocker\SecurityBlockerConfig;
use Spryker\Client\SecurityBlocker\SecurityBlockerDependencyProvider;
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
 * @group IncrementAttemptCountTest
 * Add your own group annotations below this line
 */
class IncrementAttemptCountTest extends Unit
{
    protected const string TYPE = 'test-type';

    protected const string IDENTIFIER = 'test-identifier';

    protected const string OTHER_IDENTIFIER = 'other-identifier';

    protected const string STORAGE_KEY = 'kv:test-type:test-identifier';

    protected const string IDENTIFIER_WITH_SEPARATOR = 'test-identifier:other identifier';

    protected const string ESCAPED_STORAGE_KEY = 'kv:test-type:test-identifier%3Aother%20identifier';

    protected const int TTL = 900;

    protected const int TTL_NO_EXPIRATION = -1;

    protected const int MIN_ATTEMPT_TTL_SECONDS = 60;

    protected SecurityBlockerClientTester $tester;

    protected InMemorySecurityBlockerRedisClient $redisClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->redisClient = new InMemorySecurityBlockerRedisClient();
        $this->tester->setDependency(SecurityBlockerDependencyProvider::CLIENT_REDIS, $this->redisClient);
    }

    public function testIncrementAttemptCountRequiresIdentifier(): void
    {
        // Arrange
        $securityCheckAttemptContextTransfer = $this->createSecurityCheckAttemptContextTransfer()->setIdentifier(null);

        // Assert
        $this->expectException(NullValueException::class);

        // Act
        $this->getClient()->incrementAttemptCount($securityCheckAttemptContextTransfer);
    }

    public function testIncrementAttemptCountReturnsIncrementedCount(): void
    {
        // Arrange
        $securityCheckAttemptContextTransfer = $this->createSecurityCheckAttemptContextTransfer();

        // Act
        $firstSecurityCheckAttemptResponseTransfer = $this->getClient()->incrementAttemptCount($securityCheckAttemptContextTransfer);
        $secondSecurityCheckAttemptResponseTransfer = $this->getClient()->incrementAttemptCount($securityCheckAttemptContextTransfer);
        $thirdSecurityCheckAttemptResponseTransfer = $this->getClient()->incrementAttemptCount($securityCheckAttemptContextTransfer);

        // Assert
        $this->assertSame(1, $firstSecurityCheckAttemptResponseTransfer->getNumberOfAttempts());
        $this->assertSame(2, $secondSecurityCheckAttemptResponseTransfer->getNumberOfAttempts());
        $this->assertSame(3, $thirdSecurityCheckAttemptResponseTransfer->getNumberOfAttempts());
    }

    public function testIncrementAttemptCountUsesSeparateCounterPerIdentifier(): void
    {
        // Arrange
        $this->getClient()->incrementAttemptCount($this->createSecurityCheckAttemptContextTransfer());

        // Act
        $securityCheckAttemptResponseTransfer = $this->getClient()->incrementAttemptCount(
            $this->createSecurityCheckAttemptContextTransfer()->setIdentifier(static::OTHER_IDENTIFIER),
        );

        // Assert
        $this->assertSame(1, $securityCheckAttemptResponseTransfer->getNumberOfAttempts());
    }

    public function testIncrementAttemptCountEscapesIdentifierInStorageKey(): void
    {
        // Act
        $this->getClient()->incrementAttemptCount(
            $this->createSecurityCheckAttemptContextTransfer()->setIdentifier(static::IDENTIFIER_WITH_SEPARATOR),
        );

        // Assert
        $this->assertSame('1', $this->redisClient->get($this->getRedisConnectionKey(), static::ESCAPED_STORAGE_KEY));
    }

    public function testIncrementAttemptCountSetsTtlOnNewCounter(): void
    {
        // Act
        $this->getClient()->incrementAttemptCount($this->createSecurityCheckAttemptContextTransfer());

        // Assert
        $ttl = $this->redisClient->ttl($this->getRedisConnectionKey(), static::STORAGE_KEY);
        $this->assertGreaterThan(0, $ttl);
        $this->assertLessThanOrEqual(static::TTL, $ttl);
    }

    public function testIncrementAttemptCountDoesNotChangeTtlOfExistingCounter(): void
    {
        // Arrange
        $this->getClient()->incrementAttemptCount($this->createSecurityCheckAttemptContextTransfer());

        // Act
        $this->getClient()->incrementAttemptCount($this->createSecurityCheckAttemptContextTransfer()->setTtl(static::TTL * 10));

        // Assert
        $this->assertLessThanOrEqual(static::TTL, $this->redisClient->ttl($this->getRedisConnectionKey(), static::STORAGE_KEY));
    }

    /**
     * @dataProvider getTtlBelowMinimumDataProvider
     */
    public function testIncrementAttemptCountRaisesTtlBelowMinimumToMinimum(int $ttl): void
    {
        // Act
        $this->getClient()->incrementAttemptCount($this->createSecurityCheckAttemptContextTransfer()->setTtl($ttl));

        // Assert
        $storedTtl = $this->redisClient->ttl($this->getRedisConnectionKey(), static::STORAGE_KEY);
        $this->assertGreaterThan(max($ttl, 1), $storedTtl);
        $this->assertLessThanOrEqual(static::MIN_ATTEMPT_TTL_SECONDS, $storedTtl);
    }

    /**
     * @return array<string, array<int>>
     */
    public function getTtlBelowMinimumDataProvider(): array
    {
        return [
            'positive ttl below minimum' => [1],
            'zero ttl' => [0],
            'negative ttl' => [-1],
        ];
    }

    public function testIncrementAttemptCountThrowsExceptionWhenCounterIsNotIncremented(): void
    {
        // Arrange
        $redisClientMock = $this->createMock(SecurityBlockerToRedisClientInterface::class);
        $redisClientMock->method('incr')->willReturn(0);
        $this->tester->setDependency(SecurityBlockerDependencyProvider::CLIENT_REDIS, $redisClientMock);

        // Assert
        $this->expectException(SecurityBlockerException::class);

        // Act
        $this->getClient()->incrementAttemptCount($this->createSecurityCheckAttemptContextTransfer());
    }

    public function testIncrementAttemptCountThrowsExceptionWhenTtlIsNotSet(): void
    {
        // Arrange
        $redisClientMock = $this->createMock(SecurityBlockerToRedisClientInterface::class);
        $redisClientMock->method('incr')->willReturn(1);
        $redisClientMock->method('ttl')->willReturn(static::TTL_NO_EXPIRATION);
        $redisClientMock->method('expire')->willReturn(false);
        $this->tester->setDependency(SecurityBlockerDependencyProvider::CLIENT_REDIS, $redisClientMock);

        // Assert
        $this->expectException(SecurityBlockerException::class);

        // Act
        $this->getClient()->incrementAttemptCount($this->createSecurityCheckAttemptContextTransfer());
    }

    protected function createSecurityCheckAttemptContextTransfer(): SecurityCheckAttemptContextTransfer
    {
        return (new SecurityCheckAttemptContextTransfer())
            ->setType(static::TYPE)
            ->setIdentifier(static::IDENTIFIER)
            ->setTtl(static::TTL);
    }

    protected function getClient(): SecurityBlockerClientInterface
    {
        return $this->tester->getLocator()->securityBlocker()->client();
    }

    protected function getRedisConnectionKey(): string
    {
        return (new SecurityBlockerConfig())->getRedisConnectionKey();
    }
}
