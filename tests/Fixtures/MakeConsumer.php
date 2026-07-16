<?php

declare(strict_types=1);

namespace X3P0\Framework\Tests\Fixtures;

use X3P0\Framework\Container\Attributes\Make;

/**
 * Configures its `Cache` dependency inline with a `#[Make]` override.
 */
final class MakeConsumer
{
	public function __construct(
		#[Make(ConfigurableCache::class, ['ttl' => 3600])]
		public readonly Cache $cache
	) {}
}
