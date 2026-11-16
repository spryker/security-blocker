<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Client\SecurityBlocker\LoginAttempt;

use Generated\Shared\Transfer\SecurityCheckAuthContextTransfer;

interface LoginChallengeRequirementCheckerInterface
{
    public function isLoginChallengeRequired(SecurityCheckAuthContextTransfer $securityCheckAuthContextTransfer): bool;
}
