<?php

namespace LaravelModules\ModuleGenerator;

class CRUD
{
    protected string $fromPath = '';
    protected string $toPath = '';

    protected array $replacements = [];

    protected Generator $generator;
    public function __construct(
        protected string $name,
    ) {

    }

    public function setGenerator(Generator $generator): self
    {
        $this->generator = $generator;

        return $this;
    }

    public function fromPath(string $fromPath): self
    {
        $this->fromPath = $fromPath;

        return $this;
    }
    public function toPath(string $fromPath): self
    {
        $this->toPath = $fromPath;

        return $this;
    }
    public function setReplacement(array $replacements): self
    {
        $this->replacements = $replacements;

        return $this;
    }

    public function appendReplacements(array $replacements): self
    {
        $this->replacements = array_merge($this->replacements, $replacements);

        return $this;
    }

    public function appendToFile(string $file, string $content, string $before = ''): self
    {
        $this->generator->file($file)
            ->prependBefore(
                search: $before,
                content: str_replace(array_keys($this->replacements), array_values($this->replacements), $content)
            )->publish();

        return $this;
    }

    public function publish(): void
    {
        $replacements = [
            '.stub' => '',
            'create___CRUD_SNAKE_PLURAL___table' => date('Y_m_d_His') . '_create___CRUD_SNAKE_PLURAL___table',
            ...$this->replacements,
        ];

        $this->generator->publish(
            from: $this->fromPath,
            to: $this->toPath,
            filesNameReplacement: $replacements,
            filesContentReplacement: $replacements,
        );
    }
}