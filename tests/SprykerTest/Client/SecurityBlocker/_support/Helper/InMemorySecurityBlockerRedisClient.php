<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types = 1);

namespace SprykerTest\Client\SecurityBlocker\Helper;

use Generated\Shared\Transfer\RedisConfigurationTransfer;
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
    /**
     * @var array<string, array{value: string, expiresAt: int|null}>
     */
    protected array $entries = [];

    public function get(string $connectionKey, string $key): ?string
    {
        $entry = $this->readEntry($connectionKey, $key);

        return $entry === null ? null : $entry['value'];
    }

    public function incr(string $connectionKey, string $key): int
    {
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
        $this->entries[$this->buildEntryKey($connectionKey, $key)] = [
            'value' => $value,
            'expiresAt' => time() + $seconds,
        ];

        return true;
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

        if ($entry['expiresAt'] !== null && $entry['expiresAt'] <= time()) {
            unset($this->entries[$entryKey]);

            return null;
        }

        return $entry;
    }

    protected function buildEntryKey(string $connectionKey, string $key): string
    {
        return $connectionKey . '|' . $key;
    }
}
