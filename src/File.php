<?php

namespace LaravelModules\ModuleGenerator;

class File
{
    /**
     * The file path that should be modified.
     */
    protected string $path;

    /**
     * The file content.
     */
    protected string $content;

    /**
     * Set the path of the file.
     */
    public function setPath(string $path): self
    {
        $this->path = $path;

        $this->setContent();

        return $this;
    }

    public function append(string $content): self
    {
        // Ensure that the content is defined in the file.
        if ($this->exists($content)) {
            return $this;
        }

        $this->content = empty($this->content) ? $content : $this->content . "\n" . $content;

        return $this;
    }

    public function prepend(string $content): self
    {
        // Ensure that the content is defined in the file.
        if ($this->exists($content)) {
            return $this;
        }

        $this->content = empty($this->content) ? $content : $content . "\n" . $this->content;

        return $this;
    }

    public function appendAfter(string $search, string $content): self
    {
        // Ensure that the content is defined in the file.
        if ($this->exists($content)) {
            return $this;
        }

        $escapedSearch = preg_quote($search, '/');
        preg_match_all("/^{$escapedSearch}(.*)/m", $this->content, $matches);

        if (! empty($matches[0][0]) && $after = $matches[0][0]) {
            $replacement = empty($after) ? $content : $after . "\n" . $content;
            $this->content = str_replace($after, $replacement, $this->content);
        } else {
            $this->append($content);
        }

        return $this;
    }
    public function prependBefore(string $search, string $content): self
    {
        // Ensure that the content is defined in the file.
        if ($this->exists($content)) {
            return $this;
        }

        $escapedSearch = preg_quote($search, '/');
        preg_match_all("/^{$escapedSearch}(.*)/m", $this->content, $matches);

        if (! empty($matches[0][0]) && $before = $matches[0][0]) {
            $replacement = empty($before) ? $content : $content . "\n" . $before;
            $this->content = str_replace($before, $replacement, $this->content);
        } else {
            $this->prepend($content);
        }

        return $this;
    }

    protected function setContent(): void
    {
        // Create parent directories if they don't exist
        $directory = dirname($this->path);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        // If file doesn't exist, create it with empty content
        if (!is_file($this->path)) {
            file_put_contents($this->path, '');
        }

        $content = file_get_contents($this->path);

        $this->content = $content;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Override the env file of the application.
     */
    public function publish(): void
    {
        file_put_contents($this->path, $this->content);
    }

    /**
     * Ensure that the file contains the given content.
     */
    protected function exists(string $content): bool
    {
        return str_contains($this->content, $content);
    }
}