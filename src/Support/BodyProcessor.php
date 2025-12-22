<?php

namespace Burningyolo\LaravelHttpMonitor\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

class BodyProcessor
{
    public static function process(?string $body, ?Request $request = null): ?string
    {
         if(!is_string($body))
            {
                return null ; 
            }  

        $maxSize = Config::get('request-tracker.max_body_size', 65536);

        $data = self::extractData($body , $request);

        if (is_array($data)) {
            return self::processArrayData($data, $maxSize, $body ?? '');
        }

         // Not JSON or failed to decode, just return as-is (pehaly se size checked)
        if (strlen($body) > $maxSize) {
            return substr($body, 0, $maxSize).'... [truncated]';
        }

        return $body;
    }

protected static function extractData(string $body, ?Request $request = null): ?array
{
    if ($request instanceof Request) {
        if ($request->isJson()) {
            $json = $request->json()->all();
            if (! empty($json)) {
                return $json;
            }
        }

        $input = $request->all();
        if (! empty($input)) {
            return $input;
        }
    }

    if (! empty($body) || $body === '0') {
        $decoded = json_decode($body, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }
    }

    return null;
}

    /**
     * Process array data by omitting sensitive fields  (functions probably nhi chahiye but it aight)
     */
protected static function processArrayData(array $data, int $maxSize, string $originalBody): string
    {
        $omittedFields = Config::get('request-tracker.omit_body_fields', []);

        $processed = self::omitSensitiveFields($data, $omittedFields);
        $encoded = json_encode($processed, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($encoded === false) {
            // JSON encoding failed, truncate original
            if (strlen($originalBody) > $maxSize) {
                return substr($originalBody, 0, $maxSize).'... [truncated]';
            }
            return $originalBody;
        }

        // Truncate if needed
        if (strlen($encoded) > $maxSize) {
            return substr($encoded, 0, $maxSize).'... [truncated]';
        }

        return $encoded;
    }


 protected static function omitSensitiveFields(array $data, array $fieldsToOmit): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            // Skip numeric keys (array indices) - only check string keys
            if (is_int($key)) {
                if (is_array($value)) {
                    $result[$key] = self::omitSensitiveFields($value, $fieldsToOmit);
                } else {
                    $result[$key] = $value;
                }
                continue;
            }

            if (self::shouldOmitField($key, $fieldsToOmit)) {
                $result[$key] = '***OMITTED***';
            } elseif (is_array($value)) {
                // Recursive maro nested arrays kay liye
                $result[$key] = self::omitSensitiveFields($value, $fieldsToOmit);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    protected static function shouldOmitField($key, array $fieldsToOmit): bool
    {
        // Skip non-string keys
        if (! is_string($key)) {
            return false;
        }

        foreach ($fieldsToOmit as $omitField) {
            // Case-insensitive partial match (contains check)
            if (stripos($key, $omitField) !== false) {
                return true;
            }
        }

        return false;
    }


    // tests mein use kring ye fucntions , probably better way to do this but for now it aight
    public static function getOmittedFields(): array
    {
        return Config::get('request-tracker.omit_body_fields', []);
    }

    public static function getMaxBodySize(): int
    {
        return Config::get('request-tracker.max_body_size', 65536);
    }
}
