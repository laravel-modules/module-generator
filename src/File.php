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
    protected string $content = '';

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

        $this->content = empty($this->content) ? $content : $this->content."\n".$content;

        return $this;
    }

    public function prepend(string $content): self
    {
        // Ensure that the content is defined in the file.
        if ($this->exists($content)) {
            return $this;
        }

        $this->content = empty($this->content) ? $content : $content."\n".$this->content;

        return $this;
    }

    public function appendAfter(string $search, string $content): self
    {
        // Ensure that the content is defined in the file.
        if ($this->exists($content)) {
            return $this;
        }

        $escapedSearch = preg_quote($search, '/');

        if (preg_match("/^{$escapedSearch}.*/m", $this->content, $matches)) {
            $line = $matches[0];
            $this->content = $this->replaceFirst($line, $line."\n".$content, $this->content);
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

        if (preg_match("/^{$escapedSearch}.*/m", $this->content, $matches)) {
            $line = $matches[0];
            $this->content = $this->replaceFirst($line, $content."\n".$line, $this->content);
        } else {
            $this->prepend($content);
        }

        return $this;
    }

    public function replace(string $search, string $replace): self
    {
        $this->content = str_replace($search, $replace, $this->content);

        return $this;
    }

    protected function setContent(): void
    {
        // Read the current content when the file exists. Missing files are
        // treated as empty and only written to disk on publish().
        $this->content = is_file($this->path) ? file_get_contents($this->path) : '';
    }

    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Write the accumulated content to the file, creating it if needed.
     */
    public function publish(): void
    {
        $directory = dirname($this->path);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($this->path, $this->content);
    }

    /**
     * Ensure that the file contains the given content.
     */
    protected function exists(string $content): bool
    {
        return str_contains($this->content, $content);
    }

    /**
     * Replace the first occurrence of the given value within the subject.
     */
    protected function replaceFirst(string $search, string $replace, string $subject): string
    {
        $position = strpos($subject, $search);

        if ($position === false) {
            return $subject;
        }

        return substr_replace($subject, $replace, $position, strlen($search));
    }
}
