<?php

namespace Arris\Toolkit\EmojiFinder\Utils;

final class PreProcessString
{
    /**
     * Pre-process a keyword string to help with search
     *
     * It performs the following operations:
     * - Remove """:;(),.!? characters
     * - Replace - with space
     * - Replace ' with '
     * - Convert to lowercase
     */
    public static function process(string $str): string
    {
        $str = preg_replace('/[""":;(),.!?]/u', '', $str);
        $str = str_replace('-', ' ', $str);
        $str = str_replace('\'', "'", $str);

        return mb_strtolower($str);
    }
}