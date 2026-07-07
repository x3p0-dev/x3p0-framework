<?php

declare(strict_types=1);

namespace X3P0\Framework\Tests\Fixtures;

final class RequiresValueObject
{
	public function __construct(public readonly ValueObject $value)
	{}
}
