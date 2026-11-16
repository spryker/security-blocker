<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Client\SecurityBlocker\Resolver;

use Generated\Shared\Transfer\SecurityBlockerLoginRealmConfigurationTransfer;

interface LoginRealmConfigurationResolverInterface
{
    public function resolve(string $realm): SecurityBlockerLoginRealmConfigurationTransfer;
}
