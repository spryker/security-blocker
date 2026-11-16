<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Client\SecurityBlocker\Storage\KeyBuilder;

use Spryker\Client\SecurityBlocker\Normalizer\LoginIdentifierNormalizerInterface;

class LoginAttemptKeyBuilder implements LoginAttemptKeyBuilderInterface
{
    protected const string HASH_ALGORITHM = 'sha256';

    protected const string ACCOUNT_KEY_PATTERN = 'login-account:{%s}:%s';

    protected const string IP_KEY_PATTERN = 'login-ip:{%s}:%s';

    protected const string GLOBAL_KEY_PATTERN = 'login-global:{%s}';

    protected const string IP_COUNTER_SHARE_KEY_PATTERN = 'login-ip-share:%s:%s';

    protected const string IP_COUNTER_SHARE_CLAIM_KEY_PATTERN = 'login-ip-share-claim:%s:%s';

    public function __construct(protected LoginIdentifierNormalizerInterface $loginIdentifierNormalizer)
    {
    }

    public function findAccountKeyHash(string $loginIdentifier): ?string
    {
        $normalizedLoginIdentifier = $this->loginIdentifierNormalizer->normalize($loginIdentifier);

        if ($normalizedLoginIdentifier === '') {
            return null;
        }

        return hash(static::HASH_ALGORITHM, $normalizedLoginIdentifier);
    }

    public function getAccountKey(string $realm, string $accountKeyHash): string
    {
        return sprintf(static::ACCOUNT_KEY_PATTERN, $realm, $accountKeyHash);
    }

    public function getIpKey(string $realm, string $ip): string
    {
        return sprintf(static::IP_KEY_PATTERN, $realm, $ip);
    }

    public function getGlobalKey(string $realm): string
    {
        return sprintf(static::GLOBAL_KEY_PATTERN, $realm);
    }

    public function getIpCounterShareKey(string $ipCounterKey, string $accountKeyHash): string
    {
        return sprintf(static::IP_COUNTER_SHARE_KEY_PATTERN, $accountKeyHash, $ipCounterKey);
    }

    public function getIpCounterShareClaimKey(string $ipCounterKey, string $accountKeyHash): string
    {
        return sprintf(static::IP_COUNTER_SHARE_CLAIM_KEY_PATTERN, $accountKeyHash, $ipCounterKey);
    }
}
