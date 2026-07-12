<?php

namespace LaravelModules\ModuleGenerator\Tests;

use LaravelModules\ModuleGenerator\Environment;

class EnvironmentTest extends TestCase
{
    private function env(string $relative, string $content = ''): Environment
    {
        $this->makeFile($relative, $content);

        return (new Environment)->setPath($this->path($relative));
    }

    public function test_it_updates_an_existing_key(): void
    {
        $content = $this->env('.env', "APP_NAME=Laravel\nAPP_ENV=local")
            ->set('APP_NAME', 'Starter')
            ->getContent();

        $this->assertSame("APP_NAME=Starter\nAPP_ENV=local", $content);
    }

    public function test_it_appends_a_missing_key(): void
    {
        $content = $this->env('.env', 'APP_NAME=Laravel')
            ->set('NEW_KEY', 'value')
            ->getContent();

        $this->assertSame("APP_NAME=Laravel\nNEW_KEY=value", $content);
    }

    public function test_it_wraps_values_containing_spaces_in_quotes(): void
    {
        $content = $this->env('.env', 'APP_NAME=Laravel')
            ->set('APP_NAME', 'Starter Kit')
            ->getContent();

        $this->assertSame('APP_NAME="Starter Kit"', $content);
    }

    public function test_it_accepts_a_null_value(): void
    {
        $content = $this->env('.env', 'APP_NAME=Laravel')
            ->set('APP_KEY', null)
            ->getContent();

        $this->assertSame("APP_NAME=Laravel\nAPP_KEY=", $content);
    }

    public function test_it_accepts_a_numeric_value(): void
    {
        $content = $this->env('.env', 'APP_NAME=Laravel')
            ->set('TOKEN', 123)
            ->getContent();

        $this->assertSame("APP_NAME=Laravel\nTOKEN=123", $content);
    }

    public function test_it_does_not_match_keys_that_share_a_prefix(): void
    {
        $content = $this->env('.env', "APP_NAME=Laravel\nAPP_NAME_SUFFIX=keep")
            ->set('APP_NAME', 'Changed')
            ->getContent();

        $this->assertSame("APP_NAME=Changed\nAPP_NAME_SUFFIX=keep", $content);
    }

    public function test_it_persists_on_publish(): void
    {
        $env = $this->env('.env', 'APP_NAME=Laravel');
        $env->set('APP_NAME', 'Starter')->publish();

        $this->assertSame('APP_NAME=Starter', file_get_contents($this->path('.env')));
    }
}
