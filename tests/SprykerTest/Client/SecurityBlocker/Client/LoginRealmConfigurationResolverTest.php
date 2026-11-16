<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Client\SecurityBlocker\Client;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\SecurityBlockerLoginRealmConfigurationTransfer;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use SprykerTest\Client\SecurityBlocker\SecurityBlockerClientTester;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group Client
 * @group SecurityBlocker
 * @group Client
 * @group LoginRealmConfigurationResolverTest
 * Add your own group annotations below this line
 */
class LoginRealmConfigurationResolverTest extends Unit
{
    protected const string UNCONFIGURED_REALM = 'unconfigured-realm';

    protected SecurityBlockerClientTester $tester;

    /**
     * Unlike the per-type settings, a realm threshold of `0` disables its dimension, so it must never be replaced by a default.
     */
    public function testResolveReturnsTheRealmConfigurationOfTheExpanderPluginsWithAZeroKept(): void
    {
        // Arrange
        $this->tester->haveLoginRealmConfiguration(SecurityBlockerClientTester::REALM, [
            SecurityBlockerLoginRealmConfigurationTransfer::ACCOUNT_CHALLENGE_NUMBER_OF_ATTEMPTS => 0,
        ]);
        $testHandler = new TestHandler();

        // Act
        $securityBlockerLoginRealmConfigurationTransfer = $this->tester
            ->createLoginRealmConfigurationResolver(new Logger('test', [$testHandler]))
            ->resolve(SecurityBlockerClientTester::REALM);

        // Assert
        $this->assertSame(SecurityBlockerClientTester::REALM, $securityBlockerLoginRealmConfigurationTransfer->getRealm());
        $this->assertSame(SecurityBlockerClientTester::ACCOUNT_NUMBER_OF_ATTEMPTS, $securityBlockerLoginRealmConfigurationTransfer->getAccountNumberOfAttempts());
        $this->assertSame(0, $securityBlockerLoginRealmConfigurationTransfer->getAccountChallengeNumberOfAttempts());
        $this->assertSame([], $testHandler->getRecords());
    }

    public function testResolveReturnsTheModuleDefaultsAndLogsAWarningForARealmNoExpanderPluginConfigures(): void
    {
        // Arrange
        $this->tester->haveLoginRealmConfiguration(SecurityBlockerClientTester::REALM);
        $testHandler = new TestHandler();

        // Act
        $securityBlockerLoginRealmConfigurationTransfer = $this->tester
            ->createLoginRealmConfigurationResolver(new Logger('test', [$testHandler]))
            ->resolve(static::UNCONFIGURED_REALM);

        // Assert
        $this->assertSame(static::UNCONFIGURED_REALM, $securityBlockerLoginRealmConfigurationTransfer->getRealm());
        $this->assertSame(9, $securityBlockerLoginRealmConfigurationTransfer->getAccountNumberOfAttempts());
        $this->assertSame(900, $securityBlockerLoginRealmConfigurationTransfer->getAccountTtl());
        $this->assertSame(360, $securityBlockerLoginRealmConfigurationTransfer->getAccountBlockFor());
        $this->assertSame(3, $securityBlockerLoginRealmConfigurationTransfer->getAccountChallengeNumberOfAttempts());
        $this->assertSame(5, $securityBlockerLoginRealmConfigurationTransfer->getIpChallengeNumberOfAttempts());
        $this->assertSame(100, $securityBlockerLoginRealmConfigurationTransfer->getGlobalChallengeNumberOfAttempts());
        $this->assertSame(300, $securityBlockerLoginRealmConfigurationTransfer->getGlobalTtl());
        $this->assertTrue($testHandler->hasWarningThatContains(static::UNCONFIGURED_REALM));
    }
}
