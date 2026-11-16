<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Client\SecurityBlocker\Normalizer;

use Normalizer;
use Spryker\Client\SecurityBlocker\Exception\SecurityBlockerException;
use Spryker\Client\SecurityBlocker\SecurityBlockerConfig;

class LoginIdentifierNormalizer implements LoginIdentifierNormalizerInterface
{
    protected const string ENCODING = 'UTF-8';

    protected const string PATTERN_INVISIBLE_CHARACTERS = '/[\p{Mn}\p{Cf}]++/u';

    protected const string PATTERN_SURROUNDING_WHITESPACE = '/^[\p{Z}\s]++|[\p{Z}\s]++$/u';

    /**
     * @var array<string, string>
     */
    protected const array CHARACTER_EQUIVALENTS = ["\u{0131}" => 'i'];

    protected const string SHARP_S = "\u{00DF}";

    public function __construct(protected SecurityBlockerConfig $securityBlockerConfig)
    {
    }

    public function normalize(string $loginIdentifier): string
    {
        $loginIdentifier = mb_scrub($loginIdentifier, static::ENCODING);
        $loginIdentifier = mb_substr($loginIdentifier, 0, $this->securityBlockerConfig->getLoginIdentifierMaxLength(), static::ENCODING);
        $loginIdentifier = $this->decomposeCompatibilityCharacters($loginIdentifier);
        $loginIdentifier = str_replace(static::SHARP_S, $this->securityBlockerConfig->getLoginIdentifierSharpSEquivalent(), $loginIdentifier);
        $loginIdentifier = mb_convert_case($loginIdentifier, MB_CASE_FOLD_SIMPLE, static::ENCODING);
        $loginIdentifier = strtr($loginIdentifier, static::CHARACTER_EQUIVALENTS);
        $loginIdentifier = $this->replace(static::PATTERN_INVISIBLE_CHARACTERS, '', $loginIdentifier);
        $loginIdentifier = $this->replace(static::PATTERN_SURROUNDING_WHITESPACE, '', $loginIdentifier);

        return mb_substr($loginIdentifier, 0, $this->securityBlockerConfig->getLoginIdentifierMaxLength(), static::ENCODING);
    }

    protected function decomposeCompatibilityCharacters(string $loginIdentifier): string
    {
        $decomposedLoginIdentifier = Normalizer::normalize($loginIdentifier, Normalizer::FORM_KD);

        if ($decomposedLoginIdentifier === false) {
            return $loginIdentifier;
        }

        return $decomposedLoginIdentifier;
    }

    /**
     * @throws \Spryker\Client\SecurityBlocker\Exception\SecurityBlockerException
     */
    protected function replace(string $pattern, string $replacement, string $loginIdentifier): string
    {
        $replacedLoginIdentifier = preg_replace($pattern, $replacement, $loginIdentifier);

        if ($replacedLoginIdentifier === null) {
            throw new SecurityBlockerException(sprintf('Login identifier normalization failed: %s', preg_last_error_msg()));
        }

        return $replacedLoginIdentifier;
    }
}
