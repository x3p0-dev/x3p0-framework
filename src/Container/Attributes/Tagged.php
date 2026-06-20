<?php

/**
 * Tagged attribute.
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
 * Injects every service assigned to a container tag into the attributed
 * parameter, mirroring `Container::tagged()`. Pair it with an `iterable` or
 * `array` parameter type:
 *
 *     public function __construct(
 *         #[Tagged('theme.blocks')] iterable $blocks
 *     ) {}
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final class Tagged implements ContextualAttribute
{
	/**
	 * Sets up the object state.
	 */
	public function __construct(private readonly string $tag)
	{}

	/**
	 * Resolves to the array of services assigned to the tag.
	 *
	 * @return array<object>
	 */
	public function resolve(Container $container): array
	{
		return $container->tagged($this->tag);
	}
}
