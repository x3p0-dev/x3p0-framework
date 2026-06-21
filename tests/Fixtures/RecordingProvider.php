<?php

declare(strict_types=1);

namespace X3P0\Framework\Tests\Fixtures;

use ReflectionClass;
use X3P0\Framework\Core\ServiceProvider;

abstract class RecordingProvider extends ServiceProvider
{
	public function register(): void
	{
		$this->record('register');
	}

	public function boot(): void
	{
		$this->record('boot');
	}

	private function record(string $phase): void
	{
		$name = (new ReflectionClass($this))->getShortName();

		$this->container->get(Recorder::class)->events[] = "{$name}:{$phase}";
	}
}
