<?php

namespace App\Mcp\Concerns;

trait NormalizesMoney
{
    /**
     * Normaliza um valor já validado (numeric, decimal:0,2) para a string
     * decimal exata de duas casas, sem passar por float.
     */
    protected function money(int|float|string $value): string
    {
        $string = (string) $value;

        if (! str_contains($string, '.')) {
            return $string.'.00';
        }

        [$integer, $decimals] = explode('.', $string, 2);

        return $integer.'.'.substr($decimals.'00', 0, 2);
    }
}
