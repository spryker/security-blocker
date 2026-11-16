<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Client\SecurityBlocker\Normalizer;

interface LoginIdentifierNormalizerInterface
{
    public function normalize(string $loginIdentifier): string;
}
