<?php

namespace App\Automation\Templates;

use App\Models\CommunicationTemplate;
use InvalidArgumentException;

class TemplateEngine
{
    /**
     * Render template body and subject with variables.
     */
    public static function render(string $templateContent, array $variables = []): string
    {
        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', function ($matches) use ($variables) {
            $key = $matches[1];
            if (array_key_exists($key, $variables)) {
                $val = $variables[$key];
                if (is_numeric($val) && strpos($key, 'amount') !== false) {
                    return number_format((float)$val, 2);
                }
                return htmlspecialchars((string)$val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }
            return $matches[0]; // Retain unchanged if unprovided
        }, $templateContent);
    }

    /**
     * Validate that all required variables in template are provided.
     */
    public static function validate(string $templateContent, array $providedVariables = []): array
    {
        preg_match_all('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', $templateContent, $matches);
        $extractedVars = array_unique($matches[1] ?? []);

        $missing = [];
        foreach ($extractedVars as $var) {
            if (!array_key_exists($var, $providedVariables) || $providedVariables[$var] === null || $providedVariables[$var] === '') {
                $missing[] = $var;
            }
        }

        return [
            'is_valid' => empty($missing),
            'missing_variables' => $missing,
            'variables_found' => $extractedVars,
        ];
    }
}
