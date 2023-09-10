## Module Generator

Simple and beautiful tool helps to generate modules to use in your project

### Installation

```shell
composer require b5digital/module-generator
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
    - routes
        - api
            users.php
```

You can specify publish path directory:

```php
$generator->publish(__DIR__.'/../stubs', '/path/to/publish');
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

> If you want to add a hel file to autoload, You can use ``mergeAutoloadFiles`` method:

```php
$generator->composer()
    ->mergeAutoloadFiles([
        'app/Support/helpers.php',
    ])
    ->publish();
``