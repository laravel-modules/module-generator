<?php

namespace LaravelModules\ModuleGenerator\Tests;

use LaravelModules\ModuleGenerator\Composer;

class ComposerTest extends TestCase
{
    private function composer(array $content = []): Composer
    {
        $this->makeFile('composer.json', json_encode($content ?: ['name' => 'test/app']));

        return (new Composer)->setPath($this->path('composer.json'));
    }

    public function test_set_path_throws_when_the_file_is_missing(): void
    {
        $this->expectException(\Exception::class);

        (new Composer)->setPath($this->path('nope.json'));
    }

    public function test_set_path_throws_when_the_json_is_invalid(): void
    {
        $this->makeFile('composer.json', '{ invalid json');

        $this->expectException(\Exception::class);

        (new Composer)->setPath($this->path('composer.json'));
    }

    public function test_it_merges_require_packages(): void
    {
        $content = $this->composer(['require' => ['php' => '^8.0']])
            ->mergeRequire(['laravel/framework' => '^11.0'])
            ->getContent();

        $this->assertSame(['php' => '^8.0', 'laravel/framework' => '^11.0'], $content['require']);
    }

    public function test_it_merges_require_dev_packages(): void
    {
        $content = $this->composer()
            ->mergeRequireDev(['phpunit/phpunit' => '^11.0'])
            ->getContent();

        $this->assertSame(['phpunit/phpunit' => '^11.0'], $content['require-dev']);
    }

    public function test_it_removes_packages_from_require_and_require_dev(): void
    {
        $content = $this->composer([
            'require' => ['php' => '^8.0', 'laravel/breeze' => '^1.0'],
            'require-dev' => ['laravel/breeze' => '^1.0'],
        ])->removePackages(['laravel/breeze'])->getContent();

        $this->assertSame(['php' => '^8.0'], $content['require']);
        $this->assertSame([], $content['require-dev']);
    }

    public function test_it_adds_packages_to_dont_discover_without_duplicates(): void
    {
        $content = $this->composer()
            ->dontDiscover(['a/b', 'a/b', 'c/d'])
            ->getContent();

        $this->assertSame(['a/b', 'c/d'], array_values($content['extra']['laravel']['dont-discover']));
    }

    public function test_it_merges_autoload_files_without_duplicates(): void
    {
        $content = $this->composer()
            ->mergeAutoloadFiles(['app/helpers.php'])
            ->mergeAutoloadFiles(['app/helpers.php', 'app/other.php'])
            ->getContent();

        $this->assertSame(['app/helpers.php', 'app/other.php'], array_values($content['autoload']['files']));
    }

    public function test_it_merges_and_removes_scripts(): void
    {
        $content = $this->composer(['scripts' => ['post-install-cmd' => ['a'], 'obsolete' => ['b']]])
            ->mergeScripts(['test' => ['phpunit']])
            ->removeScripts(['obsolete'])
            ->getContent();

        $this->assertArrayHasKey('test', $content['scripts']);
        $this->assertArrayNotHasKey('obsolete', $content['scripts']);
        $this->assertArrayHasKey('post-install-cmd', $content['scripts']);
    }

    public function test_publish_writes_valid_pretty_json(): void
    {
        $this->composer(['require' => ['php' => '^8.0']])
            ->mergeRequire(['laravel/framework' => '^11.0'])
            ->publish();

        $raw = file_get_contents($this->path('composer.json'));

        $this->assertStringEndsWith("\n", $raw);
        $this->assertStringContainsString('    "require"', $raw);
        $this->assertSame(
            ['php' => '^8.0', 'laravel/framework' => '^11.0'],
            json_decode($raw, true)['require']
        );
    }

    public function test_it_exposes_the_original_content(): void
    {
        $composer = $this->composer(['require' => ['php' => '^8.0']]);
        $composer->mergeRequire(['laravel/framework' => '^11.0']);

        $this->assertSame(['require' => ['php' => '^8.0']], $composer->getOriginalContent());
    }
}
