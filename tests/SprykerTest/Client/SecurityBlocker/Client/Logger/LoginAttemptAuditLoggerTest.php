<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Client\SecurityBlocker\Client\Logger;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\SecurityBlockerLoginRealmConfigurationTransfer;
use Generated\Shared\Transfer\SecurityCheckAuthContextTransfer;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Spryker\Client\SecurityBlocker\Logger\LoginAttemptAuditLogger;
use Spryker\Client\SecurityBlocker\LoginAttempt\LoginAttemptCheckResultRequestStorage;
use Spryker\Client\SecurityBlocker\LoginAttempt\LoginAttemptRegistrar;
use Spryker\Client\SecurityBlocker\LoginAttempt\LoginAttemptReserver;
use Spryker\Client\SecurityBlocker\Normalizer\LoginIdentifierNormalizer;
use Spryker\Client\SecurityBlocker\Redis\SecurityBlockerRedisWrapper;
use Spryker\Client\SecurityBlocker\Resolver\ConfigurationResolver;
use Spryker\Client\SecurityBlocker\SecurityBlockerConfig as ClientSecurityBlockerConfig;
use Spryker\Client\SecurityBlocker\SecurityBlockerFactory;
use Spryker\Client\SecurityBlocker\Storage\KeyBuilder\LoginAttemptKeyBuilder;
use Spryker\Client\SecurityBlocker\Storage\Writer\AtomicCounterWriter;
use Spryker\Client\SecurityBlocker\Storage\Writer\IpCounterShareWriter;
use SprykerTest\Client\SecurityBlocker\Helper\InMemorySecurityBlockerRedisClient;
use SprykerTest\Client\SecurityBlocker\SecurityBlockerClientTester;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group Client
 * @group SecurityBlocker
 * @group Client
 * @group Logger
 * @group LoginAttemptAuditLoggerTest
 * Add your own group annotations below this line
 */
class LoginAttemptAuditLoggerTest extends Unit
{
    protected const string LOGIN_IDENTIFIER_VICTIM = 'victim@example.com';

    protected const string IP = '203.0.113.1';

    protected SecurityBlockerClientTester $tester;

    protected TestHandler $testHandler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tester->haveLoginProtectionThresholds();
        $this->testHandler = new TestHandler();
    }

    public function testAuditLogsAreNotWrittenByDefault(): void
    {
        // Arrange
        $loginAttemptAuditLogger = new LoginAttemptAuditLogger(new Logger('security', [$this->testHandler]), new ClientSecurityBlockerConfig());

        // Act
        $loginAttemptAuditLogger->addAccountLockedAuditLog(SecurityBlockerClientTester::REALM, hash('sha256', static::LOGIN_IDENTIFIER_VICTIM), 9, 360);
        $loginAttemptAuditLogger->addGlobalChallengeThresholdReachedAuditLog(SecurityBlockerClientTester::REALM, 100, 300);

        // Assert
        $this->assertCount(0, $this->testHandler->getRecords());
    }

    public function testAddAccountLockedAuditLogCarriesTheRealmAndTheKeyHashOnly(): void
    {
        // Arrange
        $loginAttemptAuditLogger = $this->createLoginAttemptAuditLogger();
        $accountKeyHash = hash('sha256', static::LOGIN_IDENTIFIER_VICTIM);

        // Act
        $loginAttemptAuditLogger->addAccountLockedAuditLog(SecurityBlockerClientTester::REALM, $accountKeyHash, 9, 360);

        // Assert
        $records = $this->testHandler->getRecords();
        $this->assertCount(1, $records);
        $this->assertSame(['login_account_locked'], $records[0]['context']['tags']);
        $this->assertSame(SecurityBlockerClientTester::REALM, $records[0]['context']['realm']);
        $this->assertSame($accountKeyHash, $records[0]['context']['account_key_hash']);
        $this->assertSame(9, $records[0]['context']['number_of_attempts']);
        $this->assertSame(360, $records[0]['context']['blocked_for']);
    }

    public function testReservingLogsTheLockExactlyOnceAndNeverTheIdentifier(): void
    {
        // Arrange
        $loginAttemptReserver = $this->createLoginAttemptReserver(new InMemorySecurityBlockerRedisClient());

        // Act
        for ($i = 0; $i < SecurityBlockerClientTester::ACCOUNT_NUMBER_OF_ATTEMPTS + 5; $i++) {
            $loginAttemptReserver->reserveLoginAttempt($this->createVictimContext());
        }

        // Assert
        $records = $this->testHandler->getRecords();
        $this->assertCount(1, $records);
        $encodedRecord = json_encode([$records[0]['message'], $records[0]['context'], $records[0]['extra']], JSON_THROW_ON_ERROR);
        $this->assertSame(['login_account_locked'], $records[0]['context']['tags']);
        $this->assertStringNotContainsString('victim', $encodedRecord);
        $this->assertStringNotContainsString(static::IP, $encodedRecord);
    }

    public function testRegisteringFailuresLogsTheRealmWideThresholdExactlyOnce(): void
    {
        // Arrange
        $this->tester->haveLoginRealmConfiguration(SecurityBlockerClientTester::REALM, [
            SecurityBlockerLoginRealmConfigurationTransfer::GLOBAL_CHALLENGE_NUMBER_OF_ATTEMPTS => 3,
        ]);
        $loginAttemptRegistrar = $this->createLoginAttemptRegistrar(new InMemorySecurityBlockerRedisClient());

        // Act
        for ($i = 0; $i < 6; $i++) {
            $loginAttemptRegistrar->registerFailedLoginAttempt($this->createVictimContext()->setIp(sprintf('192.0.2.%d', $i)));
        }

        // Assert
        $records = $this->testHandler->getRecords();
        $this->assertCount(1, $records);
        $this->assertSame(['login_global_challenge_threshold_reached'], $records[0]['context']['tags']);
        $this->assertSame(3, $records[0]['context']['number_of_failures']);
    }

    protected function createLoginAttemptReserver(InMemorySecurityBlockerRedisClient $inMemorySecurityBlockerRedisClient): LoginAttemptReserver
    {
        $securityBlockerConfig = new ClientSecurityBlockerConfig();

        return new LoginAttemptReserver(
            $this->tester->createLoginRealmConfigurationResolver(),
            new LoginAttemptKeyBuilder(new LoginIdentifierNormalizer($securityBlockerConfig)),
            new AtomicCounterWriter(new SecurityBlockerRedisWrapper($inMemorySecurityBlockerRedisClient, $securityBlockerConfig)),
            $this->createLoginAttemptAuditLogger(),
            new LoginAttemptCheckResultRequestStorage(new RequestStack()),
        );
    }

    protected function createLoginAttemptRegistrar(InMemorySecurityBlockerRedisClient $inMemorySecurityBlockerRedisClient): LoginAttemptRegistrar
    {
        $securityBlockerConfig = new ClientSecurityBlockerConfig();
        $securityBlockerRedisWrapper = new SecurityBlockerRedisWrapper($inMemorySecurityBlockerRedisClient, $securityBlockerConfig);
        $loginAttemptKeyBuilder = new LoginAttemptKeyBuilder(new LoginIdentifierNormalizer($securityBlockerConfig));
        $atomicCounterWriter = new AtomicCounterWriter($securityBlockerRedisWrapper);

        return new LoginAttemptRegistrar(
            $this->tester->createLoginRealmConfigurationResolver(),
            new ConfigurationResolver([], $securityBlockerConfig),
            (new SecurityBlockerFactory())->createSecurityBlockerStorageKeyBuilder(),
            $loginAttemptKeyBuilder,
            $atomicCounterWriter,
            new IpCounterShareWriter($loginAttemptKeyBuilder, $atomicCounterWriter, $securityBlockerRedisWrapper),
            $securityBlockerRedisWrapper,
            $this->createLoginAttemptAuditLogger(),
            new LoginAttemptCheckResultRequestStorage(new RequestStack()),
        );
    }

    protected function createLoginAttemptAuditLogger(): LoginAttemptAuditLogger
    {
        $securityBlockerConfigMock = $this->createPartialMock(ClientSecurityBlockerConfig::class, ['isSecurityAuditLogEnabled']);
        $securityBlockerConfigMock->method('isSecurityAuditLogEnabled')->willReturn(true);

        return new LoginAttemptAuditLogger(new Logger('security', [$this->testHandler]), $securityBlockerConfigMock);
    }

    protected function createVictimContext(): SecurityCheckAuthContextTransfer
    {
        return $this->tester->createLoginAttemptContext(SecurityBlockerClientTester::REALM, 'customer', static::IP, static::LOGIN_IDENTIFIER_VICTIM);
    }
}
