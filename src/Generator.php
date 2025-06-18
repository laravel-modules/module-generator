<?php

namespace LaravelModules\ModuleGenerator;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class Generator
{
    /**
     * Get the project's base path.
     */
    protected function getBasePath(): string
    {
        if (function_exists('base_path')) {
            return base_path();
        }

        return explode('/vendor', __FILE__)[0];
    }

    /**
     * Publish all files from the given stubs to the given directory
     */
    public function publish(string $from, ?string $to = null): self
    {
        $to = $to ?: $this->getBasePath();

        // Source directory does not exist
        if (!is_dir($from)) {
            return $this;
        }

        // Create destination directory if it doesn't exist
        if (!is_dir($to)) {
            mkdir($to, 0777, true);
        }

        $files = scandir($from);

        foreach ($files as $file) {
            if ($file != '.' && $file != '..') {
                $sourceFile = $from . '/' . $file;
                $destinationFile = $to . '/' . $file;

                // Recursively copy subdirectories
                if (is_dir($sourceFile)) {
                    $this->publish($sourceFile, $destinationFile);
                } else {
                    // Copy the file
                    copy($sourceFile, $destinationFile);
                }
            }
        }

        return $this;
    }

    /**
     * @throws \Exception
     */
    public function composer(): Composer
    {
        $composer = new Composer;

        return $composer->setPath($this->getBasePath().'/composer.json');
    }

    public function environment(string $envFile = '.env.example'): Environment
    {
        $env = new Environment();

        return $env->setPath($this->getBasePath().'/'.$envFile);
    }

    public function file(string $path): File
    {
        $file = new File();

        return $file->setPath($path);
    }

    public function replaceInFile(string $filePath, array $search = [], array $replace = []): self
    {
        $content = file_get_contents($filePath);

        $content = str_replace($search, $replace, $content);

        file_put_contents($filePath, $content);

        return $this;
    }

    /**
     * Register laravel service provider in config file.
     *
     * This method only works in laravel projects
     */
    public function registerServiceProvider(string $provider): self
    {
        $provider = Str::replaceLast('::class', '', trim($provider, '\\'));

        if ($this->isLaravelTenOrLower()) {
            $namespace = Str::replaceLast('\\', '', app()->getNamespace());

            $appConfig = file_get_contents(config_path('app.php'));


            if (Str::contains($appConfig, $provider)) {
                return $this;
            }

            file_put_contents(config_path('app.php'), str_replace(
                "{$namespace}\\Providers\EventServiceProvider::class,".PHP_EOL,
                "{$namespace}\\Providers\EventServiceProvider::class,".PHP_EOL."        $provider::class,".PHP_EOL,
                $appConfig
            ));
        } else {
            ServiceProvider::addProviderToBootstrapFile(
                $provider,
                app()->getBootstrapProvidersPath(),
            );
        }

        return $this;
    }

    protected function isLaravelTenOrLower(): bool
    {
        return \Illuminate\Foundation\Application::VERSION < 11;
    }
}