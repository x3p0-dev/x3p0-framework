<?php

/**
 * Defer attribute.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2025, Justin Tadlock
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 * @link      https://github.com/x3p0-dev/x3p0-framework
 */

declare(strict_types=1);

namespace X3P0\Framework\Container\Attributes;

use Attribute;
use Closure;
use X3P0\Framework\Container\Container;

/**
 * Defers resolution of a service, mirroring `Container::defer()`. Instead of
 * building the service when the attributed class is constructed, it injects a
 * closure that resolves the service when called. Pair it with a `Closure`
 * parameter type:
 *
 *     public function __construct(
 *         #[Defer(Report::class)] Closure $makeReport
 *     ) {}
 *
 * Calling the closure resolves the service from the container, optionally
 * passing build parameters. Whether each call yields a fresh or shared instance
 * follows the binding's lifetime, exactly as `Container::defer()` does.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final class Defer implements ContextualAttribute
{
	/**
	 * Stores the identifier whose resolution is deferred.
	 */
	public function __construct(private readonly string $abstract)
	{}

	/**
	 * Resolves to a closure that defers resolution of the identifier.
	 */
	public function resolve(Container $container): Closure
	{
		return $container->defer($this->abstract);
	}
}
