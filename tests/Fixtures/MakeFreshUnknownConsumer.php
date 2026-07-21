<?php

declare(strict_types=1);

namespace X3P0\Framework\Tests\Fixtures;

use X3P0\Framework\Container\Attributes\Build;

/**
 * Points `#[MakeFresh]` at an identifier that is neither registered nor
 * buildable, used to verify the failure surfaces cleanly.
 */
final class MakeFreshUnknownConsumer
{
	public function __construct(
		#[Build('X3P0\Framework\Tests\Fixtures\DoesNotExist')]
		public readonly object $thing
	) {}
}
