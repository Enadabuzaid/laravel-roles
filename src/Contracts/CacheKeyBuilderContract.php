<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Contracts;

/**
 * CacheKeyBuilderContract
 *
 * Responsible for building contextual cache keys that prevent
 * cache collisions across tenants, guards, and locales.
 *
 * @package Enadstack\LaravelRoles\Contracts
 */
interface CacheKeyBuilderContract
{
    /**
     * Build a complete cache key with all context segments.
     *
     * The key will include: tenant scope, guard, locale, and the base key.
     * Format: laravel_roles:{guard}:{tenant}:{locale}:{baseKey}
     *
     * @param string $baseKey The base cache key
     * @return string The complete, contextual cache key
     */
    public function key(string $baseKey): string;

    /**
     * Get cache tags for the current context.
     *
     * Returns tags that can be used for cache invalidation.
     * Tags may include: 'laravel_roles', tenant-specific tags, guard tags.
     *
     * @return array<string> Array of cache tags
     */
    public function tags(): array;

    /**
     * Build a key with custom context overrides.
     *
     * @param string $baseKey
     * @param array{guard?: string, locale?: string, tenantId?: int|string|null} $context
     * @return string
     */
    public function keyWithContext(string $baseKey, array $context = []): string;

    /**
     * Get the current locale for cache keys.
     *
     * @return string
     */
    public function locale(): string;

    /**
     * Get the cache TTL in seconds.
     *
     * @return int
     */
    public function ttl(): int;

    /**
     * Check if caching is enabled.
     *
     * @return bool
     */
    public function isEnabled(): bool;

    /**
     * Check if the cache store supports tags.
     *
     * @return bool
     */
    public function supportsTags(): bool;
}
