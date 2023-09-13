## Module Generator

Simple and beautiful tool helps to generate modules to use in your project

### Installation

First add the repository to your application's composer.json file:

```json
"repositories": [
    {
        "type": "composer",
        "url": "https://packages.b5digital.dk"
    }
],
```

```shell
composer require b5-digital/module-generator
```

### Usage

```php
$generator = new \B5Digital\ModuleGenerator\Generator;

$generator->publish(__DIR__.'/../stubs');
```

##### Stubs Example

This example of users module:

```
- stubs
    - app
        - Http
            - Controllers
                - Api
                    - Users
                        - ProfileController.php
                        - UserController.php
            - Requests
                - Users
                    - UserRequest.php
            - Resources
                - Users
                    - UserResource.php
        - Providers
            - UsersServiceProvider.php
    - routes
        - api
            users.php
```

You can specify publish path directory:

```php
$generator->publish(__DIR__.'/../stubs', '/path/to/publish');
```

#### Register Service Provider
You can register the service provider in `config/app.php` file automatically by calling the `registerServiceProvider()` method:

```php
$generator->registerServiceProvider('App\Providers\UsersServiceProvider')

```

#### Working with composer file

```php
$generator->composer()
    ->removePackages([
        'laravel/breeze',
    ])
    ->mergeRequire([
        'creativeorange/gravatar' => '~1.0.20',
        'laravel/socialite' => '^5.6',
        'laravel/ui' => '^3.2.0',
        'rollbar/rollbar-laravel' => '^7.0',
        'spatie/laravel-medialibrary' => '^10.0.0',
        'spatie/laravel-permission' => '^5.0',
        'yajra/laravel-datatables' => '^1.5',
        'yajra/laravel-datatables-oracle' => '^9.0',
        'ylsideas/feature-flags' => '^2.0',
    ])
    ->mergeRequireDev([
        'barryvdh/laravel-ide-helper' => '^2.13',
    ])
    ->removeScripts(['post-create-project-cmd'])
    ->mergeScripts([
        'auto-complete:generate' => [
            '@php artisan ide-helper:meta --ansi --quiet',
            '@php artisan ide-helper:generate --ansi --quiet',
            '@php artisan ide-helper:models --nowrite --quiet',
        ],
    ])
    ->publish();
```

> Also, you can save a backup copy of your composer before modify named "composer-backup.json"

```php
$generator->composer()
    ->mergeRequire([
        'laravel/socialite' => '^5.6',
    ])
    ->withBackup()
    ->publish();
```

> If you want to add a helper file to autoload, You can use `mergeAutoloadFiles` method:

```php
$generator->composer()
    ->mergeAutoloadFiles([
        'app/Support/helpers.php',
    ])
    ->publish();
```

> You can add some packages to `dont-discover` by calling `dontDiscover()` method:

```php
$generator->composer()
    ->dontDiscover([
        'rollbar/rollbar-laravel',
    ])
    ->publish();
```

#### Working with .env.example file

```php
$generator->environment()
    ->set('APP_NAME', 'Starter Kit')
    ->set('ROLLBAR_TOKEN', "123")
    ->appendAfter('APP_URL=', "APP_FRONTEND_URL=https://example.com")
    ->append("\nFOO=bar")
    ->publish();
```