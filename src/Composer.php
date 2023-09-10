<?php

namespace B5Digital\ModuleGenerator;

class Composer
{
    /**
     * The composer path that should be modified.
     */
    protected string $path;

    /**
     * The composer file content.
     */
    protected array $composer = [];

    /**
     * The composer file original content.
     */
    protected array $originalComposer = [];

    /**
     * Write backup composer when override content.
     */
    protected bool $allowBackup = false;

    /**
     * Set the path of the composer file.
     *
     * @throws \Exception
     */
    public function setPath(string $path): self
    {
        $this->path = $path;

        $this->setContent();

        return $this;
    }

    /**
     * Merge the given packages to composer require packages.
     */
    public function mergeRequire(array $packages): self
    {
        $require = $this->composer['require'] ?? [];

        $this->composer['require'] = array_merge($require, $packages);

        return $this;
    }

    /**
     * Remove the given packages from composer.
     */
    public function removePackages(array $packages): self
    {
        $require = $this->composer['require'] ?? [];

        $requireDev = $this->composer['require-dev'] ?? [];

        foreach ($packages as $package) {
            if (isset($require[$package])) unset($require[$package]);
            if (isset($requireDev[$package])) unset($requireDev[$package]);
        }

        $this->composer['require'] = $require;
        $this->composer['require-dev'] = $requireDev;

        return $this;
    }

    /**
     * Merge the given packages to composer require-dev packages.
     */
    public function mergeRequireDev(array $packages): self
    {
        $require = $this->composer['require-dev'] ?? [];

        $this->composer['require-dev'] = array_merge($require, $packages);

        return $this;
    }

    /**
     * Merge the given files to composer autoload.
     */
    public function mergeAutoloadFiles(array $files): self
    {
        $autoloadFiles = $this->composer['autoload']['files'] ?? [];

        $this->composer['autoload']['files'] = array_merge($autoloadFiles, $files);

        return $this;
    }

    /**
     * Merge the given scripts to composer scripts.
     */
    public function mergeScripts(array $scripts): self
    {
        $composerScripts = $this->composer['scripts'] ?? [];

        $this->composer['scripts'] = array_merge($composerScripts, $scripts);

        return $this;
    }

    /**
     * Remove the given scripts from composer.
     */
    public function removeScripts(array $keys): self
    {
        $scripts = $this->composer['scripts'] ?? [];

        foreach ($keys as $key) {
            if (isset($scripts[$key])) unset($scripts[$key]);
        }

        $this->composer['scripts'] = $scripts;

        return $this;
    }

    /**
     * @throws \Exception
     */
    protected function setContent(): void
    {
        // Ensure that the composer file is correct.
        if (! $this->path || ! is_file($this->path)) {
            throw new \Exception('The composer path is not correct!');
        }

        // Ensure that the composer file contains a valid json.
        if (! is_array($content = @json_decode(file_get_contents($this->path), true))) {
            throw new \Exception('The composer file is invalid!');
        }

        $this->originalComposer = $content;

        $this->composer = $content;
    }

    public function getContent(): array
    {
        return $this->composer;
    }

    public function getOriginalContent(): array
    {
        return $this->originalComposer;
    }

    /**
     * Save original composer as a backup.
     */
    public function withBackup(): self
    {
        $this->allowBackup = true;

        return $this;
    }

    /**
     * Disable saving original composer as a backup.
     */
    public function withoutBackup(): self
    {
        $this->allowBackup = false;

        return $this;
    }

    /**
     * Override the composer file of the application.
     */
    public function publish(): void
    {
        // Save backup copy if the "allowBackup" flag equals "true"
        if ($this->allowBackup) {

            $original = json_encode($this->originalComposer, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);

            $backupPath = sprintf('%s/composer-backup.json', dirname($this->path));

            file_put_contents($backupPath, $original);
        }

        // Convert the composer array to pretty json.
        $json = json_encode($this->composer, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);

        // Override the composer file.
        file_put_contents($this->path, $json);
    }
}