<?php

namespace App\Services;

class CsvHeaderResolver
{
    /**
     * Build an index map from canonical field names to their column position in $headers.
     *
     * @param  array  $headers  First row of the CSV (raw strings).
     * @return array<string, int|null>  e.g. ['domain' => 2, 'application_type' => null]
     */
    public function resolve(array $headers): array
    {
        $normalized = array_map(
            fn(string $h) => strtolower(trim($h)),
            $headers
        );

        $map = [];

        foreach (config('csv_mappings.fields') as $field => $variants) {
            $map[$field] = null;

            foreach ($variants as $variant) {
                $needle = strtolower(trim($variant));
                $index  = array_search($needle, $normalized, strict: true);

                if ($index !== false) {
                    $map[$field] = $index;
                    break;
                }
            }
        }

        return $map;
    }
}
