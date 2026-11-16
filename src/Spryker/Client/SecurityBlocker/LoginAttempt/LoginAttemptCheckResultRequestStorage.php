<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Client\SecurityBlocker\LoginAttempt;

use Generated\Shared\Transfer\SecurityCheckAuthContextTransfer;
use Generated\Shared\Transfer\SecurityCheckAuthResponseTransfer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class LoginAttemptCheckResultRequestStorage implements LoginAttemptCheckResultRequestStorageInterface
{
    protected const string REQUEST_ATTRIBUTE_CHECK_RESULTS = '_security_blocker_login_attempt_check_results';

    protected const string KEY_SECURITY_CHECK_AUTH_CONTEXT = 'securityCheckAuthContext';

    protected const string KEY_SECURITY_CHECK_AUTH_RESPONSE = 'securityCheckAuthResponse';

    public function __construct(protected RequestStack $requestStack)
    {
    }

    public function findCheckResult(SecurityCheckAuthContextTransfer $securityCheckAuthContextTransfer): ?SecurityCheckAuthResponseTransfer
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request === null) {
            return null;
        }

        $securityCheckAuthContextData = $securityCheckAuthContextTransfer->toArray();

        foreach ($this->getCheckResults($request) as $checkResult) {
            if ($this->isCheckResultOfContext($checkResult, $securityCheckAuthContextData)) {
                return (new SecurityCheckAuthResponseTransfer())->fromArray($checkResult[static::KEY_SECURITY_CHECK_AUTH_RESPONSE], true);
            }
        }

        return null;
    }

    public function saveCheckResult(
        SecurityCheckAuthContextTransfer $securityCheckAuthContextTransfer,
        SecurityCheckAuthResponseTransfer $securityCheckAuthResponseTransfer
    ): void {
        $request = $this->requestStack->getCurrentRequest();

        if ($request === null) {
            return;
        }

        $checkResults = $this->getCheckResults($request);
        $checkResults[] = [
            static::KEY_SECURITY_CHECK_AUTH_CONTEXT => $securityCheckAuthContextTransfer->toArray(),
            static::KEY_SECURITY_CHECK_AUTH_RESPONSE => $securityCheckAuthResponseTransfer->toArray(),
        ];

        $request->attributes->set(static::REQUEST_ATTRIBUTE_CHECK_RESULTS, $checkResults);
    }

    public function removeCheckResults(): void
    {
        $this->requestStack->getCurrentRequest()?->attributes->remove(static::REQUEST_ATTRIBUTE_CHECK_RESULTS);
    }

    /**
     * @return array<mixed>
     */
    protected function getCheckResults(Request $request): array
    {
        $checkResults = $request->attributes->get(static::REQUEST_ATTRIBUTE_CHECK_RESULTS);

        return is_array($checkResults) ? $checkResults : [];
    }

    /**
     * @param array<string, mixed> $securityCheckAuthContextData
     */
    protected function isCheckResultOfContext(mixed $checkResult, array $securityCheckAuthContextData): bool
    {
        return is_array($checkResult)
            && ($checkResult[static::KEY_SECURITY_CHECK_AUTH_CONTEXT] ?? null) === $securityCheckAuthContextData
            && is_array($checkResult[static::KEY_SECURITY_CHECK_AUTH_RESPONSE] ?? null);
    }
}
