<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Client\SecurityBlocker;

use Generated\Shared\Transfer\SecurityCheckAttemptContextTransfer;
use Generated\Shared\Transfer\SecurityCheckAttemptResponseTransfer;
use Generated\Shared\Transfer\SecurityCheckAuthContextTransfer;
use Generated\Shared\Transfer\SecurityCheckAuthResponseTransfer;
use Spryker\Client\Kernel\AbstractClient;

/**
 * @method \Spryker\Client\SecurityBlocker\SecurityBlockerFactory getFactory()
 */
class SecurityBlockerClient extends AbstractClient implements SecurityBlockerClientInterface
{
    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\SecurityCheckAuthContextTransfer $securityCheckAuthContextTransfer
     *
     * @throws \Spryker\Client\SecurityBlocker\Exception\SecurityBlockerException
     *
     * @return \Generated\Shared\Transfer\SecurityCheckAuthResponseTransfer
     */
    public function incrementLoginAttemptCount(SecurityCheckAuthContextTransfer $securityCheckAuthContextTransfer): SecurityCheckAuthResponseTransfer
    {
        return $this->getFactory()
            ->createSecurityBlockerStorageWriter()
            ->incrementLoginAttemptCount($securityCheckAuthContextTransfer);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\SecurityCheckAuthContextTransfer $securityCheckAuthContextTransfer
     *
     * @return \Generated\Shared\Transfer\SecurityCheckAuthResponseTransfer
     */
    public function isAccountBlocked(SecurityCheckAuthContextTransfer $securityCheckAuthContextTransfer): SecurityCheckAuthResponseTransfer
    {
        return $this->getFactory()
            ->createSecurityBlockerStorageChecker()
            ->isAccountBlocked($securityCheckAuthContextTransfer);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @throws \Spryker\Client\SecurityBlocker\Exception\SecurityBlockerException
     */
    public function checkLoginAttempt(SecurityCheckAuthContextTransfer $securityCheckAuthContextTransfer): SecurityCheckAuthResponseTransfer
    {
        return $this->getFactory()
            ->createLoginAttemptChecker()
            ->checkLoginAttempt($securityCheckAuthContextTransfer);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     */
    public function isLoginChallengeRequired(SecurityCheckAuthContextTransfer $securityCheckAuthContextTransfer): bool
    {
        return $this->getFactory()
            ->createLoginChallengeRequirementChecker()
            ->isLoginChallengeRequired($securityCheckAuthContextTransfer);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @throws \Spryker\Client\SecurityBlocker\Exception\SecurityBlockerException
     */
    public function reserveLoginAttempt(SecurityCheckAuthContextTransfer $securityCheckAuthContextTransfer): SecurityCheckAuthResponseTransfer
    {
        return $this->getFactory()
            ->createLoginAttemptReserver()
            ->reserveLoginAttempt($securityCheckAuthContextTransfer);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @throws \Spryker\Client\SecurityBlocker\Exception\SecurityBlockerException
     */
    public function registerFailedLoginAttempt(SecurityCheckAuthContextTransfer $securityCheckAuthContextTransfer): SecurityCheckAuthResponseTransfer
    {
        return $this->getFactory()
            ->createLoginAttemptRegistrar()
            ->registerFailedLoginAttempt($securityCheckAuthContextTransfer);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @throws \Spryker\Client\SecurityBlocker\Exception\SecurityBlockerException
     */
    public function registerSuccessfulLoginAttempt(SecurityCheckAuthContextTransfer $securityCheckAuthContextTransfer): SecurityCheckAuthResponseTransfer
    {
        return $this->getFactory()
            ->createLoginAttemptRegistrar()
            ->registerSuccessfulLoginAttempt($securityCheckAuthContextTransfer);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @throws \Spryker\Client\SecurityBlocker\Exception\SecurityBlockerException
     */
    public function incrementAttemptCount(SecurityCheckAttemptContextTransfer $securityCheckAttemptContextTransfer): SecurityCheckAttemptResponseTransfer
    {
        return $this->getFactory()
            ->createSecurityBlockerStorageWriter()
            ->incrementAttemptCount($securityCheckAttemptContextTransfer);
    }
}
