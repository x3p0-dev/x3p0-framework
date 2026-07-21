<?php

/**
 * Build attribute.
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
 * Builds a fresh, unshared service, mirroring `InstanceResolver::build()`. The
 * built instance bypasses any cached singleton — the shared instance (if any)
 * is left in place and a newly built one is injected. Use it to give a single
 * consumer its own private copy of a service, optionally configured with inline
 * constructor overrides:
 *
 *     public function __construct(
 *         #[Build(TransientCache::class, ['ttl' => 3600])] Cache $cache
 *     ) {}
 *
 * The overrides are attribute arguments, so they are limited to compile-time
 * constants — scalars, arrays, enums, and class constants. A value that must be
 * resolved or computed (another service, a closure) belongs in a contextual
 * binding instead.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final class Build implements ContextualAttribute
{
	/**
	 * Stores the identifier to build and the constructor overrides to pass.
	 *
	 * @param array<string, mixed> $parameters
	 */
	public function __construct(
		private readonly string $abstract,
		private readonly array  $parameters = []
	) {}

	/**
	 * Builds a fresh instance of the identifier with the stored overrides.
	 */
	public function resolve(Container $container): object
	{
		return $container->build($this->abstract, $this->parameters);
	}
}
