<?php

/**
 * Make attribute.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2025, Justin Tadlock
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 * @link      https://github.com/x3p0-dev/x3p0-framework
 */

declare(strict_types=1);

namespace X3P0\Framework\Container\Attributes;

use Attribute;
use X3P0\Framework\Container\Container;

/**
 * Builds a service with inline constructor overrides, mirroring
 * `Container::make()`. Use it to configure a dependency's construction right at
 * the point of use, when the configuration is a one-off that does not warrant a
 * named binding:
 *
 *     public function __construct(
 *         #[Make(TransientCache::class, ['ttl' => 3600])] Cache $cache
 *     ) {}
 *
 * The overrides are attribute arguments, so they are limited to compile-time
 * constants — scalars, arrays, enums, and class constants. A value that must be
 * resolved or computed (another service, a closure) belongs in a contextual
 * binding instead.
 *
 * Because a parameterized resolution is never cached, a `#[Make]` with overrides
 * yields a fresh, unshared instance. With no overrides it is equivalent to
 * `#[Get]` and returns the shared instance, so reach for `#[Get]` in that case.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final class Make implements ContextualAttribute
{
	/**
	 * Stores the identifier to build and the constructor overrides to pass.
	 *
	 * @param array<string, mixed> $parameters
	 */
	public function __construct(
		private readonly string $abstract,
		private readonly array $parameters = []
	) {}

	/**
	 * Builds the identifier from the container with the stored overrides.
	 */
	public function resolve(Container $container): object
	{
		return $container->make($this->abstract, $this->parameters);
	}
}
