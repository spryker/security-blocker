<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Client\SecurityBlocker\Client;

use Codeception\Test\Unit;
use Generated\Shared\DataBuilder\SecurityCheckAuthContextBuilder;
use Generated\Shared\Transfer\SecurityCheckAuthContextTransfer;
use Spryker\Client\SecurityBlocker\Dependency\Client\SecurityBlockerToRedisClientInterface;
use Spryker\Client\SecurityBlocker\Exception\SecurityBlockerException;
use Spryker\Client\SecurityBlocker\SecurityBlockerConfig;
use Spryker\Client\SecurityBlocker\SecurityBlockerDependencyProvider;
use Spryker\Shared\Kernel\Transfer\Exception\NullValueException;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group Client
 * @group SecurityBlocker
 * @group Client
 * @group IncrementLoginAttemptCountTest
 * Add your own group annotations below this line
 */
class IncrementLoginAttemptCountTest extends Unit
{
    /**
     * @var \SprykerTest\Client\SecurityBlocker\SecurityBlockerClientTester
     */
    protected $tester;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|\Spryker\Client\SecurityBlocker\Dependency\Client\SecurityBlockerToRedisClientInterface
     */
    protected $redisClientMock;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->redisClientMock = $this->getMockBuilder(SecurityBlockerToRedisClientInterface::class)
            ->getMock();
        $this->tester->setDependency(SecurityBlockerDependencyProvider::CLIENT_REDIS, $this->redisClientMock);
    }

    /**
     * @return void
     */
    public function testIncrementLoginAttemptCountWillRequireType(): void
    {
        // Arrange
        $securityCheckAuthContextTransfer = (new SecurityCheckAuthContextTransfer());
        $this->expectException(NullValueException::class);

        // Act
        $this->tester->getLocator()->securityBlocker()->client()->incrementLoginAttemptCount($securityCheckAuthContextTransfer);
    }

    /**
     * @return void
     */
    public function testIncrementLoginAttemptCountWillSucceed(): void
    {
        // Arrange
        $securityCheckAuthContextTransfer = (new SecurityCheckAuthContextBuilder())->build();
        $securityBlockerConfig = new SecurityBlockerConfig();
        $expectedRedisKey = sprintf(
            'kv:%s:%s:%s',
            $securityCheckAuthContextTransfer->getType(),
            $securityCheckAuthContextTransfer->getIp(),
            $securityCheckAuthContextTransfer->getAccount(),
        );

        $expectedNumberOfAttempts = '0';
        $this->redisClientMock
            ->method('get')
            ->willReturn($expectedNumberOfAttempts);

        $this->redisClientMock
            ->expects($this->once())
            ->method('setex')
            ->with(
                $securityBlockerConfig->getRedisConnectionKey(),
                $expectedRedisKey,
                $securityBlockerConfig->getDefaultSecurityBlockerConfigurationSettings()->getTtl(),
                '1',
            )
            ->willReturn(true);

        // Act
        $actualSecurityCheckAuthResponseTransfer = $this->tester->getLocator()
            ->securityBlocker()
            ->client()
            ->incrementLoginAttemptCount($securityCheckAuthContextTransfer);

        // Assert
        $this->assertSame($securityCheckAuthContextTransfer, $actualSecurityCheckAuthResponseTransfer->getSecurityCheckAuthContext());
        $this->assertSame((int)$expectedNumberOfAttempts + 1, $actualSecurityCheckAuthResponseTransfer->getNumberOfAttempts());
        $this->assertFalse($actualSecurityCheckAuthResponseTransfer->getIsBlocked());
    }

    /**
     * @return void
     */
    public function testIncrementLoginAttemptCountWillFailWithException(): void
    {
        // Arrange
        $securityCheckAuthContextTransfer = (new SecurityCheckAuthContextBuilder())->build();

        $this->redisClientMock
            ->expects($this->once())
            ->method('setex')
            ->willReturn(false);

        $this->expectException(SecurityBlockerException::class);

        // Act
        $this->tester->getLocator()->securityBlocker()->client()->incrementLoginAttemptCount($securityCheckAuthContextTransfer);
    }
}
