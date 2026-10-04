<?php

namespace App\Support;

class TimelineLogLevelFilter
{
    public const DISPLAY_TO_RAW = [
        'debug' => ['trace', 'debug'],
        'info' => ['info'],
        'success' => ['success'],
        'warning' => ['warn', 'warning'],
        'error' => ['error', 'fatal'],
    ];

    /**
     * Parse a comma-separated list into unique, trimmed display levels.
     *
     * @param  string  $value
     * @return array
     */
    public static function parse($value)
    {
        return array_values(array_unique(array_map('trim', explode(',', $value))));
    }

    /**
     * Return all recognized stored values, including supported legacy values.
     *
     * @return array
     */
    public static function recognizedRawValues()
    {
        return array_values(array_unique(array_merge(...array_values(self::DISPLAY_TO_RAW))));
    }

    /**
     * Expand display levels to their stored values.
     *
     * @param  array  $levels
     * @return array
     */
    public static function rawValues(array $levels)
    {
        $values = [];

        foreach ($levels as $level) {
            $values = array_merge($values, self::DISPLAY_TO_RAW[$level] ?? []);
        }

        return array_values(array_unique($values));
    }
}
