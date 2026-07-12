<?php

namespace LaravelModules\ModuleGenerator\Tests;

use LaravelModules\ModuleGenerator\File;

class FileTest extends TestCase
{
    private function file(string $relative): File
    {
        return (new File)->setPath($this->path($relative));
    }

    public function test_it_reads_existing_content(): void
    {
        $this->makeFile('a.txt', 'hello');

        $this->assertSame('hello', $this->file('a.txt')->getContent());
    }

    public function test_it_treats_a_missing_file_as_empty_without_creating_it(): void
    {
        $path = $this->path('missing.txt');

        $file = (new File)->setPath($path);

        $this->assertSame('', $file->getContent());
        $this->assertFileDoesNotExist($path);
    }

    public function test_publish_creates_the_file_and_parent_directories(): void
    {
        $path = $this->path('nested/dir/created.txt');

        $this->file('nested/dir/created.txt')->append('content')->publish();

        $this->assertFileExists($path);
        $this->assertSame('content', file_get_contents($path));
    }

    public function test_append_adds_content_on_a_new_line(): void
    {
        $this->makeFile('a.txt', 'first');

        $content = $this->file('a.txt')->append('second')->getContent();

        $this->assertSame("first\nsecond", $content);
    }

    public function test_append_is_idempotent(): void
    {
        $this->makeFile('a.txt', 'line');

        $content = $this->file('a.txt')->append('line')->append('line')->getContent();

        $this->assertSame('line', $content);
    }

    public function test_prepend_adds_content_before_existing_content(): void
    {
        $this->makeFile('a.txt', 'body');

        $content = $this->file('a.txt')->prepend('head')->getContent();

        $this->assertSame("head\nbody", $content);
    }

    public function test_append_after_inserts_after_the_matching_line(): void
    {
        $this->makeFile('a.txt', "one\ntwo\nthree");

        $content = $this->file('a.txt')->appendAfter('two', 'inserted')->getContent();

        $this->assertSame("one\ntwo\ninserted\nthree", $content);
    }

    public function test_append_after_falls_back_to_append_when_search_is_missing(): void
    {
        $this->makeFile('a.txt', 'one');

        $content = $this->file('a.txt')->appendAfter('missing', 'inserted')->getContent();

        $this->assertSame("one\ninserted", $content);
    }

    public function test_prepend_before_inserts_before_the_matching_line(): void
    {
        $this->makeFile('a.txt', "one\ntwo\nthree");

        $content = $this->file('a.txt')->prependBefore('two', 'inserted')->getContent();

        $this->assertSame("one\ninserted\ntwo\nthree", $content);
    }

    public function test_append_after_only_touches_the_first_match(): void
    {
        $this->makeFile('a.txt', "dup\ndup");

        $content = $this->file('a.txt')->appendAfter('dup', 'x')->getContent();

        $this->assertSame("dup\nx\ndup", $content);
    }

    public function test_replace_swaps_placeholders(): void
    {
        $this->makeFile('a.txt', 'Hello {{NAME}}');

        $content = $this->file('a.txt')->replace('{{NAME}}', 'World')->getContent();

        $this->assertSame('Hello World', $content);
    }

    public function test_publish_persists_accumulated_changes(): void
    {
        $path = $this->makeFile('a.txt', 'body');

        $this->file('a.txt')->prepend('head')->append('foot')->publish();

        $this->assertSame("head\nbody\nfoot", file_get_contents($path));
    }
}
