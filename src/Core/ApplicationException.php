<?php

/**
 * Application exception.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2025, Justin Tadlock
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 * @link      https://github.com/x3p0-dev/x3p0-framework
 */

declare(strict_types=1);

namespace X3P0\Framework\Core;

use LogicException;

/**
 * Base exception for application-layer misconfiguration, such as registering an
 * invalid service provider or listing a non-bootable service. Extends
 * `LogicException` because these represent programming errors in how the
 * application is wired rather than recoverable runtime failures. Catch this to
 * handle any application bootstrap error.
 */
class ApplicationException extends LogicException
{
}
