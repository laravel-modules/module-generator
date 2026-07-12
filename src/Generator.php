<?php

namespace LaravelModules\ModuleGenerator;

use Illuminate\Foundation\Application;
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
        if (! is_dir($from)) {
            return $this;
        }

        // Create destination directory if it doesn't exist
        if (! is_dir($to)) {
            mkdir($to, 0755, true);
        }

        $filesNameSearch = array_keys($filesNameReplacement);
        $filesNameReplace = array_values($filesNameReplacement);

        $filesContentSearch = array_keys($filesContentReplacement);
        $filesContentReplace = array_values($filesContentReplacement);

        foreach (scandir($from) as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $sourceFile = $from.'/'.$file;
            $destinationFile = str_replace($filesNameSearch, $filesNameReplace, $to.'/'.$file);

            // Recursively copy subdirectories
            if (is_dir($sourceFile)) {
                $this->publish($sourceFile, $destinationFile, $filesNameReplacement, $filesContentReplacement);

                continue;
            }

            // Copy the file, applying content replacements when requested.
            copy($sourceFile, $destinationFile);

            if (! empty($filesContentReplacement)) {
                $fileContent = str_replace($filesContentSearch, $filesContentReplace, file_get_contents($destinationFile));
                file_put_contents($destinationFile, $fileContent);
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
        $env = new Environment;

        return $env->setPath($this->getBasePath().'/'.$envFile);
    }

    public function file(string $path): File
    {
        $file = new File;

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
                ...$this->getReplacementsFor(name: $name),
            ]);
    }

    /**
     * Get CRUD replacement for the given CRUD name.
     */
    public function getReplacementsFor(string $name): array
    {
        // Normalise the name to a lower-case, space separated phrase (e.g.
        // "UserCategories" => "user categories") so it can be re-cased below.
        $normalized = str($name)->studly()->snake(' ')->lower()->toString();

        $singular = str($normalized)->singular();
        $plural = str($normalized)->plural();

        return [
            // ===== Singular =====
            '__CRUD_STUDLY_SINGULAR__' => $singular->studly()->toString(),            // E.g: UserCategory
            '__CRUD_CAMEL_SINGULAR__' => $singular->camel()->toString(),             // E.g: userCategory
            '__CRUD_TITLE_SINGULAR__' => $singular->snake(' ')->title()->toString(), // E.g: User Category
            '__CRUD_UCFIRST_SINGULAR__' => $singular->snake(' ')->ucfirst()->toString(), // E.g: User category
            '__CRUD_LOWER_SINGULAR__' => $singular->snake(' ')->lower()->toString(), // E.g: user category
            '__CRUD_KEBAB_SINGULAR__' => $singular->kebab()->toString(),             // E.g: user-category
            '__CRUD_SNAKE_SINGULAR__' => $singular->snake()->toString(),             // E.g: user_category
            '__CRUD_SNAKE_UPPER_SINGULAR__' => $singular->snake()->upper()->toString(),    // E.g: USER_CATEGORY
            '__CRUD_PLAIN_SINGULAR__' => $singular->replace(' ', '')->lower()->toString(), // E.g: usercategory

            // ===== Plural =====
            '__CRUD_STUDLY_PLURAL__' => $plural->studly()->toString(),              // E.g: UserCategories
            '__CRUD_CAMEL_PLURAL__' => $plural->camel()->toString(),               // E.g: userCategories
            '__CRUD_TITLE_PLURAL__' => $plural->snake(' ')->title()->toString(),   // E.g: User Categories
            '__CRUD_UCFIRST_PLURAL__' => $plural->snake(' ')->ucfirst()->toString(), // E.g: User categories
            '__CRUD_LOWER_PLURAL__' => $plural->snake(' ')->lower()->toString(),   // E.g: user categories
            '__CRUD_KEBAB_PLURAL__' => $plural->kebab()->toString(),               // E.g: user-categories
            '__CRUD_SNAKE_PLURAL__' => $plural->snake()->toString(),               // E.g: user_categories
            '__CRUD_SNAKE_UPPER_PLURAL__' => $plural->snake()->upper()->toString(),      // E.g: USER_CATEGORIES
            '__CRUD_PLAIN_PLURAL__' => $plural->replace(' ', '')->lower()->toString(),   // E.g: usercategories
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

            $anchor = "{$namespace}\\Providers\\EventServiceProvider::class,";

            file_put_contents(config_path('app.php'), str_replace(
                $anchor.PHP_EOL,
                $anchor.PHP_EOL."        {$provider}::class,".PHP_EOL,
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
        return version_compare(Application::VERSION, '11.0.0', '<');
    }
}
