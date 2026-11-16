<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Client\SecurityBlocker\LoginAttempt;

use Generated\Shared\Transfer\SecurityCheckAuthContextTransfer;
use Generated\Shared\Transfer\SecurityCheckAuthResponseTransfer;

interface LoginAttemptCheckResultRequestStorageInterface
{
    public function findCheckResult(SecurityCheckAuthContextTransfer $securityCheckAuthContextTransfer): ?SecurityCheckAuthResponseTransfer;

    public function saveCheckResult(
        SecurityCheckAuthContextTransfer $securityCheckAuthContextTransfer,
        SecurityCheckAuthResponseTransfer $securityCheckAuthResponseTransfer
    ): void;

    public function removeCheckResults(): void;
}
