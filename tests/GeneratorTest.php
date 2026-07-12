<?php

namespace LaravelModules\ModuleGenerator\Tests;

use LaravelModules\ModuleGenerator\Environment;
use LaravelModules\ModuleGenerator\File;
use LaravelModules\ModuleGenerator\Generator;
use PHPUnit\Framework\Attributes\DataProvider;

class GeneratorTest extends TestCase
{
    private Generator $generator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generator = new Generator;
    }

    public function test_publish_copies_a_directory_tree(): void
    {
        $this->makeFile('stubs/app/Foo.php', 'foo');
        $this->makeFile('stubs/routes/api.php', 'route');

        $this->generator->publish($this->path('stubs'), $this->path('out'));

        $this->assertSame('foo', file_get_contents($this->path('out/app/Foo.php')));
        $this->assertSame('route', file_get_contents($this->path('out/routes/api.php')));
    }

    public function test_publish_returns_early_when_source_is_missing(): void
    {
        $result = $this->generator->publish($this->path('missing'), $this->path('out'));

        $this->assertInstanceOf(Generator::class, $result);
        $this->assertDirectoryDoesNotExist($this->path('out'));
    }

    public function test_publish_replaces_file_names(): void
    {
        $this->makeFile('stubs/CrudController.php', 'body');

        $this->generator->publish(
            from: $this->path('stubs'),
            to: $this->path('out'),
            filesNameReplacement: ['Crud' => 'Category'],
        );

        $this->assertFileExists($this->path('out/CategoryController.php'));
    }

    public function test_publish_replaces_file_content(): void
    {
        $this->makeFile('stubs/file.php', 'class Crud {}');

        $this->generator->publish(
            from: $this->path('stubs'),
            to: $this->path('out'),
            filesContentReplacement: ['Crud' => 'Category'],
        );

        $this->assertSame('class Category {}', file_get_contents($this->path('out/file.php')));
    }

    public function test_publish_replaces_nested_directory_names(): void
    {
        $this->makeFile('stubs/Crud/CrudController.php', 'class Crud {}');

        $this->generator->publish(
            from: $this->path('stubs'),
            to: $this->path('out'),
            filesNameReplacement: ['Crud' => 'Category'],
            filesContentReplacement: ['Crud' => 'Category'],
        );

        $this->assertSame(
            'class Category {}',
            file_get_contents($this->path('out/Category/CategoryController.php'))
        );
    }

    public function test_factory_methods_return_the_expected_types(): void
    {
        $this->assertInstanceOf(File::class, $this->generator->file($this->path('a.txt')));
        $this->assertInstanceOf(Environment::class, $this->generator->environment());
    }

    #[DataProvider('replacementProvider')]
    public function test_get_replacements_for_generates_expected_casings(string $placeholder, string $expected): void
    {
        $replacements = $this->generator->getReplacementsFor('UserCategory');

        $this->assertSame($expected, $replacements[$placeholder]);
    }

    public static function replacementProvider(): array
    {
        return [
            ['__CRUD_STUDLY_SINGULAR__', 'UserCategory'],
            ['__CRUD_CAMEL_SINGULAR__', 'userCategory'],
            ['__CRUD_TITLE_SINGULAR__', 'User Category'],
            ['__CRUD_UCFIRST_SINGULAR__', 'User category'],
            ['__CRUD_LOWER_SINGULAR__', 'user category'],
            ['__CRUD_KEBAB_SINGULAR__', 'user-category'],
            ['__CRUD_SNAKE_SINGULAR__', 'user_category'],
            ['__CRUD_SNAKE_UPPER_SINGULAR__', 'USER_CATEGORY'],
            ['__CRUD_PLAIN_SINGULAR__', 'usercategory'],
            ['__CRUD_STUDLY_PLURAL__', 'UserCategories'],
            ['__CRUD_CAMEL_PLURAL__', 'userCategories'],
            ['__CRUD_TITLE_PLURAL__', 'User Categories'],
            ['__CRUD_KEBAB_PLURAL__', 'user-categories'],
            ['__CRUD_SNAKE_PLURAL__', 'user_categories'],
            ['__CRUD_SNAKE_UPPER_PLURAL__', 'USER_CATEGORIES'],
            ['__CRUD_PLAIN_PLURAL__', 'usercategories'],
        ];
    }

    public function test_get_replacements_for_normalises_plural_input(): void
    {
        $replacements = $this->generator->getReplacementsFor('UserCategories');

        $this->assertSame('UserCategory', $replacements['__CRUD_STUDLY_SINGULAR__']);
        $this->assertSame('UserCategories', $replacements['__CRUD_STUDLY_PLURAL__']);
    }
}
