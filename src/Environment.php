<?php

namespace B5Digital\ModuleGenerator;

class Environment
{
    /**
     * The env file path that should be modified.
     */
    protected string $path;

    /**
     * The env file content.
     */
    protected string $env;

    /**
     * The env file original content.
     */
    protected string $originalEnv;

    /**
     * Write backup env file when override content.
     */
    protected bool $allowBackup = false;

    /**
     * Set the path of the env file.
     *
     * @throws \Exception
     */
    public function setPath(string $path): self
    {
        $this->path = $path;

        $this->setContent();

        return $this;
    }

    public function append(string $variable): self
    {
        // Ensure that the variable is defined in the env file.
        if ($this->exists($variable)) {
            return $this;
        }

        $this->env = $this->env.sprintf("\n%s", $variable);

        return $this;
    }

    public function appendAfter(string $key, string $variable): self
    {
        // Ensure that the variable is defined in the env file.
        if ($this->exists($variable)) {
            return $this;
        }

        preg_match_all("/^{$key}(.*)/m", $this->env, $matches);

        if (! empty($matches[0][0]) && $afterVariable = $matches[0][0]) {
            $this->env = str_replace($afterVariable, "$afterVariable\n$variable", $this->env);
        } else {
            $this->append($variable);
        }

        return $this;
    }

    public function set(string $key, string $value = ''): self
    {
        $value = str_contains($value, ' ') ? sprintf('"%s"', $value) : $value;

        preg_match_all("/(^{$key})(.*)/m", $this->env, $matches);

        if (! empty($matches[1][0]) && $key = $matches[1][0]) {
            $this->env = str_replace($matches[0][0], "$key=$value", $this->env);
        } else {
            $this->append("$key=$value\n");
        }

        return $this;
    }

    /**
     * @throws \Exception
     */
    protected function setContent(): void
    {
        // Ensure that the rnv file path is correct.
        if (! $this->path || ! is_file($this->path)) {
            throw new \Exception('The env path is not correct!');
        }

        $content = file_get_contents($this->path);

        $this->originalEnv = $content;

        $this->env = $content;
    }

    public function getContent(): string
    {
        return $this->env;
    }

    public function getOriginalContent(): string
    {
        return $this->originalEnv;
    }

    /**
     * Save original env as a backup.
     */
    public function withBackup(): self
    {
        $this->allowBackup = true;

        return $this;
    }

    /**
     * Disable saving original env as a backup.
     */
    public function withoutBackup(): self
    {
        $this->allowBackup = false;

        return $this;
    }

    /**
     * Override the env file of the application.
     */
    public function publish(): void
    {
        // Save backup copy if the "allowBackup" flag equals "true"
        if ($this->allowBackup) {
            $original = $this->originalEnv;

            $backupPath = sprintf('%s/.env.backup', dirname($this->path));

            file_put_contents($backupPath, $original);
        }

        // Override the env file.
        file_put_contents($this->path, $this->env);
    }

    /**
     * Ensure that the variable is defined in the env file.
     */
    protected function exists(string $variable): bool
    {
        return str_contains($this->env, explode('=', $variable)[0]);
    }
}