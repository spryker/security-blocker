<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types = 1);

namespace SprykerTest\Client\SecurityBlocker\Helper;

use Generated\Shared\Transfer\RedisConfigurationTransfer;
use LogicException;
use Spryker\Client\SecurityBlocker\Dependency\Client\SecurityBlockerToRedisClientInterface;

/**
 * In-memory stand-in for the Redis connection SecurityBlocker opens directly, which the docker-free
 * host lane has no server for.
 *
 * Implements the counter semantics the blocker actually relies on — `incr` returns the new value and
 * keeps whatever expiry was already set, `get` reports a key as absent once its window has passed —
 * so a suite that drives repeated failed logins still observes a real block. An empty stub would
 * answer "never blocked" to every question and quietly turn each such assertion into a tautology,
 * which is the same class of defect as the empty plugin stacks this lane is being fixed for.
 */
class InMemorySecurityBlockerRedisClient implements SecurityBlockerToRedisClientInterface
{
    protected const string EXPIRE_RESOLUTION_SECONDS = 'EX';

    protected const string FLAG_ONLY_IF_NOT_EXISTS = 'NX';

    protected const int TTL_KEY_NOT_EXISTS = -2;

    protected const int TTL_NO_EXPIRATION = -1;

    protected const string SCRIPT_COMMAND_DECREMENT = 'DECRBY';

    /**
     * @var array<string, array{value: string, expiresAt: int|null}>
     */
    protected array $entries = [];

    protected ?int $currentTime = null;

    /**
     * @var list<string>
     */
    protected array $executedCommands = [];

    /**
     * Test-only control: makes every expiry use this Unix time instead of the system clock, so an expiry assertion
     * does not depend on a second boundary falling between the call under test and the assertion.
     */
    public function setCurrentTime(int $currentTime): void
    {
        $this->currentTime = $currentTime;
    }

    /**
     * @param array<string> $keys
     *
     * @return list<string|null>
     */
    public function mget(string $connectionKey, array $keys): array
    {
        $this->executedCommands[] = __FUNCTION__;

        return array_map(fn (string $key): ?string => $this->readEntry($connectionKey, $key)['value'] ?? null, array_values($keys));
    }

    /**
     * Test-only: the Redis commands executed so far, in order, so a test can pin the number of round trips.
     *
     * @return list<string>
     */
    public function getExecutedCommands(): array
    {
        return $this->executedCommands;
    }

    public function get(string $connectionKey, string $key): ?string
    {
        $this->executedCommands[] = __FUNCTION__;

        $entry = $this->readEntry($connectionKey, $key);

        return $entry === null ? null : $entry['value'];
    }

    public function incr(string $connectionKey, string $key): int
    {
        $this->executedCommands[] = __FUNCTION__;

        $entry = $this->readEntry($connectionKey, $key);
        $value = (int)($entry['value'] ?? 0) + 1;

        // Redis leaves the existing TTL alone on INCR. Refreshing it here would let a blocked actor
        // push their own block window further out with every additional attempt.
        $this->entries[$this->buildEntryKey($connectionKey, $key)] = [
            'value' => (string)$value,
            'expiresAt' => $entry['expiresAt'] ?? null,
        ];

        return $value;
    }

    public function setex(string $connectionKey, string $key, int $seconds, string $value): bool
    {
        $this->executedCommands[] = __FUNCTION__;

        $this->entries[$this->buildEntryKey($connectionKey, $key)] = [
            'value' => $value,
            'expiresAt' => $this->getCurrentTime() + $seconds,
        ];

        return true;
    }

    public function set(
        string $connectionKey,
        string $key,
        string $value,
        ?string $expireResolution = null,
        ?int $expireTTL = null,
        ?string $flag = null
    ): bool {
        $this->executedCommands[] = __FUNCTION__;

        if ($flag === static::FLAG_ONLY_IF_NOT_EXISTS && $this->readEntry($connectionKey, $key) !== null) {
            return false;
        }

        $this->entries[$this->buildEntryKey($connectionKey, $key)] = [
            'value' => $value,
            'expiresAt' => $expireResolution === static::EXPIRE_RESOLUTION_SECONDS && $expireTTL !== null ? $this->getCurrentTime() + $expireTTL : null,
        ];

        return true;
    }

    public function ttl(string $connectionKey, string $key): int
    {
        $this->executedCommands[] = __FUNCTION__;

        $entry = $this->readEntry($connectionKey, $key);

        if ($entry === null) {
            return static::TTL_KEY_NOT_EXISTS;
        }

        if ($entry['expiresAt'] === null) {
            return static::TTL_NO_EXPIRATION;
        }

        return $entry['expiresAt'] - $this->getCurrentTime();
    }

    public function expire(string $connectionKey, string $key, int $seconds): bool
    {
        $this->executedCommands[] = __FUNCTION__;

        $entry = $this->readEntry($connectionKey, $key);

        if ($entry === null) {
            return false;
        }

        $this->entries[$this->buildEntryKey($connectionKey, $key)]['expiresAt'] = $this->getCurrentTime() + $seconds;

        return true;
    }

    /**
     * @param array<string> $keys
     */
    public function del(string $connectionKey, array $keys): int
    {
        $this->executedCommands[] = __FUNCTION__;

        $deletedKeyCount = 0;

        foreach ($keys as $key) {
            if ($this->readEntry($connectionKey, $key) === null) {
                continue;
            }

            unset($this->entries[$this->buildEntryKey($connectionKey, $key)]);
            $deletedKeyCount++;
        }

        return $deletedKeyCount;
    }

    /**
     * @throws \LogicException
     */
    public function eval(string $connectionKey, string $script, int $numKeys, string ...$keysOrArgs): bool
    {
        $this->executedCommands[] = __FUNCTION__;

        if ($numKeys !== 1 || count($keysOrArgs) !== 2 || !str_contains($script, static::SCRIPT_COMMAND_DECREMENT)) {
            throw new LogicException(sprintf('The in-memory Redis client cannot run this script: %s', $script));
        }

        [$key, $amount] = $keysOrArgs;
        $entry = $this->readEntry($connectionKey, $key);

        if ($entry === null) {
            return true;
        }

        $count = (int)$entry['value'] - (int)$amount;

        if ($count <= 0) {
            unset($this->entries[$this->buildEntryKey($connectionKey, $key)]);

            return true;
        }

        $this->entries[$this->buildEntryKey($connectionKey, $key)]['value'] = (string)$count;

        return true;
    }

    /**
     * Test-only probe: lets a test assert that a TTL was set once and never extended.
     */
    public function getExpiresAt(string $connectionKey, string $key): ?int
    {
        return $this->readEntry($connectionKey, $key)['expiresAt'] ?? null;
    }

    /**
     * Test-only probe: lets a test assert which keys a call did (not) write.
     *
     * @return array<string>
     */
    public function getStoredKeys(string $connectionKey): array
    {
        $storedKeys = [];

        foreach (array_keys($this->entries) as $entryKey) {
            [$entryConnectionKey, $key] = explode('|', $entryKey, 2);

            if ($entryConnectionKey !== $connectionKey || $this->readEntry($connectionKey, $key) === null) {
                continue;
            }

            $storedKeys[] = $key;
        }

        return $storedKeys;
    }

    public function setupConnection(string $connectionKey, RedisConfigurationTransfer $configurationTransfer): void
    {
        // Nothing to open: the entries live on this instance for the lifetime of one test.
    }

    /**
     * @return array{value: string, expiresAt: int|null}|null
     */
    protected function readEntry(string $connectionKey, string $key): ?array
    {
        $entryKey = $this->buildEntryKey($connectionKey, $key);
        $entry = $this->entries[$entryKey] ?? null;

        if ($entry === null) {
            return null;
        }

        if ($entry['expiresAt'] !== null && $entry['expiresAt'] <= $this->getCurrentTime()) {
            unset($this->entries[$entryKey]);

            return null;
        }

        return $entry;
    }

    protected function getCurrentTime(): int
    {
        return $this->currentTime ?? time();
    }

    protected function buildEntryKey(string $connectionKey, string $key): string
    {
        return $connectionKey . '|' . $key;
    }
}
