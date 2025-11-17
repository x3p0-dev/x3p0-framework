# X3P0: Framework

A dependency-injection framework for WordPress plugins and themes. Includes a DI container and abstract application layer.

**Please vendor prefix if releasing as part of a theme/plugin bundle. _Thanks!_**

## Installation

```bash
composer require x3p0-dev/x3p0-framework
```

## Usage

### Create Custom Services

First, you should create your custom services. For example:

- `Your\Plugin\ServiceA`
- `Your\Plugin\ServiceB`

### Create a Service Provider

Then create a custom service provider by extending the included `ServiceProvider` base class:

```php
<?php

namespace Your\Plugin;

use X3P0\Framework\Contracts\Bootable;
use X3P0\Framework\Core\ServiceProvider;

final class YourServiceProvider extends ServiceProvider implements Bootable
{
	public function register(): void
	{
		// Example with an abstract/interface.
		// `transient()` creates a new service each time.
		$this->container->transient(ServiceAInterface::class, ServiceA::class);

		// Example with only a concrete implementation.
		// `singleton()` creates a single instance and reuses it.
		$this->container->singleton(ServiceB::class);
	}

	// Implementing `Bootable` is optional, but you can use it to run any
	// bootstrapping code for the services.
	public function boot(): void
	{
		$this->container->get(ServiceB::class)->boot();
	}
}
```

### Create an Application and Register Providers

Now extend the `Application` base class to register a hook namespace and service providers:

```php
<?php

namespace Your\Plugin;

use X3P0\Framework\Core\Application;

final class App extends Application
{
	/**
	 * Defines the plugin's namespace, which is used as a hook prefix.
	 */
	protected const NAMESPACE = 'your/plugin';

	/**
	 * Defines the plugin's default service providers.
	 */
	protected const PROVIDERS = [
		YourServiceProvider::class
	];
}
```

### Bootstrap

Feel free to bootstrap however you prefer. I typically create a custom function like so:

```php
<?php

namespace Your\Plugin;

use X3P0\Framework\Core\{Application, ServiceContainer};

function plugin(): Application
{
	static $plugin;

	if (! $plugin instanceof Plugin) {
		$plugin = new Plugin(new ServiceContainer());
	}

	return $plugin;
}
```

Then launch like so in my main plugin file:

```php
# Initialize the plugin.
add_action('plugins_loaded', plugin(...), 9999);

# Boot registered services.
add_action('plugins_loaded', fn() => plugin()->boot(), PHP_INT_MAX);
```
