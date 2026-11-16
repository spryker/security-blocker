<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Client\SecurityBlocker\Client;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\SecurityCheckAuthContextTransfer;
use SprykerTest\Client\SecurityBlocker\Helper\InMemorySecurityBlockerRedisClient;
use SprykerTest\Client\SecurityBlocker\SecurityBlockerClientTester;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group Client
 * @group SecurityBlocker
 * @group Client
 * @group LoginAttemptCheckReuseTest
 * Add your own group annotations below this line
 */
class LoginAttemptCheckReuseTest extends Unit
{
    protected const string TYPE_CUSTOMER = 'customer';

    protected const string IP = '203.0.113.1';

    protected const string LOGIN_IDENTIFIER = 'customer@example.com';

    protected const string OTHER_LOGIN_IDENTIFIER = 'other@example.com';

    /**
     * The commands of one check: the per-IP counter, then the realm-wide and the account counter in one round trip.
     *
     * @var list<string>
     */
    protected const array CHECK_COMMANDS = ['get', 'mget'];

    protected SecurityBlockerClientTester $tester;

    /**
     * The login blocker checks the attempt before the CAPTCHA step-up plugin asks again with the same context.
     */
    public function testRepeatedCheckOfTheSameContextInOneRequestReadsTheCountersOnce(): void
    {
        // Arrange
        $inMemorySecurityBlockerRedisClient = $this->haveCheckedLoginAttemptInCurrentRequest();
        $executedCommandCount = count($inMemorySecurityBlockerRedisClient->getExecutedCommands());

        // Act
        $securityCheckAuthResponseTransfer = $this->tester->getSecurityBlockerClient()->checkLoginAttempt($this->createLoginAttemptContext(static::LOGIN_IDENTIFIER));

        // Assert
        $this->assertSame([], array_slice($inMemorySecurityBlockerRedisClient->getExecutedCommands(), $executedCommandCount));
        $this->assertFalse($securityCheckAuthResponseTransfer->getIsBlocked());
        $this->assertSame(static::LOGIN_IDENTIFIER, $securityCheckAuthResponseTransfer->getSecurityCheckAuthContextOrFail()->getLoginIdentifier());
    }

    public function testLoginChallengeRequirementReusesTheCheckOfTheSameRequest(): void
    {
        // Arrange
        $inMemorySecurityBlockerRedisClient = $this->haveCheckedLoginAttemptInCurrentRequest();
        $executedCommandCount = count($inMemorySecurityBlockerRedisClient->getExecutedCommands());

        // Act
        $this->tester->getSecurityBlockerClient()->isLoginChallengeRequired($this->createLoginAttemptContext(static::LOGIN_IDENTIFIER));

        // Assert
        $this->assertSame([], array_slice($inMemorySecurityBlockerRedisClient->getExecutedCommands(), $executedCommandCount));
    }

    public function testCheckOfAnotherLoginIdentifierReadsTheCounters(): void
    {
        // Arrange
        $inMemorySecurityBlockerRedisClient = $this->haveCheckedLoginAttemptInCurrentRequest();
        $executedCommandCount = count($inMemorySecurityBlockerRedisClient->getExecutedCommands());

        // Act
        $this->tester->getSecurityBlockerClient()->checkLoginAttempt($this->createLoginAttemptContext(static::OTHER_LOGIN_IDENTIFIER));

        // Assert
        $this->assertSame(static::CHECK_COMMANDS, array_slice($inMemorySecurityBlockerRedisClient->getExecutedCommands(), $executedCommandCount));
    }

    /**
     * Each write changes the counters a check reads, so a check from before it no longer answers.
     *
     * @dataProvider provideLoginCounterWriteMethodNames
     */
    public function testCheckAfterAWriteOfTheLoginCountersReadsThemAgain(string $writeMethodName): void
    {
        // Arrange
        $inMemorySecurityBlockerRedisClient = $this->haveCheckedLoginAttemptInCurrentRequest();
        $this->tester->getSecurityBlockerClient()->{$writeMethodName}($this->createLoginAttemptContext(static::LOGIN_IDENTIFIER));
        $executedCommandCount = count($inMemorySecurityBlockerRedisClient->getExecutedCommands());

        // Act
        $this->tester->getSecurityBlockerClient()->checkLoginAttempt($this->createLoginAttemptContext(static::LOGIN_IDENTIFIER));

        // Assert
        $this->assertSame(static::CHECK_COMMANDS, array_slice($inMemorySecurityBlockerRedisClient->getExecutedCommands(), $executedCommandCount));
    }

    /**
     * @return array<string, list<string>>
     */
    public static function provideLoginCounterWriteMethodNames(): array
    {
        return [
            'reservation' => ['reserveLoginAttempt'],
            'failed login' => ['registerFailedLoginAttempt'],
            'successful login' => ['registerSuccessfulLoginAttempt'],
            'per-IP count of the legacy login protection' => ['incrementLoginAttemptCount'],
        ];
    }

    public function testEveryCheckReadsTheCountersWithoutACurrentRequest(): void
    {
        // Arrange
        $inMemorySecurityBlockerRedisClient = $this->tester->haveInMemorySecurityBlockerRedisClient();
        $this->tester->haveLoginProtectionThresholds();
        $this->tester->haveNoCurrentRequest();
        $this->tester->getSecurityBlockerClient()->checkLoginAttempt($this->createLoginAttemptContext(static::LOGIN_IDENTIFIER));
        $executedCommandCount = count($inMemorySecurityBlockerRedisClient->getExecutedCommands());

        // Act
        $this->tester->getSecurityBlockerClient()->checkLoginAttempt($this->createLoginAttemptContext(static::LOGIN_IDENTIFIER));

        // Assert
        $this->assertSame(static::CHECK_COMMANDS, array_slice($inMemorySecurityBlockerRedisClient->getExecutedCommands(), $executedCommandCount));
    }

    protected function haveCheckedLoginAttemptInCurrentRequest(): InMemorySecurityBlockerRedisClient
    {
        $inMemorySecurityBlockerRedisClient = $this->tester->haveInMemorySecurityBlockerRedisClient();
        $this->tester->haveLoginProtectionThresholds();
        $this->tester->haveCurrentRequest();
        $this->tester->getSecurityBlockerClient()->checkLoginAttempt($this->createLoginAttemptContext(static::LOGIN_IDENTIFIER));

        return $inMemorySecurityBlockerRedisClient;
    }

    protected function createLoginAttemptContext(string $loginIdentifier): SecurityCheckAuthContextTransfer
    {
        return $this->tester->createLoginAttemptContext(SecurityBlockerClientTester::REALM, static::TYPE_CUSTOMER, static::IP, $loginIdentifier);
    }
}
