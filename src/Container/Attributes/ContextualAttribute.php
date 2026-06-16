<?php

/**
 * Contextual attribute interface.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2025, Justin Tadlock
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 * @link      https://github.com/x3p0-dev/x3p0-framework
 */

declare(strict_types=1);

namespace X3P0\Framework\Container\Attributes;

use X3P0\Framework\Container\Container;

/**
 * Defines the contract for parameter attributes that resolve their own value
 * during autowiring. When the container encounters a constructor parameter
 * marked with a contextual attribute, it calls `resolve()` to obtain the value
 * instead of resolving the parameter from its type.
 */
interface ContextualAttribute
{
	/**
	 * Resolves the value to inject for the attributed parameter.
	 */
	public function resolve(Container $container): mixed;
}
