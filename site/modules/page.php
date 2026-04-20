<?php

class Page
{
    private string $template;

    public function __construct(string $template)
    {
        if (!is_file($template)) {
            throw new InvalidArgumentException(
                "Template not found: {$template}",
            );
        }
        $contents = file_get_contents($template);
        if ($contents === false) {
            throw new RuntimeException("Failed to read template: {$template}");
        }
        $this->template = $contents;
    }

    public function Render(array $data): string
    {
        $rendered = $this->template;
        foreach ($data as $key => $value) {
            $rendered = str_replace(
                "{{" . $key . "}}",
                htmlspecialchars(
                    (string) $value,
                    ENT_QUOTES | ENT_SUBSTITUTE,
                    "UTF-8",
                ),
                $rendered,
            );
        }
        return $rendered;
    }
}
