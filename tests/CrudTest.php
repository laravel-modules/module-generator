<?php

namespace LaravelModules\ModuleGenerator\Tests;

use LaravelModules\ModuleGenerator\CRUD;
use LaravelModules\ModuleGenerator\Generator;

class CrudTest extends TestCase
{
    private Generator $generator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generator = new Generator;
    }

    public function test_crud_returns_a_configured_instance(): void
    {
        $crud = $this->generator->crud('UserCategory');

        $this->assertInstanceOf(CRUD::class, $crud);
    }

    public function test_it_generates_files_with_replaced_names_and_content(): void
    {
        $this->makeFile(
            'stubs/crud/app/Http/Controllers/__CRUD_STUDLY_SINGULAR__Controller.php.stub',
            "class __CRUD_STUDLY_SINGULAR__Controller {}\n// route: __CRUD_KEBAB_PLURAL__"
        );
        $this->makeFile(
            'stubs/crud/routes/api/__CRUD_KEBAB_PLURAL__.php.stub',
            "Route::apiResource('__CRUD_KEBAB_PLURAL__', __CRUD_STUDLY_SINGULAR__Controller::class);"
        );

        $this->generator->crud('UserCategory')
            ->fromPath($this->path('stubs/crud'))
            ->toPath($this->path('out'))
            ->publish();

        $controller = $this->path('out/app/Http/Controllers/UserCategoryController.php');
        $route = $this->path('out/routes/api/user-categories.php');

        $this->assertFileExists($controller);
        $this->assertFileExists($route);
        $this->assertSame(
            "class UserCategoryController {}\n// route: user-categories",
            file_get_contents($controller)
        );
        $this->assertStringContainsString("'user-categories'", file_get_contents($route));
    }

    public function test_it_prefixes_migrations_with_a_timestamp(): void
    {
        $this->makeFile(
            'stubs/crud/database/migrations/create___CRUD_SNAKE_PLURAL___table.php.stub',
            'migration body'
        );

        $this->generator->crud('UserCategory')
            ->fromPath($this->path('stubs/crud'))
            ->toPath($this->path('out'))
            ->publish();

        $migrations = glob($this->path('out/database/migrations/*_create_user_categories_table.php'));

        $this->assertCount(1, $migrations);
        $this->assertMatchesRegularExpression(
            '/\d{4}_\d{2}_\d{2}_\d{6}_create_user_categories_table\.php$/',
            $migrations[0]
        );
    }

    public function test_it_strips_the_stub_extension(): void
    {
        $this->makeFile('stubs/crud/config/__CRUD_SNAKE_PLURAL__.php.stub', 'config');

        $this->generator->crud('UserCategory')
            ->fromPath($this->path('stubs/crud'))
            ->toPath($this->path('out'))
            ->publish();

        $this->assertFileExists($this->path('out/config/user_categories.php'));
    }

    public function test_custom_replacements_take_precedence_is_available(): void
    {
        $crud = $this->generator->crud('UserCategory', ['__EXTRA__' => 'value'])
            ->appendReplacements(['__ANOTHER__' => 'x']);

        $this->assertInstanceOf(CRUD::class, $crud);
    }

    public function test_append_to_file_inserts_replaced_content_before_a_marker(): void
    {
        $target = $this->makeFile('resources/sidebar.txt', "start\n</ul>");

        $this->generator->crud('UserCategory')
            ->appendToFile(
                file: $target,
                content: 'link: __CRUD_KEBAB_PLURAL__',
                before: '</ul>',
            );

        $this->assertSame(
            "start\nlink: user-categories\n</ul>",
            file_get_contents($target)
        );
    }
}
