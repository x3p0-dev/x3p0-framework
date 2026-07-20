<?php

/**
 * Tagged with attribute.
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
 * Injects every service assigned to a container tag and with an assigned
 * tagged attribute value into the attributed parameter, mirroring
 * `Container::taggedWith()`. Pair it with an `iterable` or `array` parameter type:
 *
 *     public function __construct(
 *         #[TaggedWith('channels', 'slug')] iterable $channels
 *     ) {}
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final class TaggedWith implements ContextualAttribute
{
	/**
	 * Stores the tag and attributes to resolve.
	 */
	public function __construct(
		private readonly string $tag,
		private readonly string $attribute
	) {}

	/**
	 * Returns a map from a chosen attribute's value to its abstract, for
	 * every member of `$tag` that was given that attribute.
	 */
	public function resolve(Container $container): array
	{
		return $container->taggedWith($this->tag, $this->attribute);
	}
}
