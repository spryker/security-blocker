<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Client\SecurityBlocker\Storage\KeyBuilder;

use Generated\Shared\Transfer\SecurityCheckAuthContextTransfer;

class LoginIdentifierIpCounterKeyAccountResolver implements IpCounterKeyAccountResolverInterface
{
    public function __construct(protected LoginAttemptKeyBuilderInterface $loginAttemptKeyBuilder)
    {
    }

    public function resolveAccount(SecurityCheckAuthContextTransfer $securityCheckAuthContextTransfer): ?string
    {
        return $this->loginAttemptKeyBuilder->findAccountKeyHash((string)$securityCheckAuthContextTransfer->getLoginIdentifier())
            ?? $securityCheckAuthContextTransfer->getAccount();
    }
}
