<?php

namespace LaravelModules\ModuleGenerator;

class Environment extends File
{
    public function set(string $key, string|float|null $value = ''): self
    {
        $value = (string) $value;

        $value = str_contains($value, ' ') ? sprintf('"%s"', $value) : $value;

        $escapedKey = preg_quote($key, '/');

        // Replace the existing entry when the key is already present,
        // otherwise append a new "KEY=VALUE" line to the file.
        if (preg_match("/^{$escapedKey}=.*/m", $this->content, $matches)) {
            $this->content = $this->replaceFirst($matches[0], "$key=$value", $this->content);
        } else {
            $this->append("$key=$value");
        }

        return $this;
    }
}
