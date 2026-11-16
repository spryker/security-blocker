<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Client\SecurityBlocker\Storage\KeyBuilder;

interface LoginAttemptKeyBuilderInterface
{
    public function findAccountKeyHash(string $loginIdentifier): ?string;

    public function getAccountKey(string $realm, string $accountKeyHash): string;

    public function getIpKey(string $realm, string $ip): string;

    public function getGlobalKey(string $realm): string;

    public function getIpCounterShareKey(string $ipCounterKey, string $accountKeyHash): string;

    public function getIpCounterShareClaimKey(string $ipCounterKey, string $accountKeyHash): string;
}
