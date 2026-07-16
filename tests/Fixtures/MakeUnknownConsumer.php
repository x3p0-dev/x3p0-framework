<?php

declare(strict_types=1);

namespace X3P0\Framework\Tests\Fixtures;

use X3P0\Framework\Container\Attributes\Make;

/**
 * Points `#[Make]` at an identifier that is neither registered nor buildable,
 * used to verify the failure surfaces cleanly.
 */
final class MakeUnknownConsumer
{
	public function __construct(
		#[Make('X3P0\Framework\Tests\Fixtures\DoesNotExist')]
		public readonly object $thing
	) {}
}
