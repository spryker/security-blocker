<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Client\SecurityBlocker\Client;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\SecurityCheckAuthContextTransfer;
use Psr\Log\LoggerInterface;
use SprykerTest\Client\SecurityBlocker\SecurityBlockerClientTester;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group Client
 * @group SecurityBlocker
 * @group Client
 * @group IsLoginChallengeRequiredTest
 * Add your own group annotations below this line
 */
class IsLoginChallengeRequiredTest extends Unit
{
    protected const string TYPE_USER = 'user';

    protected const string IP = '203.0.113.9';

    protected const string LOGIN_IDENTIFIER = 'operator@example.com';

    protected const string UNKNOWN_IP_COUNTER_KEY_STRATEGY = 'ip-and-unknown';

    protected SecurityBlockerClientTester $tester;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tester->haveInMemorySecurityBlockerRedisClient();
        $this->tester->haveLoginProtectionThresholds();
    }

    public function testReturnsFalseBeforeAnyChallengeThresholdIsReached(): void
    {
        // Act
        $isLoginChallengeRequired = $this->tester->getSecurityBlockerClient()->isLoginChallengeRequired($this->createContext());

        // Assert
        $this->assertFalse($isLoginChallengeRequired);
    }

    public function testReturnsTrueOnceTheAccountChallengeThresholdIsReached(): void
    {
        // Arrange
        $this->tester->reserveLoginAttempts($this->createContext(), SecurityBlockerClientTester::ACCOUNT_CHALLENGE_NUMBER_OF_ATTEMPTS);

        // Act
        $isLoginChallengeRequired = $this->tester->getSecurityBlockerClient()->isLoginChallengeRequired($this->createContext());

        // Assert
        $this->assertTrue($isLoginChallengeRequired);
    }

    public function testReturnsTrueAndLogsAnErrorWhenTheLoginAttemptCannotBeChecked(): void
    {
        // Arrange
        $loggerMock = $this->createMock(LoggerInterface::class);
        $loggerMock->expects($this->once())->method('error');
        $securityCheckAuthContextTransfer = $this->createContext()->setIpCounterKeyStrategy(static::UNKNOWN_IP_COUNTER_KEY_STRATEGY);

        // Act
        $isLoginChallengeRequired = $this->tester->createLoginChallengeRequirementChecker($loggerMock)->isLoginChallengeRequired($securityCheckAuthContextTransfer);

        // Assert
        $this->assertTrue($isLoginChallengeRequired);
    }

    protected function createContext(): SecurityCheckAuthContextTransfer
    {
        return $this->tester->createLoginAttemptContext(SecurityBlockerClientTester::REALM, static::TYPE_USER, static::IP, static::LOGIN_IDENTIFIER);
    }
}
