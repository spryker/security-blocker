<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Client\SecurityBlocker\Client;

use Codeception\Test\Unit;
use SprykerTest\Client\SecurityBlocker\SecurityBlockerClientTester;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group Client
 * @group SecurityBlocker
 * @group Client
 * @group CrossSurfaceLoginRealmTest
 * Add your own group annotations below this line
 */
class CrossSurfaceLoginRealmTest extends Unit
{
    protected const string TYPE_FIRST_SURFACE = 'first-surface';

    protected const string TYPE_SECOND_SURFACE = 'second-surface';

    protected const string TYPE_THIRD_SURFACE = 'third-surface';

    protected const string IP = '203.0.113.1';

    protected const string LOGIN_IDENTIFIER = 'admin@example.com';

    protected SecurityBlockerClientTester $tester;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tester->haveInMemorySecurityBlockerRedisClient();
        $this->tester->haveLoginProtectionThresholds();
    }

    /**
     * One identity is reachable through several login surfaces of its realm; separate counters would multiply the attacker's budget.
     */
    public function testAttemptsOnOneSurfaceOfARealmLockTheSameAccountOnEveryOtherSurfaceOfTheRealm(): void
    {
        // Arrange
        $this->tester->reserveLoginAttempts(
            $this->tester->createLoginAttemptContext(SecurityBlockerClientTester::REALM, static::TYPE_FIRST_SURFACE, static::IP, static::LOGIN_IDENTIFIER),
            SecurityBlockerClientTester::ACCOUNT_NUMBER_OF_ATTEMPTS,
        );

        // Act
        $secondSurfaceResponseTransfer = $this->tester->getSecurityBlockerClient()->checkLoginAttempt(
            $this->tester->createLoginAttemptContext(SecurityBlockerClientTester::REALM, static::TYPE_SECOND_SURFACE, '198.51.100.7', static::LOGIN_IDENTIFIER),
        );
        $thirdSurfaceResponseTransfer = $this->tester->getSecurityBlockerClient()->checkLoginAttempt(
            $this->tester->createLoginAttemptContext(SecurityBlockerClientTester::REALM, static::TYPE_THIRD_SURFACE, '198.51.100.8', static::LOGIN_IDENTIFIER),
        );

        // Assert
        $this->assertTrue($secondSurfaceResponseTransfer->getIsBlocked());
        $this->assertTrue($thirdSurfaceResponseTransfer->getIsBlocked());
    }

    public function testTheSameIdentifierInAnotherRealmIsCountedSeparately(): void
    {
        // Arrange
        $this->tester->reserveLoginAttempts(
            $this->tester->createLoginAttemptContext(SecurityBlockerClientTester::REALM, static::TYPE_FIRST_SURFACE, static::IP, static::LOGIN_IDENTIFIER),
            SecurityBlockerClientTester::ACCOUNT_NUMBER_OF_ATTEMPTS,
        );

        // Act
        $securityCheckAuthResponseTransfer = $this->tester->getSecurityBlockerClient()->checkLoginAttempt(
            $this->tester->createLoginAttemptContext(SecurityBlockerClientTester::OTHER_REALM, static::TYPE_THIRD_SURFACE, '198.51.100.7', static::LOGIN_IDENTIFIER),
        );

        // Assert
        $this->assertFalse($securityCheckAuthResponseTransfer->getIsBlocked());
    }

    public function testIpCountersStayPerLoginSurface(): void
    {
        // Arrange
        $this->tester->registerFailedLoginAttempts(
            $this->tester->createLoginAttemptContext(SecurityBlockerClientTester::REALM, static::TYPE_FIRST_SURFACE, static::IP, 'someone@example.com'),
            SecurityBlockerClientTester::IP_NUMBER_OF_ATTEMPTS,
        );

        // Act
        $securityCheckAuthResponseTransfer = $this->tester->getSecurityBlockerClient()->checkLoginAttempt(
            $this->tester->createLoginAttemptContext(SecurityBlockerClientTester::REALM, static::TYPE_SECOND_SURFACE, static::IP, static::LOGIN_IDENTIFIER),
        );

        // Assert
        $this->assertFalse($securityCheckAuthResponseTransfer->getIsBlocked());
    }
}
