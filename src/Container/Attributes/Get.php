<?php

/**
 * Get attribute.
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
 * Resolves the attributed parameter from the container by identifier, mirroring
 * `Container::get()`. The identifier may be a class name or any string key the
 * container can resolve:
 *
 *     public function __construct(
 *         #[Get('app.paths')]      array $paths,
 *         #[Get(FileCache::class)] Cache $cache
 *     ) {}
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final class Get implements ContextualAttribute
{
	/**
	 * Sets up the object state.
	 */
	public function __construct(private readonly string $abstract)
	{}

	/**
	 * Resolves the identifier from the container.
	 */
	public function resolve(Container $container): mixed
	{
		return $container->get($this->abstract);
	}
}
