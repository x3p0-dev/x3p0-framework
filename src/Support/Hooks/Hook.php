<?php

/**
 * Hook interface.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2025, Justin Tadlock
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 * @link      https://github.com/x3p0-dev/x3p0-framework
 */

declare(strict_types=1);

namespace X3P0\Framework\Support\Hooks;

/**
 * Defines the contract that hook attributes should utilize. Hook classes should
 * have a `register()` method with the purpose of registering an action or
 * filter in WordPress.
 */
interface Hook
{
	/**
	 * Registers the callback.
	 */
	public function register(callable $callback, int $arguments = 1): void;
}
