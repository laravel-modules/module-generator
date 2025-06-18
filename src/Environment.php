<?php

namespace LaravelModules\ModuleGenerator;

class Environment extends File
{
    public function set(string $key, string $value = ''): self
    {
        $value = str_contains($value, ' ') ? sprintf('"%s"', $value) : $value;

        preg_match_all("/(^{$key})(.*)/m", $this->content, $matches);

        if (! empty($matches[1][0]) && $key = $matches[1][0]) {
            $this->content = str_replace($matches[0][0], "$key=$value", $this->content);
        } else {
            $this->append("$key=$value\n");
        }

        return $this;
    }
}