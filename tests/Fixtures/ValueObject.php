<?php

declare(strict_types=1);

namespace X3P0\Framework\Tests\Fixtures;

/**
 * A value object that mirrors a class like `WP_Post`: it exists, so
 * class_exists() is true, but its required, untyped constructor parameter
 * means the container cannot autowire it.
 */
final class ValueObject
{
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
	public function __construct($source)
	{}
}
