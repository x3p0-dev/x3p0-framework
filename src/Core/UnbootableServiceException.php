<?php

/**
 * Unbootable service exception.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2025, Justin Tadlock
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 * @link      https://github.com/x3p0-dev/x3p0-framework
 */

declare(strict_types=1);

namespace X3P0\Framework\Core;

/**
 * Thrown when a service listed in a provider's `BOOTABLE` constant does not
 * implement the `Bootable` contract. Extends `ApplicationException`, so catching
 * the latter also catches unbootable-service failures.
 */
class UnbootableServiceException extends ApplicationException
{
}
