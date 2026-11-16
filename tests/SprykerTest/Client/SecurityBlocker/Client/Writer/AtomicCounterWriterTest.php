<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Client\SecurityBlocker\Client\Writer;

use Codeception\Test\Unit;
use Spryker\Client\SecurityBlocker\Exception\SecurityBlockerException;
use Spryker\Client\SecurityBlocker\Redis\SecurityBlockerRedisWrapper;
use Spryker\Client\SecurityBlocker\Redis\SecurityBlockerRedisWrapperInterface;
use Spryker\Client\SecurityBlocker\SecurityBlockerConfig;
use Spryker\Client\SecurityBlocker\Storage\Writer\AtomicCounterWriter;
use SprykerTest\Client\SecurityBlocker\Helper\InMemorySecurityBlockerRedisClient;
use SprykerTest\Client\SecurityBlocker\SecurityBlockerClientTester;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group Client
 * @group SecurityBlocker
 * @group Client
 * @group Writer
 * @group AtomicCounterWriterTest
 * Add your own group annotations below this line
 */
class AtomicCounterWriterTest extends Unit
{
    protected const string KEY = 'login-account:test-realm:hash';

    protected const string STORAGE_KEY = 'kv:login-account:test-realm:hash';

    protected const int WINDOW_TTL = 900;

    protected const int LOCK_THRESHOLD = 3;

    protected const int LOCK_TTL = 360;

    protected InMemorySecurityBlockerRedisClient $inMemorySecurityBlockerRedisClient;

    protected AtomicCounterWriter $atomicCounterWriter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inMemorySecurityBlockerRedisClient = new InMemorySecurityBlockerRedisClient();
        $this->atomicCounterWriter = new AtomicCounterWriter(
            new SecurityBlockerRedisWrapper($this->inMemorySecurityBlockerRedisClient, new SecurityBlockerConfig()),
        );
    }

    public function testIncrementStartsTheWindowOnTheFirstAttempt(): void
    {
        // Arrange
        $this->inMemorySecurityBlockerRedisClient->setCurrentTime(SecurityBlockerClientTester::CURRENT_TIME);

        // Act
        $count = $this->atomicCounterWriter->increment(static::KEY, static::WINDOW_TTL, static::LOCK_THRESHOLD, static::LOCK_TTL);

        // Assert
        $this->assertSame(1, $count);
        $this->assertSame(SecurityBlockerClientTester::CURRENT_TIME + static::WINDOW_TTL, $this->getExpiresAt());
    }

    public function testIncrementReturnsTheExactCountAndKeepsTheWindow(): void
    {
        // Arrange
        $this->atomicCounterWriter->increment(static::KEY, static::WINDOW_TTL, static::LOCK_THRESHOLD, static::LOCK_TTL);
        $windowExpiresAt = $this->getExpiresAt();

        // Act
        $count = $this->atomicCounterWriter->increment(static::KEY, static::WINDOW_TTL, static::LOCK_THRESHOLD, static::LOCK_TTL);

        // Assert
        $this->assertSame(2, $count);
        $this->assertSame($windowExpiresAt, $this->getExpiresAt());
    }

    public function testIncrementStartsTheLockExactlyWhenTheThresholdIsReached(): void
    {
        // Arrange
        $this->inMemorySecurityBlockerRedisClient->setCurrentTime(SecurityBlockerClientTester::CURRENT_TIME);
        $this->incrementTimes(static::LOCK_THRESHOLD - 1);

        // Act
        $count = $this->atomicCounterWriter->increment(static::KEY, static::WINDOW_TTL, static::LOCK_THRESHOLD, static::LOCK_TTL);

        // Assert
        $this->assertSame(static::LOCK_THRESHOLD, $count);
        $this->assertSame(SecurityBlockerClientTester::CURRENT_TIME + static::LOCK_TTL, $this->getExpiresAt());
    }

    public function testIncrementNeverExtendsAStartedLock(): void
    {
        // Arrange
        $this->inMemorySecurityBlockerRedisClient->setCurrentTime(SecurityBlockerClientTester::CURRENT_TIME);
        $this->incrementTimes(static::LOCK_THRESHOLD);
        $this->inMemorySecurityBlockerRedisClient->setCurrentTime(SecurityBlockerClientTester::CURRENT_TIME + SecurityBlockerClientTester::LATER_ATTEMPTS_DELAY);

        // Act
        $count = $this->incrementTimes(10);

        // Assert
        $this->assertSame(static::LOCK_THRESHOLD + 10, $count);
        $this->assertSame(SecurityBlockerClientTester::CURRENT_TIME + static::LOCK_TTL, $this->getExpiresAt());
    }

    public function testIncrementWithoutLockThresholdOnlyCounts(): void
    {
        // Arrange
        $this->atomicCounterWriter->increment(static::KEY, static::WINDOW_TTL, 0, 0);
        $windowExpiresAt = $this->getExpiresAt();

        // Act
        $count = $this->atomicCounterWriter->increment(static::KEY, static::WINDOW_TTL, 0, 0);

        // Assert
        $this->assertSame(2, $count);
        $this->assertSame($windowExpiresAt, $this->getExpiresAt());
    }

    public function testIncrementReArmsTheWindowWhenTheKeyExpiredBetweenTheTwoCommands(): void
    {
        // Arrange
        $redisWrapperMock = $this->createMock(SecurityBlockerRedisWrapperInterface::class);
        $redisWrapperMock->method('setIfNotExists')->willReturn(false);
        $redisWrapperMock->method('incr')->willReturn(1);

        // Assert
        $redisWrapperMock->expects($this->never())->method('setex');
        $redisWrapperMock->expects($this->once())
            ->method('expire')
            ->with(static::KEY, static::WINDOW_TTL)
            ->willReturn(true);

        // Act
        (new AtomicCounterWriter($redisWrapperMock))->increment(static::KEY, static::WINDOW_TTL, static::LOCK_THRESHOLD, static::LOCK_TTL);
    }

    public function testIncrementStartsTheLockWithoutWritingTheCountBack(): void
    {
        // Arrange
        $redisWrapperMock = $this->createMock(SecurityBlockerRedisWrapperInterface::class);
        $redisWrapperMock->method('setIfNotExists')->willReturn(false);
        $redisWrapperMock->method('incr')->willReturn(static::LOCK_THRESHOLD);

        // Assert
        $redisWrapperMock->expects($this->never())->method('setex');
        $redisWrapperMock->expects($this->once())
            ->method('expire')
            ->with(static::KEY, static::LOCK_TTL)
            ->willReturn(true);

        // Act
        (new AtomicCounterWriter($redisWrapperMock))->increment(static::KEY, static::WINDOW_TTL, static::LOCK_THRESHOLD, static::LOCK_TTL);
    }

    public function testIncrementThrowsExceptionWhenTheCounterCannotBeIncremented(): void
    {
        // Arrange
        $redisWrapperMock = $this->createMock(SecurityBlockerRedisWrapperInterface::class);
        $redisWrapperMock->method('setIfNotExists')->willReturn(false);
        $redisWrapperMock->method('incr')->willReturn(0);

        // Assert
        $this->expectException(SecurityBlockerException::class);

        // Act
        (new AtomicCounterWriter($redisWrapperMock))->increment(static::KEY, static::WINDOW_TTL, static::LOCK_THRESHOLD, static::LOCK_TTL);
    }

    public function testIncrementThrowsExceptionWhenTheLockCannotBeStarted(): void
    {
        // Arrange
        $redisWrapperMock = $this->createMock(SecurityBlockerRedisWrapperInterface::class);
        $redisWrapperMock->method('setIfNotExists')->willReturn(false);
        $redisWrapperMock->method('incr')->willReturn(static::LOCK_THRESHOLD);
        $redisWrapperMock->method('expire')->willReturn(false);

        // Assert
        $this->expectException(SecurityBlockerException::class);

        // Act
        (new AtomicCounterWriter($redisWrapperMock))->increment(static::KEY, static::WINDOW_TTL, static::LOCK_THRESHOLD, static::LOCK_TTL);
    }

    protected function incrementTimes(int $times): int
    {
        $count = 0;

        for ($i = 0; $i < $times; $i++) {
            $count = $this->atomicCounterWriter->increment(static::KEY, static::WINDOW_TTL, static::LOCK_THRESHOLD, static::LOCK_TTL);
        }

        return $count;
    }

    protected function getExpiresAt(): ?int
    {
        return $this->inMemorySecurityBlockerRedisClient->getExpiresAt((new SecurityBlockerConfig())->getRedisConnectionKey(), static::STORAGE_KEY);
    }
}
