<?php

/**
 * Invalid provider exception.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2025, Justin Tadlock
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 * @link      https://github.com/x3p0-dev/x3p0-framework
 */

declare(strict_types=1);

namespace X3P0\Framework\Core;

/**
 * Thrown when a value registered with the application is not a `ServiceProvider`
 * class or instance. Extends `ApplicationException`, so catching the latter also
 * catches invalid-provider failures.
 */
class InvalidProviderException extends ApplicationException
{
}
