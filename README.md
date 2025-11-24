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
$generator->publish(
    from: __DIR__.'/../stubs',
    to: base_path('app')
);
```
You can also replace published file names using the third and forth arguments `$filesNameReplacement` and `$filesContentReplacement` by adding array of [search => replacement]

Example of crud module generator:

```
stubs/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/
│   │   │       └── Crud/
│   │   │           └── CrudController.php
│   │   ├── Requests/
│   │   │   └── Cruds/
│   │   │       └── CrudRequest.php
│   │   └── Resources/
│   │       └── Cruds/
│   │           └── CrudResource.php
│   └── Providers/
│       └── CrudsServiceProvider.php
└── routes/
    └── api/
        └── cruds.php
```

```php
$crudReplacement = [
    'Cruds' => 'Categories',
    'cruds' => 'categories',
    'Crud' => 'Category',
    'crud' => 'category',
];

$generator->publish(
    from: __DIR__.'/../stubs',
    to: base_path('app'),
    filesNameReplacement: $crudReplacement,
    filesContentReplacement: $crudReplacement,
);
```

Result:
```
stubs/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/
│   │   │       └── CategoryController.php
│   │   ├── Requests/
│   │   │   └── CategoryRequest.php
│   │   └── Resources/
│   │       └── CategoryResource.php
│   └── Providers/
│       └── CategoriesServiceProvider.php
└── routes/
    └── api/
        └── categories.php
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
$generator
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
#### Working with CRUDs
> This package allows you to generate full CRUD files, You can generate it by using the `crud()` method:
> 
> Here is an example of how to generate a `user_categories` CRUD:
##### Replacements of the CRUD words & file names:
| Key                    | Example              |
|------------------------|----------------------|
| `__CRUD_STUDLY_SINGULAR__` | UserCategory         |
| `__CRUD_CAMEL_SINGULAR__` | userCategory         |
| `__CRUD_TITLE_SINGULAR__` | User Category        |
| `__CRUD_UCFIRST_SINGULAR__` | User category        |
| `__CRUD_LOWER_SINGULAR__` | user category        |
| `__CRUD_KEBAB_SINGULAR__` | user-category        |
| `__CRUD_SNAKE_SINGULAR__` | user_category        |
| `__CRUD_PLAIN_SINGULAR__` | usercategory         |
| `__CRUD_STUDLY_PLURAL__` | UserCategories       |
| `__CRUD_CAMEL_PLURAL__` | userCategories       |
| `__CRUD_TITLE_PLURAL__` | User Categories      |
| `__CRUD_UCFIRST_PLURAL__` | User categories      |
| `__CRUD_LOWER_PLURAL__` | user categories      |
| `__CRUD_KEBAB_PLURAL__` | user-categories      |
| `__CRUD_SNAKE_PLURAL__` | user_categories      |
| `__CRUD_PLAIN_PLURAL__` | usercategories       |

##### Files Structure
```
stubs/
├── sidebar.stub
└── crud/
    ├── app/
    │   ├── Http/
    │   │   ├── Controllers/
    │   │   │   └── Api/
    │   │   │       └── __CRUD_STUDLY_SINGULAR__Controller.php     // e.g., UserCategoryController.php
    │   │   ├── Requests/
    │   │   │   └── __CRUD_STUDLY_SINGULAR__Request.php            // e.g., UserCategoryRequest.php
    │   │   └── Resources/
    │   │       └── __CRUD_STUDLY_SINGULAR__Resource.php           // e.g., UserCategoryResource.php
    │   └── Providers/
    │       └── __CRUD_STUDLY_PLURAL__ServiceProvider.php          // e.g., UserCategoriesServiceProvider.php
    ├── lang/
    │   └── __CRUD_KEBAB_PLURAL__.php                              // e.g., user-categories.php
    └── routes/
        └── api/
            └── __CRUD_KEBAB_PLURAL__.php                          // e.g., user-categories.php
```
##### sidebar.stub
```html
        <SidebarLink
            :label="$t('__CRUD_KEBAB_PLURAL__.plural')"
            :href="route('dashboard.__CRUD_KEBAB_PLURAL__.index')"
            :active="['Dashboard/__CRUD_STUDLY_PLURAL__/Index', 'Dashboard/__CRUD_STUDLY_PLURAL__/Create', 'Dashboard/__CRUD_STUDLY_PLURAL__/Edit'].includes(page.component)"
        >
            <template #svg>
                <LocationIcon width="20" height="20" class="me-2"></LocationIcon>
            </template>
        </SidebarLink>

```
##### Command to generate the CRUD files
```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use LaravelModules\ModuleGenerator\Generator;
use function Laravel\Prompts\text;

class MakeCrudCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:crud {name?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a new CRUD';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $name = $this->argument('name') ?? text('What is the CRUD name?');

        $generator = new Generator;

        $generator
            ->crud(name: $name)
            ->fromPath(base_path('stubs/crud'))
            ->toPath(base_path())
            ->appendToFile(
                file: resource_path('js/components/SidebarItems.vue'),
                content: file_get_contents(base_path('stubs/sidebar.stub')),
                before: '    </ul>',
            )
            ->publish();

        $this->info('CRUD  has been generated successfully.');
    }
}
```
> Now you can run the command to generate a new CRUD:

```shell
php artisan make:crud UserCategory
```