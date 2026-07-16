<?php

declare(strict_types=1);

namespace X3P0\Framework\Tests\Fixtures;

/**
 * A cache whose TTL is a constructor scalar. The default lets it be bound as a
 * shared instance, while `#[Make]` can build a fresh one with an overridden
 * TTL.
 */
final class ConfigurableCache implements Cache
{
	public function __construct(public readonly int $ttl = 60)
	{}
}
