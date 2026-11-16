<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Client\SecurityBlocker;

use Codeception\Actor;
use Generated\Shared\Transfer\SecurityBlockerLoginRealmConfigurationTransfer;
use Generated\Shared\Transfer\SecurityCheckAuthContextTransfer;
use Generated\Shared\Transfer\SecurityCheckAuthResponseTransfer;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Spryker\Client\SecurityBlocker\LoginAttempt\LoginChallengeRequirementChecker;
use Spryker\Client\SecurityBlocker\LoginAttempt\LoginChallengeRequirementCheckerInterface;
use Spryker\Client\SecurityBlocker\Resolver\LoginRealmConfigurationResolver;
use Spryker\Client\SecurityBlocker\SecurityBlockerClientInterface;
use Spryker\Client\SecurityBlocker\SecurityBlockerConfig;
use Spryker\Client\SecurityBlocker\SecurityBlockerDependencyProvider;
use Spryker\Client\SecurityBlocker\SecurityBlockerFactory;
use Spryker\Client\SecurityBlockerExtension\Dependency\Plugin\SecurityBlockerLoginRealmConfigurationExpanderPluginInterface;
use Spryker\Shared\SecurityBlocker\SecurityBlockerConstants;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Inherited Methods
 *
 * @method void wantToTest($text)
 * @method void wantTo($text)
 * @method void execute($callable)
 * @method void expectTo($prediction)
 * @method void expect($prediction)
 * @method void amGoingTo($argumentation)
 * @method void am($role)
 * @method void lookForwardTo($achieveValue)
 * @method void comment($description)
 * @method void pause()
 *
 * @SuppressWarnings(PHPMD)
 */
class SecurityBlockerClientTester extends Actor
{
    use _generated\SecurityBlockerClientTesterActions;

    protected const string LOGIN_CHECK_PATH = '/login_check';

    public const int IP_NUMBER_OF_ATTEMPTS = 10;

    public const int IP_BLOCK_FOR = 300;

    /**
     * Differs from the module default, so a test whose login realm configuration is not wired fails.
     */
    public const int ACCOUNT_NUMBER_OF_ATTEMPTS = 7;

    public const int ACCOUNT_TTL = 900;

    public const int ACCOUNT_BLOCK_FOR = 360;

    public const int ACCOUNT_CHALLENGE_NUMBER_OF_ATTEMPTS = 3;

    public const int IP_CHALLENGE_NUMBER_OF_ATTEMPTS = 5;

    public const int GLOBAL_CHALLENGE_NUMBER_OF_ATTEMPTS = 100;

    public const int GLOBAL_CHALLENGE_TTL = 300;

    public const int CURRENT_TIME = 1800000000;

    /**
     * Shorter than every lock, so the later attempts still hit the started lock.
     */
    public const int LATER_ATTEMPTS_DELAY = 60;

    public const string REALM = 'test-realm';

    public const string ACCOUNT_STORAGE_KEY_PATTERN = 'kv:login-account:{%s}:%s';

    public const string GLOBAL_STORAGE_KEY_PATTERN = 'kv:login-global:{%s}';

    public const string IP_STORAGE_KEY_PATTERN = 'kv:login-ip:{%s}:%s';

    public const string OTHER_REALM = 'other-test-realm';

    /**
     * @var array<string, \Generated\Shared\Transfer\SecurityBlockerLoginRealmConfigurationTransfer>
     */
    protected array $securityBlockerLoginRealmConfigurationTransfers = [];

    /**
     * Pins every threshold, so no test inherits the limits of the environment it runs in.
     */
    public function haveLoginProtectionThresholds(): void
    {
        $this->setConfig(SecurityBlockerConstants::SECURITY_BLOCKER_BLOCKING_NUMBER_OF_ATTEMPTS, static::IP_NUMBER_OF_ATTEMPTS);
        $this->setConfig(SecurityBlockerConstants::SECURITY_BLOCKER_BLOCKING_TTL, 600);
        $this->setConfig(SecurityBlockerConstants::SECURITY_BLOCKER_BLOCK_FOR, static::IP_BLOCK_FOR);

        $this->haveLoginRealmConfiguration(static::REALM);
        $this->haveLoginRealmConfiguration(static::OTHER_REALM);
    }

    /**
     * @param array<string, int> $seed
     */
    public function haveLoginRealmConfiguration(string $realm, array $seed = []): SecurityBlockerLoginRealmConfigurationTransfer
    {
        $securityBlockerLoginRealmConfigurationTransfer = (new SecurityBlockerLoginRealmConfigurationTransfer())->fromArray($seed + [
            SecurityBlockerLoginRealmConfigurationTransfer::REALM => $realm,
            SecurityBlockerLoginRealmConfigurationTransfer::ACCOUNT_NUMBER_OF_ATTEMPTS => static::ACCOUNT_NUMBER_OF_ATTEMPTS,
            SecurityBlockerLoginRealmConfigurationTransfer::ACCOUNT_TTL => static::ACCOUNT_TTL,
            SecurityBlockerLoginRealmConfigurationTransfer::ACCOUNT_BLOCK_FOR => static::ACCOUNT_BLOCK_FOR,
            SecurityBlockerLoginRealmConfigurationTransfer::ACCOUNT_CHALLENGE_NUMBER_OF_ATTEMPTS => static::ACCOUNT_CHALLENGE_NUMBER_OF_ATTEMPTS,
            SecurityBlockerLoginRealmConfigurationTransfer::IP_CHALLENGE_NUMBER_OF_ATTEMPTS => static::IP_CHALLENGE_NUMBER_OF_ATTEMPTS,
            SecurityBlockerLoginRealmConfigurationTransfer::GLOBAL_CHALLENGE_NUMBER_OF_ATTEMPTS => static::GLOBAL_CHALLENGE_NUMBER_OF_ATTEMPTS,
            SecurityBlockerLoginRealmConfigurationTransfer::GLOBAL_TTL => static::GLOBAL_CHALLENGE_TTL,
        ]);
        $this->securityBlockerLoginRealmConfigurationTransfers[$realm] = $securityBlockerLoginRealmConfigurationTransfer;

        $this->setDependency(
            SecurityBlockerDependencyProvider::PLUGINS_SECURITY_BLOCKER_LOGIN_REALM_CONFIGURATION_EXPANDER,
            [$this->createLoginRealmConfigurationExpanderPlugin($this->securityBlockerLoginRealmConfigurationTransfers)],
            SecurityBlockerFactory::class,
        );

        return $securityBlockerLoginRealmConfigurationTransfer;
    }

    /**
     * @param list<\Spryker\Client\SecurityBlockerExtension\Dependency\Plugin\SecurityBlockerLoginChallengeStrategyPluginInterface> $securityBlockerLoginChallengeStrategyPlugins
     */
    public function haveLoginChallengeStrategyPlugins(array $securityBlockerLoginChallengeStrategyPlugins): void
    {
        $this->setDependency(
            SecurityBlockerDependencyProvider::PLUGINS_SECURITY_BLOCKER_LOGIN_CHALLENGE_STRATEGY,
            $securityBlockerLoginChallengeStrategyPlugins,
            SecurityBlockerFactory::class,
        );
    }

    public function createLoginRealmConfigurationResolver(?LoggerInterface $logger = null): LoginRealmConfigurationResolver
    {
        return new LoginRealmConfigurationResolver(
            [$this->createLoginRealmConfigurationExpanderPlugin($this->securityBlockerLoginRealmConfigurationTransfers)],
            new SecurityBlockerConfig(),
            $logger ?? new NullLogger(),
        );
    }

    /**
     * @param array<string, \Generated\Shared\Transfer\SecurityBlockerLoginRealmConfigurationTransfer> $securityBlockerLoginRealmConfigurationTransfers
     */
    public function createLoginRealmConfigurationExpanderPlugin(
        array $securityBlockerLoginRealmConfigurationTransfers
    ): SecurityBlockerLoginRealmConfigurationExpanderPluginInterface {
        return new class ($securityBlockerLoginRealmConfigurationTransfers) implements SecurityBlockerLoginRealmConfigurationExpanderPluginInterface {
            /**
             * @param array<string, \Generated\Shared\Transfer\SecurityBlockerLoginRealmConfigurationTransfer> $securityBlockerLoginRealmConfigurationTransfers
             */
            public function __construct(protected array $securityBlockerLoginRealmConfigurationTransfers)
            {
            }

            /**
             * @param array<string, \Generated\Shared\Transfer\SecurityBlockerLoginRealmConfigurationTransfer> $securityBlockerLoginRealmConfigurationTransfers
             *
             * @return array<string, \Generated\Shared\Transfer\SecurityBlockerLoginRealmConfigurationTransfer>
             */
            public function expand(array $securityBlockerLoginRealmConfigurationTransfers): array
            {
                return array_merge($securityBlockerLoginRealmConfigurationTransfers, $this->securityBlockerLoginRealmConfigurationTransfers);
            }
        };
    }

    public function createLoginAttemptContext(string $realm, string $type, string $ip, ?string $loginIdentifier): SecurityCheckAuthContextTransfer
    {
        return (new SecurityCheckAuthContextTransfer())
            ->setRealm($realm)
            ->setType($type)
            ->setIp($ip)
            ->setLoginIdentifier($loginIdentifier);
    }

    public function reserveLoginAttempts(SecurityCheckAuthContextTransfer $securityCheckAuthContextTransfer, int $times): SecurityCheckAuthResponseTransfer
    {
        $securityCheckAuthResponseTransfer = new SecurityCheckAuthResponseTransfer();

        for ($i = 0; $i < $times; $i++) {
            $securityCheckAuthResponseTransfer = $this->getSecurityBlockerClient()->reserveLoginAttempt($securityCheckAuthContextTransfer);
        }

        return $securityCheckAuthResponseTransfer;
    }

    public function registerFailedLoginAttempts(
        SecurityCheckAuthContextTransfer $securityCheckAuthContextTransfer,
        int $times
    ): SecurityCheckAuthResponseTransfer {
        $securityCheckAuthResponseTransfer = new SecurityCheckAuthResponseTransfer();

        for ($i = 0; $i < $times; $i++) {
            $securityCheckAuthResponseTransfer = $this->getSecurityBlockerClient()->registerFailedLoginAttempt($securityCheckAuthContextTransfer);
        }

        return $securityCheckAuthResponseTransfer;
    }

    /**
     * Builds the checker with the client's real login attempt checker, so a test can assert the error it logs.
     */
    public function createLoginChallengeRequirementChecker(LoggerInterface $logger): LoginChallengeRequirementCheckerInterface
    {
        return new LoginChallengeRequirementChecker((new SecurityBlockerFactory())->createLoginAttemptChecker(), $logger);
    }

    /**
     * Gives the client the request stack of an application that is serving a request, so it can keep its checks on it.
     */
    public function haveCurrentRequest(): Request
    {
        $request = Request::create(static::LOGIN_CHECK_PATH, Request::METHOD_POST);
        $requestStack = new RequestStack();
        $requestStack->push($request);
        $this->haveRequestStack($requestStack);

        return $request;
    }

    /**
     * The console and the queue serve no request.
     */
    public function haveNoCurrentRequest(): void
    {
        $this->haveRequestStack(new RequestStack());
    }

    public function getSecurityBlockerClient(): SecurityBlockerClientInterface
    {
        return $this->getLocator()->securityBlocker()->client();
    }

    /**
     * Scoped to the factory like the Redis double of `SecurityBlockerRedisHelper`: a scoped override hides the unscoped ones.
     */
    protected function haveRequestStack(RequestStack $requestStack): void
    {
        $this->setDependency(SecurityBlockerDependencyProvider::SERVICE_REQUEST_STACK, $requestStack, SecurityBlockerFactory::class);
    }
}
