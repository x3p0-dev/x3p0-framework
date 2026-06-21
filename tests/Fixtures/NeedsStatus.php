<?php

declare(strict_types=1);

namespace X3P0\Framework\Tests\Fixtures;

final class NeedsStatus
{
	public function __construct(public readonly Status $status)
	{}
}
