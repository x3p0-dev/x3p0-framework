<?php

/**
 * Tagged abstracts with attribute.
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
 * Injects a lookup map for a tagged group of services, keyed by one of the
 * attributes each member was tagged with, rather than the resolved services
 * themselves. This defers building any member until its abstract identifier
 * is looked up and passed to `make()` (or similar), which is what makes it
 * suited to a large tag group where a consumer only ever needs one member
 * at a time — e.g. resolving a single markup type by slug:
 *
 *     public function __construct(
 *         #[TaggedMap('channel', 'slug')] private readonly array $channels
 *     ) {}
 *
 *     $instance = $this->container->make($this->channels[$slug]);
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final class TaggedAbstractsWith implements ContextualAttribute
{
	/**
	 * Stores the tag to map and the attribute whose value keys the map.
	 */
	public function __construct(
		private readonly string $tag,
		private readonly string $attribute
	) {}

	/**
	 * Returns a map from a chosen attribute's value to its abstract, for
	 * every member of `$tag` that was given that attribute.
	 *
	 * @return array<mixed, string>
	 */
	public function resolve(Container $container): array
	{
		return $container->taggedAbstractsWith($this->tag, $this->attribute);
	}
}
