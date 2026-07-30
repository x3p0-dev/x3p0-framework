<?php

declare(strict_types=1);

namespace X3P0\Framework\Tests\Fixtures;

use X3P0\Framework\Container\Attributes\SingletonWhen;

#[SingletonWhen(false)]
final class SingletonWhenFalseCache implements Cache
{
}
