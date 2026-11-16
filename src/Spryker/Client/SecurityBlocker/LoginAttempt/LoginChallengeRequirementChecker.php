<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Client\SecurityBlocker\LoginAttempt;

use Exception;
use Generated\Shared\Transfer\SecurityCheckAuthContextTransfer;
use Psr\Log\LoggerInterface;

class LoginChallengeRequirementChecker implements LoginChallengeRequirementCheckerInterface
{
    protected const string LOG_MESSAGE_LOGIN_ATTEMPT_NOT_READABLE = 'The failed login attempts of type "%s" could not be read, so a CAPTCHA is required.';

    public function __construct(
        protected LoginAttemptCheckerInterface $loginAttemptChecker,
        protected LoggerInterface $logger
    ) {
    }

    public function isLoginChallengeRequired(SecurityCheckAuthContextTransfer $securityCheckAuthContextTransfer): bool
    {
        try {
            return (bool)$this->loginAttemptChecker->checkLoginAttempt($securityCheckAuthContextTransfer)->getIsChallengeRequired();
        } catch (Exception $exception) {
            $this->logger->error(
                sprintf(static::LOG_MESSAGE_LOGIN_ATTEMPT_NOT_READABLE, $securityCheckAuthContextTransfer->getType()),
                ['exception' => $exception],
            );

            return true;
        }
    }
}
