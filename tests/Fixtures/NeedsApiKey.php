<?php

declare(strict_types=1);

namespace X3P0\Framework\Tests\Fixtures;

/**
 * Constructor of built-in-typed parameters the container cannot autowire, so
 * they must be supplied by a contextual `whenNeedsParam()` binding (or a
 * `make()` override).
 */
final class NeedsApiKey
{
	public function __construct(
		public readonly string $apiKey,
		public readonly int $timeout
	) {}
}
