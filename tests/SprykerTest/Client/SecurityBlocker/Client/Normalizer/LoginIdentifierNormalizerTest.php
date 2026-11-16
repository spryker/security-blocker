<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Client\SecurityBlocker\Client\Normalizer;

use Codeception\Test\Unit;
use Spryker\Client\SecurityBlocker\Exception\SecurityBlockerException;
use Spryker\Client\SecurityBlocker\Normalizer\LoginIdentifierNormalizer;
use Spryker\Client\SecurityBlocker\SecurityBlockerConfig;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group Client
 * @group SecurityBlocker
 * @group Client
 * @group Normalizer
 * @group LoginIdentifierNormalizerTest
 * Add your own group annotations below this line
 */
class LoginIdentifierNormalizerTest extends Unit
{
    protected const string CANONICAL_IDENTIFIER = 'victim@example.com';

    /**
     * The database authenticates all of these as the same account (the Docker default column collation
     * `utf8mb3_general_ci`, and `utf8_unicode_ci`), so each must fold to the same counter or the per-account lock is
     * bypassable.
     *
     * @dataProvider provideIdentifierVariantsOfTheSameAccount
     */
    public function testNormalizeFoldsVariantsOfTheSameAccountToOneValue(string $loginIdentifier): void
    {
        // Arrange
        $loginIdentifierNormalizer = new LoginIdentifierNormalizer(new SecurityBlockerConfig());

        // Act
        $normalizedLoginIdentifier = $loginIdentifierNormalizer->normalize($loginIdentifier);

        // Assert
        $this->assertSame(static::CANONICAL_IDENTIFIER, $normalizedLoginIdentifier);
    }

    /**
     * @return array<string, array<string>>
     */
    protected function provideIdentifierVariantsOfTheSameAccount(): array
    {
        return [
            'canonical' => ['victim@example.com'],
            'upper case' => ['VICTIM@EXAMPLE.COM'],
            'mixed case' => ['Victim@Example.Com'],
            'precomposed accent' => ["v\u{00ED}ctim@example.com"],
            'decomposed accent' => ["vi\u{0301}ctim@example.com"],
            'trailing space' => ['victim@example.com '],
            'leading tab' => ["\tvictim@example.com"],
            'non-breaking spaces' => ["\u{00A0}victim@example.com\u{00A0}"],
            'ideographic space' => ["\u{3000}victim@example.com"],
            'full-width letter' => ["\u{FF56}ictim@example.com"],
            'dotless i' => ["v\u{0131}ct\u{0131}m@example.com"],
            'zero-width space' => ["vic\u{200B}tim@example.com"],
            'soft hyphen' => ["vic\u{00AD}tim@example.com"],
            'byte order mark' => ["\u{FEFF}victim@example.com"],
        ];
    }

    /**
     * `utf8mb3_general_ci`, the Docker default column collation, compares `ß` with a single `s`.
     *
     * @dataProvider provideSharpSIdentifiersWithTheirSingleSEquivalent
     */
    public function testNormalizeFoldsSharpSToASingleSByDefault(string $loginIdentifier, string $expectedNormalizedLoginIdentifier): void
    {
        // Arrange
        $loginIdentifierNormalizer = new LoginIdentifierNormalizer(new SecurityBlockerConfig());

        // Act
        $normalizedLoginIdentifier = $loginIdentifierNormalizer->normalize($loginIdentifier);

        // Assert
        $this->assertSame($expectedNormalizedLoginIdentifier, $normalizedLoginIdentifier);
    }

    /**
     * @return array<string, array<string>>
     */
    protected function provideSharpSIdentifiersWithTheirSingleSEquivalent(): array
    {
        return [
            'sharp s next to s' => ["je\u{00DF}sica@example.com", 'jessica@example.com'],
            'sharp s alone' => ["je\u{00DF}ica@example.com", 'jesica@example.com'],
            'upper case' => ["JE\u{00DF}SICA@EXAMPLE.COM", 'jessica@example.com'],
        ];
    }

    /**
     * `utf8_unicode_ci` compares `ß` with `ss`.
     */
    public function testNormalizeFoldsSharpSToTheConfiguredEquivalent(): void
    {
        // Arrange
        $securityBlockerConfigMock = $this->createPartialMock(SecurityBlockerConfig::class, ['getLoginIdentifierSharpSEquivalent']);
        $securityBlockerConfigMock->method('getLoginIdentifierSharpSEquivalent')->willReturn('ss');
        $loginIdentifierNormalizer = new LoginIdentifierNormalizer($securityBlockerConfigMock);

        // Act
        $normalizedLoginIdentifier = $loginIdentifierNormalizer->normalize("je\u{00DF}ica@example.com");

        // Assert
        $this->assertSame('jessica@example.com', $normalizedLoginIdentifier);
    }

    /**
     * Neither collation compares a run of `s` with a single `s`. Two such accounts sharing one counter would let a
     * successful login to one of them reset the counter of the other.
     */
    public function testNormalizeKeepsIdentifiersDifferingInARunOfSDistinct(): void
    {
        // Arrange
        $loginIdentifierNormalizer = new LoginIdentifierNormalizer(new SecurityBlockerConfig());

        // Act
        $normalizedDoubleSIdentifier = $loginIdentifierNormalizer->normalize('jessica@example.com');
        $normalizedSingleSIdentifier = $loginIdentifierNormalizer->normalize('jesica@example.com');

        // Assert
        $this->assertNotSame($normalizedDoubleSIdentifier, $normalizedSingleSIdentifier);
    }

    /**
     * Both collations compare the capital sharp s `ẞ` with neither `s`, `ss` nor `ß`.
     */
    public function testNormalizeKeepsCapitalSharpSDistinct(): void
    {
        // Arrange
        $loginIdentifierNormalizer = new LoginIdentifierNormalizer(new SecurityBlockerConfig());

        // Act
        $normalizedIdentifiers = array_unique(array_map(
            fn (string $loginIdentifier): string => $loginIdentifierNormalizer->normalize($loginIdentifier),
            ["JE\u{1E9E}ICA@example.com", 'jesica@example.com', 'jessica@example.com'],
        ));

        // Assert
        $this->assertCount(3, $normalizedIdentifiers);
    }

    /**
     * `utf8mb3_general_ci` compares the combining ypogegrammeni with iota; stripping it before case folding loses it.
     */
    public function testNormalizeFoldsCombiningIotaLikeTheDatabase(): void
    {
        // Arrange
        $loginIdentifierNormalizer = new LoginIdentifierNormalizer(new SecurityBlockerConfig());

        // Act
        $normalizedCombiningIota = $loginIdentifierNormalizer->normalize("a\u{0345}b@example.com");
        $normalizedIota = $loginIdentifierNormalizer->normalize("a\u{03B9}b@example.com");

        // Assert
        $this->assertSame($normalizedIota, $normalizedCombiningIota);
    }

    /**
     * An empty result would skip the per-account counter, so a failing pattern must not degrade to one.
     */
    public function testNormalizeFailsLoudlyWhenAPatternCannotBeApplied(): void
    {
        // Arrange
        $loginIdentifierNormalizer = new class (new SecurityBlockerConfig()) extends LoginIdentifierNormalizer {
            protected const string PATTERN_SURROUNDING_WHITESPACE = '/(?:\D+|<\d+>)*[!?]/';
        };

        // Assert
        $this->expectException(SecurityBlockerException::class);

        // Act
        $loginIdentifierNormalizer->normalize(str_repeat('foobar foobar foobar ', 30));
    }

    public function testNormalizeKeepsDistinctIdentifiersDistinct(): void
    {
        // Arrange
        $loginIdentifierNormalizer = new LoginIdentifierNormalizer(new SecurityBlockerConfig());

        // Act
        $normalizedFirstIdentifier = $loginIdentifierNormalizer->normalize('a@example.com');
        $normalizedSecondIdentifier = $loginIdentifierNormalizer->normalize('b@example.com');

        // Assert
        $this->assertNotSame($normalizedFirstIdentifier, $normalizedSecondIdentifier);
    }

    public function testNormalizeReturnsEmptyStringForWhitespaceOnlyIdentifier(): void
    {
        // Arrange
        $loginIdentifierNormalizer = new LoginIdentifierNormalizer(new SecurityBlockerConfig());

        // Act
        $normalizedLoginIdentifier = $loginIdentifierNormalizer->normalize(" \t\u{00A0}\n");

        // Assert
        $this->assertSame('', $normalizedLoginIdentifier);
    }

    public function testNormalizeCapsTheIdentifierLengthDeterministically(): void
    {
        // Arrange
        $securityBlockerConfig = new SecurityBlockerConfig();
        $loginIdentifierNormalizer = new LoginIdentifierNormalizer($securityBlockerConfig);
        $maxLength = $securityBlockerConfig->getLoginIdentifierMaxLength();

        // Act
        $normalizedLongIdentifier = $loginIdentifierNormalizer->normalize(str_repeat('a', $maxLength) . 'tail-one');
        $normalizedOtherLongIdentifier = $loginIdentifierNormalizer->normalize(str_repeat('a', $maxLength) . 'tail-two');

        // Assert
        $this->assertSame(str_repeat('a', $maxLength), $normalizedLongIdentifier);
        $this->assertSame($normalizedLongIdentifier, $normalizedOtherLongIdentifier);
    }

    public function testNormalizeCapsTheLengthAfterDecomposition(): void
    {
        // Arrange
        $securityBlockerConfig = new SecurityBlockerConfig();
        $loginIdentifierNormalizer = new LoginIdentifierNormalizer($securityBlockerConfig);
        $maxLength = $securityBlockerConfig->getLoginIdentifierMaxLength();

        // Act
        $normalizedLoginIdentifier = $loginIdentifierNormalizer->normalize(str_repeat("\u{FB03}", $maxLength));

        // Assert
        $this->assertSame(str_repeat('ffi', intdiv($maxLength, 3)) . substr('ffi', 0, $maxLength % 3), $normalizedLoginIdentifier);
    }

    public function testNormalizeHandlesInvalidUtf8Deterministically(): void
    {
        // Arrange
        $loginIdentifierNormalizer = new LoginIdentifierNormalizer(new SecurityBlockerConfig());
        $invalidUtf8Identifier = "victim\xC3\x28@example.com";

        // Act
        $firstNormalization = $loginIdentifierNormalizer->normalize($invalidUtf8Identifier);
        $secondNormalization = $loginIdentifierNormalizer->normalize($invalidUtf8Identifier);

        // Assert
        $this->assertSame($firstNormalization, $secondNormalization);
        $this->assertTrue(mb_check_encoding($firstNormalization, 'UTF-8'));
    }
}
