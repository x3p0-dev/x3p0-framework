<?php

/**
 * SingletonWhen attribute.
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

/**
 * Marks a class to be cached as a single shared instance when the container
 * autowires it, but only when the given condition evaluates truthy. A bool is
 * used as-is; a Closure is invoked with the container; any other callable is
 * run through `Container::call()` so it is autowired like any other container
 * callback. Like `#[Singleton]`, it only applies to classes resolved without
 * an explicit binding; a binding's declared lifetime always takes precedence.
 *
 *     #[SingletonWhen([FeatureFlags::class, 'cacheIsShared'])]
 *     final class FileCache implements Cache {}
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class SingletonWhen
{
	public function __construct(
		public readonly Closure|string|array|bool $condition
	) {}
}
