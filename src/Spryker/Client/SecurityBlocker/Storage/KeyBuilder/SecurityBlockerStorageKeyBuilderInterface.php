<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Client\SecurityBlocker\Storage\KeyBuilder;

use Generated\Shared\Transfer\SecurityCheckAuthContextTransfer;

interface SecurityBlockerStorageKeyBuilderInterface
{
    public function getStorageKey(SecurityCheckAuthContextTransfer $securityCheckAuthContextTransfer): string;
}
