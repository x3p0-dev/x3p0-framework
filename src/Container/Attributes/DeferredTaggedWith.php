<?php

/**
 * Deferred tagged with attribute.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2025, Justin Tadlock
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 * @link      https://github.com/x3p0-dev/x3p0-framework
 */

declare(strict_types=1);

namespace X3P0\Framework\Container\Attributes;

use Closure;
use Attribute;
use X3P0\Framework\Container\Container;

/**
 * Injects the services assigned to a container tag as an array of deferred
 * resolvers, keyed by a chosen attribute's value rather than by abstract —
 * combining `Container::taggedMap()` with `Container::defer()`. Like
 * `TaggedWith`, this lets a consumer look up a single tagged service by a
 * value it already knows (a slug, say) instead of an abstract identifier;
 * like `DeferredTagged`, the lookup yields a closure rather than a built
 * instance, so nothing is constructed until the consumer calls it. This
 * combination suits a large tag group where a consumer resolves at most
 * one or two members per request, keyed by something other than the class
 * name — e.g. resolving a single markup type by slug:
 *
 *     public function __construct(
 *         #[DeferredTaggedMap('channel', 'slug')] private readonly array $channels
 *     ) {}
 *
 *     $markup = ($this->channels[$slug])();
 *
 * Whether each call yields a fresh or shared instance follows the binding's
 * lifetime, exactly as `Container::defer()` does.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final class DeferredTaggedWith implements ContextualAttribute
{
	/**
	 * Stores the tag to map and the attribute whose value keys the map.
	 */
	public function __construct(
		private readonly string $tag,
		private readonly string $attribute
	) {}

	/**
	 * Resolves to an array of closures, keyed by the chosen attribute's
	 * value, that each defer resolution of one abstract assigned to the tag.
	 *
	 * @return array<mixed, Closure>
	 */
	public function resolve(Container $container): array
	{
		return array_map(
			fn (string $abstract): Closure => $container->defer($abstract),
			$container->taggedAbstractsWith($this->tag, $this->attribute)
		);
	}
}
