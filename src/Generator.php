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
    public function publish(string $from, ?string $to = null, array $filesNameReplacement = [], array $filesContentReplacement = []): self
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

                $filesNameSearch = array_keys($filesNameReplacement);
                $filesNameReplace = array_values($filesNameReplacement);

                $filesContentSearch = array_keys($filesContentReplacement);
                $filesContentReplace = array_values($filesContentReplacement);

                $destinationFile = str_replace($filesNameSearch, $filesNameReplace, $to . '/' . $file);

                // Recursively copy subdirectories
                if (is_dir($sourceFile)) {
                    $this->publish($sourceFile, $destinationFile, $filesNameReplacement, $filesContentReplacement);
                } else {
                    // Copy the file
                    copy($sourceFile, $destinationFile);

                    if (! empty($filesContentReplacement)) {
                        $fileContent = file_get_contents($destinationFile);
                        $fileContent = str_replace($filesContentSearch, $filesContentReplace, $fileContent);
                        file_put_contents($destinationFile, $fileContent);
                    }
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

    /**
     * Create a new CRUD instance.
     */
    public function crud(string $name, array $replacements = []): CRUD
    {
        return (new CRUD(name: $name))
            ->setGenerator($this)
            ->setReplacement([
                ...$replacements,
                ...$this->getReplacementsFor(name: $name)
            ]);
    }

    /**
     * Get CRUD replacement for the given CRUD name.
     */
    public function getReplacementsFor(string $name): array
    {
        $name = str($name)->studly()->snake(' ')->lower()->toString();

        return [
            // ===== Singular =====
            '__CRUD_STUDLY_SINGULAR__' => str($name)->singular()->studly()->toString(),       // E.g: UserCategory
            '__CRUD_CAMEL_SINGULAR__'  => str($name)->singular()->camel()->toString(),        // E.g: userCategory
            '__CRUD_TITLE_SINGULAR__'  => str($name)->singular()->snake(' ')->title()->toString(), // E.g: User Category
            '__CRUD_UCFIRST_SINGULAR__'=> str($name)->singular()->snake(' ')->ucfirst()->toString(), // E.g: User category
            '__CRUD_LOWER_SINGULAR__'  => str($name)->singular()->snake(' ')->lower()->toString(),   // E.g: user category
            '__CRUD_KEBAB_SINGULAR__'  => str($name)->singular()->kebab()->toString(),        // E.g: user-category
            '__CRUD_SNAKE_SINGULAR__'  => str($name)->singular()->snake()->toString(),        // E.g: user_category
            '__CRUD_PLAIN_SINGULAR__'  => str($name)->singular()->lower()->toString(),        // E.g: usercategory

            // ===== Plural =====
            '__CRUD_STUDLY_PLURAL__'   => str($name)->plural()->studly()->toString(),         // E.g: UserCategories
            '__CRUD_CAMEL_PLURAL__'    => str($name)->plural()->camel()->toString(),          // E.g: userCategories
            '__CRUD_TITLE_PLURAL__'    => str($name)->plural()->snake(' ')->title()->toString(), // E.g: User Categories
            '__CRUD_UCFIRST_PLURAL__'  => str($name)->plural()->snake(' ')->ucfirst()->toString(), // E.g: User categories
            '__CRUD_LOWER_PLURAL__'    => str($name)->plural()->snake(' ')->lower()->toString(),   // E.g: user categories
            '__CRUD_KEBAB_PLURAL__'    => str($name)->plural()->kebab()->toString(),          // E.g: user-categories
            '__CRUD_SNAKE_PLURAL__'    => str($name)->plural()->snake()->toString(),          // E.g: user_categories
            '__CRUD_PLAIN_PLURAL__'    => str($name)->plural()->lower()->toString(),          // E.g: usercategories
        ];
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