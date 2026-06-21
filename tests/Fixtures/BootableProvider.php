<?php

declare(strict_types=1);

namespace X3P0\Framework\Tests\Fixtures;

use X3P0\Framework\Core\ServiceProvider;

final class BootableProvider extends ServiceProvider
{
	protected const BOOTABLE = [
		FirstBoot::class,
		SecondBoot::class
	];
}
