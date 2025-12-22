<?php

namespace Burningyolo\LaravelHttpMonitor\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

class BodyProcessor
{
    public static function process(?string $body, ?Request $request = null): ?string
    {
        if (empty($body)) {
            return null;
        }

        $maxSize = Config::get('request-tracker.max_body_size', 65536);
        
    
        if (strlen($body) > $maxSize) {
            return substr($body, 0, $maxSize) . '... [truncated]';
        }

        $data = self::extractData($body, $request);

        if (is_array($data)) {
            return self::processArrayData($data, $maxSize, $body);
        }

        // Not JSON or failed to decode, just return as-is (pehaly se size checked)
        return $body;
    }


    protected static function extractData(string $body, ?Request $request = null): ?array
    {
    
        if ($request instanceof Request) {
            $contentType = $request->header('Content-Type', '');
            
            if (Str::contains($contentType, ['application/json', 'application/ld+json'])) {
                $jsonData = $request->json()->all();
                if (!empty($jsonData)) {
                    return $jsonData;
                }
            } elseif (Str::contains($contentType, ['application/x-www-form-urlencoded', 'multipart/form-data'])) {
                $formData = $request->all();
                if (!empty($formData)) {
                    return $formData;
                }
            }
        }

        $decoded = json_decode($body, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
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
        
        if ($encoded !== false && strlen($encoded) <= $maxSize) {
            return $encoded;
        }
        
        
        $bodyToTruncate = $encoded !== false ? $encoded : $originalBody;
        return substr($bodyToTruncate, 0, $maxSize) . '... [truncated]';
    }

    protected static function omitSensitiveFields(array $data, array $fieldsToOmit): array
    {
        $result = [];

        foreach ($data as $key => $value) {
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
        if (!is_string($key)) {
            return false;
        }

        foreach ($fieldsToOmit as $omitField) {
            // Case-insensitive exact match or contains check
            if (strcasecmp($key, $omitField) === 0 || 
                stripos($key, $omitField) !== false) {
                return true;
            }
        }

        return false;
    }
}