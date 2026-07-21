<?php

/**
 * Defer tagged attribute.
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
 * resolvers, combining `Container::taggedAbstracts()` with `Container::defer()`.
 * Rather than building every tagged service up front, it injects one closure
 * per tagged abstract, each resolving its service only when called. The
 * consumer iterates and builds services on demand without depending on the
 * container itself. Pair it with an `iterable` or `array` parameter type:
 *
 *     public function __construct(
 *         #[DeferTagged('theme.blocks')] iterable $blocks
 *     ) {}
 *
 *     foreach ($blocks as $makeBlock) {
 *         $block = $makeBlock();
 *     }
 *
 * Each closure is keyed by the abstract it resolves, so a consumer can select
 * and build only the services it needs without instantiating the rest:
 *
 *     foreach ($modifiers as $class => $makeModifier) {
 *         $map[ForBlock::of($class)] = $makeModifier;
 *     }
 *
 * Whether each call yields a fresh or shared instance follows the binding's
 * lifetime, exactly as `Container::defer()` does.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final class DeferTagged implements ContextualAttribute
{
	/**
	 * Stores the tag whose deferred resolvers are injected.
	 */
	public function __construct(private readonly string $tag)
	{}

	/**
	 * Resolves to an array of closures, keyed by abstract, that each defer
	 * resolution of one abstract assigned to the tag.
	 *
	 * @return array<string, Closure>
	 */
	public function resolve(Container $container): array
	{
		$abstracts = $container->taggedAbstracts($this->tag);

		return array_combine($abstracts, array_map(
			fn (string $abstract): Closure => $container->defer($abstract),
			$abstracts
		));
	}
}
