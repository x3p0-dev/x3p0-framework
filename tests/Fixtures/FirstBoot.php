<?php

declare(strict_types=1);

namespace X3P0\Framework\Tests\Fixtures;

use X3P0\Framework\Contracts\Bootable;

final class FirstBoot implements Bootable
{
	public function __construct(private readonly Recorder $recorder)
	{}

	public function boot(): void
	{
		$this->recorder->events[] = 'first';
	}
}
