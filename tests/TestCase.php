<?php

namespace LaravelModules\ModuleGenerator\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * A unique temporary working directory for the current test.
     */
    protected string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir().'/module-generator-tests/'.uniqid('', true);

        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->tempDir);

        parent::tearDown();
    }

    /**
     * Build an absolute path inside the test's temporary directory.
     */
    protected function path(string $relative = ''): string
    {
        return rtrim($this->tempDir.'/'.ltrim($relative, '/'), '/');
    }

    /**
     * Create a file (and any missing parent directories) with the given content.
     */
    protected function makeFile(string $relative, string $content = ''): string
    {
        $path = $this->path($relative);

        if (! is_dir($directory = dirname($path))) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($path, $content);

        return $path;
    }

    /**
     * Recursively delete a directory.
     */
    protected function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory.'/'.$entry;

            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }

        rmdir($directory);
    }
}
