<?php

/**
 * Tagged abstracts attribute.
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
 * Injects an array for a tagged group of services. This defers building any
 * member until its abstract identifier is looked up and passed to `make()` (or
 * similar), which is what makes it suited to a large tag group where a consumer
 * only ever needs specific members:
 *
 *     public function __construct(
 *         #[TaggedAbstracts('channel')] private readonly array $channels
 *     ) {}
 *
 *     foreach ($channels as $channel) {
 *         if (is_subclass_of($channel, PublicChannel::class)) {
 *             $instance = $this->container->make($channel);
 *         }
 *     }
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final class TaggedAbstracts implements ContextualAttribute
{
	/**
	 * Stores the tag whose services to inject.
	 */
	public function __construct(private readonly string $tag)
	{}

	/**
	 * Returns an array of the abstracts under a given tag.
	 *
	 * @return array<mixed, string>
	 */
	public function resolve(Container $container): array
	{
		return $container->taggedAbstracts($this->tag);
	}
}
