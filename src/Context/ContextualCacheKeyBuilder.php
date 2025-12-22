<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Context;

use Enadstack\LaravelRoles\Contracts\CacheKeyBuilderContract;
use Enadstack\LaravelRoles\Contracts\TenantContextContract;
use Enadstack\LaravelRoles\Contracts\GuardResolverContract;
use Illuminate\Support\Facades\Cache;

/**
 * ContextualCacheKeyBuilder
 *
 * Builds cache keys that are contextual to tenant, guard, and locale.
 * Prevents cache key collisions across tenants, guards, and locales.
 *
 * Cache key format: laravel_roles:{guard}:{tenant}:{locale}:{baseKey}
 *
 * @package Enadstack\LaravelRoles\Context
 */
class ContextualCacheKeyBuilder implements CacheKeyBuilderContract
{
    /**
     * Prefix for all cache keys.
     */
    protected const PREFIX = 'laravel_roles';

    /**
     * Tenant context instance.
     *
     * @var TenantContextContract
     */
    protected TenantContextContract $tenantContext;

    /**
     * Guard resolver instance.
     *
     * @var GuardResolverContract
     */
    protected GuardResolverContract $guardResolver;

    /**
     * Create a new cache key builder instance.
     *
     * @param TenantContextContract $tenantContext
     * @param GuardResolverContract $guardResolver
     */
    public function __construct(
        TenantContextContract $tenantContext,
        GuardResolverContract $guardResolver
    ) {
        $this->tenantContext = $tenantContext;
        $this->guardResolver = $guardResolver;
    }

    /**
     * {@inheritdoc}
     */
    public function key(string $baseKey): string
    {
        $segments = [
            self::PREFIX,
            $this->guardResolver->guard(),
            $this->tenantContext->scopeKey(),
            $this->locale(),
            $baseKey,
        ];

        return implode(':', $segments);
    }

    /**
     * {@inheritdoc}
     */
    public function tags(): array
    {
        $tags = [self::PREFIX];

        // Add tenant-specific tag
        $scopeKey = $this->tenantContext->scopeKey();
        if ($scopeKey !== 'global') {
            $tags[] = self::PREFIX . ':' . $scopeKey;
        }

        // Add guard-specific tag
        $tags[] = self::PREFIX . ':guard:' . $this->guardResolver->guard();

        return $tags;
    }

    /**
     * {@inheritdoc}
     */
    public function keyWithContext(string $baseKey, array $context = []): string
    {
        $guard = $context['guard'] ?? $this->guardResolver->guard();
        $locale = $context['locale'] ?? $this->locale();

        // Determine tenant scope key
        $tenantScopeKey = $this->tenantContext->scopeKey();
        if (array_key_exists('tenantId', $context)) {
            $tenantId = $context['tenantId'];
            if ($tenantId === null) {
                $tenantScopeKey = 'global';
            } else {
                $tenantScopeKey = 'tenant_' . $tenantId;
            }
        }

        $segments = [
            self::PREFIX,
            $guard,
            $tenantScopeKey,
            $locale,
            $baseKey,
        ];

        return implode(':', $segments);
    }

    /**
     * {@inheritdoc}
     */
    public function locale(): string
    {
        // Check if i18n is enabled
        if (!config('roles.i18n.enabled', false)) {
            return 'default';
        }

        return app()->getLocale() ?? config('roles.i18n.default', 'en');
    }

    /**
     * {@inheritdoc}
     */
    public function ttl(): int
    {
        return (int) config('roles.cache.ttl', 300);
    }

    /**
     * {@inheritdoc}
     */
    public function isEnabled(): bool
    {
        return (bool) config('roles.cache.enabled', true);
    }

    /**
     * {@inheritdoc}
     */
    public function supportsTags(): bool
    {
        $store = Cache::getStore();
        return method_exists($store, 'tags');
    }

    /**
     * Get a cache instance with appropriate tags.
     *
     * @return \Illuminate\Cache\Repository|\Illuminate\Contracts\Cache\Repository
     */
    public function cache()
    {
        if ($this->supportsTags()) {
            return Cache::tags($this->tags());
        }

        return Cache::store();
    }

    /**
     * Remember a value in cache with the current context.
     *
     * @param string $baseKey
     * @param callable $callback
     * @param int|null $ttl Optional TTL override
     * @return mixed
     */
    public function remember(string $baseKey, callable $callback, ?int $ttl = null)
    {
        if (!$this->isEnabled()) {
            return $callback();
        }

        $key = $this->key($baseKey);
        $seconds = $ttl ?? $this->ttl();

        return $this->cache()->remember($key, $seconds, $callback);
    }

    /**
     * Forget a cache key.
     *
     * @param string $baseKey
     * @return bool
     */
    public function forget(string $baseKey): bool
    {
        return $this->cache()->forget($this->key($baseKey));
    }

    /**
     * Flush all caches with the current tags.
     *
     * @return void
     */
    public function flush(): void
    {
        if ($this->supportsTags()) {
            $this->cache()->flush();
        } else {
            // Without tags, we need to forget individual keys
            $keys = config('roles.cache.keys', []);
            foreach ($keys as $key) {
                $this->forget($key);
            }
        }
    }

    /**
     * Flush all package caches.
     *
     * @return void
     */
    public function flushAll(): void
    {
        if ($this->supportsTags()) {
            Cache::tags([self::PREFIX])->flush();
        } else {
            $keys = config('roles.cache.keys', []);
            foreach ($keys as $key) {
                Cache::forget($key);
            }
        }
    }
}
