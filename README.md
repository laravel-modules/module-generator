## Module Generator

Simple and beautiful tool helps to generate modules to use in your project

### Installation

First add the repository to your application's composer.json file:

```json
"repositories": [
    {
        "type": "composer",
        "url": "https://satis.laraeast.com"
    }
],
```

```shell
composer require laravel-modules/module-generator
```

### Usage

```php
$generator = new \LaravelModules\ModuleGenerator\Generator;

$generator->publish(__DIR__.'/../stubs');
```

##### Stubs Example

This example of users module:

```
stubs/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/
│   │   │       └── Users/
│   │   │           ├── ProfileController.php
│   │   │           └── UserController.php
│   │   ├── Requests/
│   │   │   └── Users/
│   │   │       └── UserRequest.php
│   │   └── Resources/
│   │       └── Users/
│   │           └── UserResource.php
│   └── Providers/
│       └── UsersServiceProvider.php
└── routes/
    └── api/
        └── users.php
```

You can specify publish path directory:

```php
$generator->publish(__DIR__.'/../stubs', base_path('app'));
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
    ->append("FOO=bar")
    ->prepend("FIRST_KEY=first")
    ->appendAfter('APP_URL=', "APP_FRONTEND_URL=https://example.com")
    ->prependBefore('APP_NAME=', "SECOND_KEY=second")
    ->publish();
```

#### Working with files

```php
$generator->file()
    ->file(path: resource_path('views/dashboard/sidebar.blade.php'))
    ->append("@include('dashboard.blogs.partials.sidebar')")
    ->prepend("@include('dashboard.users.partials.sidebar')")
    ->append("@include('dashboard.settings.partials.sidebar')")
    ->appendAfter(
        search: "@include('dashboard.blogs.partials.sidebar')",
        content: "@include('dashboard.articles.partials.sidebar')"
    )
    ->prependBefore(
        search: "@include('dashboard.settings.partials.sidebar')",
        content: "@include('dashboard.posts.partials.sidebar')"
    )
    ->publish()
```
> If the file doesn't exist, It will create a new one with the added content.

#### Modify files
Example file:
```php
<?php
// database/seeders/SettingsSeeder.php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Laraeast\LaravelSettings\Facades\Settings;

class SettingsSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Settings::set('name:en', '{{APP_NAME_EN}}');
        Settings::set('name:ar', '{{APP_NAME_AR}}');
    }
}
```

```php
$generator->file(base_path('database/seeders/SettingsSeeder.php'))
    ->replace(search: '{{APP_NAME_EN}}', replace: $nameEn)
    ->replace(search: '{{APP_NAME_AR}}', replace: $nameAr)
    ->publish()
```
