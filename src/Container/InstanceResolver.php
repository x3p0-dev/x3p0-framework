<?php

/**
 * Instance resolver interface.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2025, Justin Tadlock
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 * @link      https://github.com/x3p0-dev/x3p0-framework
 */

declare(strict_types=1);

namespace X3P0\Framework\Container;

/**
 * The resolution capability of the container, narrowed to instance creation.
 * Factories, decorators, and resolving callbacks receive this rather than the
 * full container, so they may build objects but never reconfigure bindings.
 * The container itself implements this contract, so any consumer that only
 * needs to resolve can type-hint the resolver instead of the whole container.
 */
interface InstanceResolver
{
	/**
	 * Resolve a class from the container, optionally overriding constructor
	 * parameters. Values in `$parameters` are matched by name and take
	 * precedence over type-based autowiring; remaining parameters are resolved
	 * from the container. A parameterized resolution is never cached.
	 *
	 * @template T of object
	 * @param    class-string<T>      $abstract
	 * @param    array<string, mixed> $parameters
	 * @return   T
	 */
	public function make(string $abstract, array $parameters = []): object;

	/**
	 * Resolve a fresh, unshared instance of the abstract, bypassing any
	 * cached singleton. Unlike `make()`, a matching cached instance is
	 * neither returned nor overwritten — the shared instance is left in
	 * place and a newly built one is returned. Only the requested abstract
	 * is built anew; its own dependencies resolve normally, so shared
	 * singletons deeper in the graph stay shared. Overrides in `$parameters`
	 * are matched by name, as `make()` does.
	 *
	 * @template T of object
	 * @param    class-string<T>      $abstract
	 * @param    array<string, mixed> $parameters
	 * @return   T
	 */
	public function makeFresh(string $abstract, array $parameters = []): object;
}
