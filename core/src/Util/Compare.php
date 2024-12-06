<?php

/**
 * Copyright (c) 2017 Public Consulting Group.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Util;

class Compare
{
    /**
     * Sort an array using multiple fields.
     *
     * @param array<array-key, array> $itemArray
     * @param array<array-key, string> $fields
     */
    public static function sortArrayByFields(array &$itemArray, array $fields): void
    {
        uasort($itemArray, static function ($a, $b) use ($fields) {
            foreach ($fields as $field) {
                if (0 !== ($ret = Compare::arrayCompare($a, $b, $field))) {
                    return $ret;
                }
            }

            return 0;
        });
    }

    /**
     * Compare two array fields.
     */
    public static function arrayCompare(array $a, array $b, int|string $key, bool $setValueIsLower = true): int
    {
        if ('sequenceNumber' === $key) {
            return self::sequenceNumberIsSetInArray($a, $b, $setValueIsLower);
        }

        if (!isset($a[$key]) && !isset($b[$key])) {
            return 0;
        }

        if (0 !== ($ret = self::isSetInArray($a, $b, $key, $setValueIsLower))) {
            return $ret;
        }

        $x = $a[$key];
        $y = $b[$key];

        if ((string) $x === (string) $y) {
            return 0;
        }

        if (is_numeric($x) && is_numeric($y)) {
            return ((float) $x < (float) $y) ? -1 : 1;
        }

        $xa = preg_split('/[\s.,-]/', $x);
        $ya = preg_split('/[\s.,-]/', $y);
        $len = is_countable($xa) ? \count($xa) : 0;
        if ($len < (is_countable($ya) ? \count($ya) : 0)) {
            $len = is_countable($ya) ? \count($ya) : 0;
        }
        if (1 < $len) {
            for ($idx = 0; $idx < $len; ++$idx) {
                if (0 !== ($ret = self::arrayCompare($xa, $ya, $idx, false))) {
                    return $ret;
                }
            }
        }

        return strnatcmp($x, $y);
    }

    /**
     * Returns
     * < 0 if $a[$key] is set and $b[$key] is not,
     * > 0 if $b[$key] is set and $a[$key] is not,
     * 0 if both are set or unset.
     */
    public static function isSetInArray(array $a, array $b, int|string $key, bool $setValueIsLower = true): int
    {
        $dir = ($setValueIsLower ? 1 : -1);

        $x = isset($a[$key]) ? 1 : 0;
        $y = isset($b[$key]) ? 1 : 0;

        return ($x - $y) * $dir;
    }

    public static function sequenceNumberIsSetInArray(array $a, array $b, bool $setValueIsLower = true): int
    {
        $dir = ($setValueIsLower ? 1 : -1);

        $x = $a['sequenceNumber'] ?? current($a['associations'] ?? [['sequenceNumber' => 0]])['sequenceNumber'] ?? 0;
        $y = $b['sequenceNumber'] ?? current($b['associations'] ?? [['sequenceNumber' => 0]])['sequenceNumber'] ?? 0;

        return ($x - $y) * $dir;
    }
}
