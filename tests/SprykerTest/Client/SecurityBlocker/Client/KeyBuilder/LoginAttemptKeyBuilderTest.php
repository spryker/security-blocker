<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Client\SecurityBlocker\Client\KeyBuilder;

use Codeception\Test\Unit;
use Spryker\Client\SecurityBlocker\Normalizer\LoginIdentifierNormalizer;
use Spryker\Client\SecurityBlocker\SecurityBlockerConfig;
use Spryker\Client\SecurityBlocker\Storage\KeyBuilder\LoginAttemptKeyBuilder;
use SprykerTest\Client\SecurityBlocker\SecurityBlockerClientTester;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group Client
 * @group SecurityBlocker
 * @group Client
 * @group KeyBuilder
 * @group LoginAttemptKeyBuilderTest
 * Add your own group annotations below this line
 */
class LoginAttemptKeyBuilderTest extends Unit
{
    protected const string IP = '203.0.113.1';

    public function testFindAccountKeyHashReturnsSha256OfTheNormalizedIdentifier(): void
    {
        // Arrange
        $loginAttemptKeyBuilder = $this->createLoginAttemptKeyBuilder();

        // Act
        $accountKeyHash = $loginAttemptKeyBuilder->findAccountKeyHash(' Victim@Example.com ');

        // Assert
        $this->assertSame(hash('sha256', 'victim@example.com'), $accountKeyHash);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $accountKeyHash);
    }

    public function testFindAccountKeyHashReturnsNullForAnIdentifierThatNormalizesToEmpty(): void
    {
        // Arrange
        $loginAttemptKeyBuilder = $this->createLoginAttemptKeyBuilder();

        // Act
        $accountKeyHash = $loginAttemptKeyBuilder->findAccountKeyHash(" \u{00A0} ");

        // Assert
        $this->assertNull($accountKeyHash);
    }

    public function testGetAccountKeyIsRealmScopedAndCarriesNoClearTextIdentifier(): void
    {
        // Arrange
        $loginAttemptKeyBuilder = $this->createLoginAttemptKeyBuilder();
        $accountKeyHash = $loginAttemptKeyBuilder->findAccountKeyHash('victim:with:colons@example.com');

        // Act
        $accountKey = $loginAttemptKeyBuilder->getAccountKey(SecurityBlockerClientTester::REALM, $accountKeyHash);
        $otherRealmAccountKey = $loginAttemptKeyBuilder->getAccountKey(SecurityBlockerClientTester::OTHER_REALM, $accountKeyHash);

        // Assert
        $this->assertSame(sprintf('login-account:{%s}:%s', SecurityBlockerClientTester::REALM, $accountKeyHash), $accountKey);
        $this->assertNotSame($accountKey, $otherRealmAccountKey);
        $this->assertStringNotContainsString('victim', $accountKey);
        $this->assertSame(2, substr_count($accountKey, ':'));
    }

    public function testGetGlobalKeyIsRealmScoped(): void
    {
        // Arrange
        $loginAttemptKeyBuilder = $this->createLoginAttemptKeyBuilder();

        // Act
        $globalKey = $loginAttemptKeyBuilder->getGlobalKey(SecurityBlockerClientTester::REALM);
        $otherRealmGlobalKey = $loginAttemptKeyBuilder->getGlobalKey(SecurityBlockerClientTester::OTHER_REALM);

        // Assert
        $this->assertSame(sprintf('login-global:{%s}', SecurityBlockerClientTester::REALM), $globalKey);
        $this->assertSame(sprintf('login-global:{%s}', SecurityBlockerClientTester::OTHER_REALM), $otherRealmGlobalKey);
    }

    public function testGetIpKeyIsRealmScopedAndCarriesNoAccount(): void
    {
        // Arrange
        $loginAttemptKeyBuilder = $this->createLoginAttemptKeyBuilder();

        // Act
        $ipKey = $loginAttemptKeyBuilder->getIpKey(SecurityBlockerClientTester::REALM, static::IP);
        $otherRealmIpKey = $loginAttemptKeyBuilder->getIpKey(SecurityBlockerClientTester::OTHER_REALM, static::IP);

        // Assert
        $this->assertSame(sprintf('login-ip:{%s}:%s', SecurityBlockerClientTester::REALM, static::IP), $ipKey);
        $this->assertSame(sprintf('login-ip:{%s}:%s', SecurityBlockerClientTester::OTHER_REALM, static::IP), $otherRealmIpKey);
    }

    protected function createLoginAttemptKeyBuilder(): LoginAttemptKeyBuilder
    {
        return new LoginAttemptKeyBuilder(new LoginIdentifierNormalizer(new SecurityBlockerConfig()));
    }

    public function testAccountIpAndRealmWideKeysOfARealmShareOneClusterHashTag(): void
    {
        // Arrange
        $loginAttemptKeyBuilder = $this->createLoginAttemptKeyBuilder();
        $accountKeyHash = (string)$loginAttemptKeyBuilder->findAccountKeyHash('victim@example.com');

        // Act
        $accountKey = $loginAttemptKeyBuilder->getAccountKey(SecurityBlockerClientTester::REALM, $accountKeyHash);
        $ipKey = $loginAttemptKeyBuilder->getIpKey(SecurityBlockerClientTester::REALM, static::IP);
        $globalKey = $loginAttemptKeyBuilder->getGlobalKey(SecurityBlockerClientTester::REALM);

        // Assert
        $this->assertSame(SecurityBlockerClientTester::REALM, $this->findHashTag($accountKey));
        $this->assertSame(SecurityBlockerClientTester::REALM, $this->findHashTag($ipKey));
        $this->assertSame(SecurityBlockerClientTester::REALM, $this->findHashTag($globalKey));
    }

    protected function findHashTag(string $key): ?string
    {
        return preg_match('/\{([^}]+)\}/', $key, $matches) === 1 ? $matches[1] : null;
    }
}
