<?php

namespace Arris\Toolkit\EmojiFinder\Utils\NLP;

/**
 * Porter Stemmer for English
 *
 * Based on Snowball stemming algorithm
 * Reference: https://snowballstem.org/texts/introduction.html
 */
final class StemWord
{
    /**
     * Custom rules applied to the snowball stemming algorithm.
     *
     * @var array<int, array{string, string, ?int}>
     */
    private const CUSTOM_RULES = [
        ['y', 'i', null],
        ['Y', 'i', null],
        ['ying', 'i', -3],
        ['yings', 'i', -4],
        ['ing', 'e', -3],
        ['ings', 'e', -4],
        ['ingly', 'e', -5],
        ['ility', 'l', -4],
        ['ilities', 'l', -6],
        ['ys', 'i', -1],
        ['est', 'est', -3],
    ];

    /**
     * Stem a word using the Porter stemming algorithm.
     */
    public static function stem(string $word): string
    {
        $stemmedWord = self::porterStem($word);

        foreach (self::CUSTOM_RULES as [$wordSuffix, $stemmedWordSuffix, $sliceEnd]) {
            if (
                str_ends_with($word, $wordSuffix) &&
                str_ends_with($stemmedWord, $stemmedWordSuffix)
            ) {
                if ($sliceEnd === null) {
                    return $word;
                }
                return mb_substr($word, 0, $sliceEnd);
            }
        }

        return $stemmedWord;
    }

    /**
     * Porter Stemmer implementation
     */
    private static function porterStem(string $word): string
    {
        // Step 1a
        $word = self::step1a($word);

        // Step 1b
        $word = self::step1b($word);

        // Step 1c
        $word = self::step1c($word);

        // Step 2
        $word = self::step2($word);

        // Step 3
        $word = self::step3($word);

        // Step 4
        $word = self::step4($word);

        // Step 5
        $word = self::step5($word);

        return $word;
    }

    private static function isVowel(string $char): bool
    {
        return in_array($char, ['a', 'e', 'i', 'o', 'u']);
    }

    private static function isConsonant(string $char): bool
    {
        return !self::isVowel($char) && ctype_alpha($char);
    }

    private static function measure(string $word): int
    {
        $measure = 0;
        $vowel = false;
        $len = mb_strlen($word);

        for ($i = 0; $i < $len; $i++) {
            if (self::isVowel($word[$i])) {
                $vowel = true;
            } elseif ($vowel) {
                $measure++;
                $vowel = false;
            }
        }

        return $measure;
    }

    private static function containsVowel(string $word): bool
    {
        $len = mb_strlen($word);
        for ($i = 0; $i < $len; $i++) {
            if (self::isVowel($word[$i])) {
                return true;
            }
        }
        return false;
    }

    private static function cvc(string $word): bool
    {
        $len = mb_strlen($word);
        if ($len < 3) {
            return false;
        }

        $last3 = mb_substr($word, -3);
        if (
            self::isConsonant($last3[0]) &&
            self::isVowel($last3[1]) &&
            self::isConsonant($last3[2]) &&
            !in_array($last3[2], ['w', 'x', 'y'])
        ) {
            return true;
        }

        return false;
    }

    private static function doubleConsonant(string $word): bool
    {
        $len = mb_strlen($word);
        if ($len < 2) {
            return false;
        }

        $last2 = mb_substr($word, -2);
        return $last2[0] === $last2[1] && self::isConsonant($last2[0]);
    }

    private static function step1a(string $word): string
    {
        if (str_ends_with($word, 'sses')) {
            return mb_substr($word, 0, -2);
        }
        if (str_ends_with($word, 'ies')) {
            return mb_substr($word, 0, -2);
        }
        if (str_ends_with($word, 'ss')) {
            return $word;
        }
        if (str_ends_with($word, 's')) {
            return mb_substr($word, 0, -1);
        }
        return $word;
    }

    private static function step1b(string $word): string
    {
        $original = $word;

        if (str_ends_with($word, 'eed')) {
            if (self::measure(mb_substr($word, 0, -3)) > 0) {
                return mb_substr($word, 0, -1);
            }
            return $word;
        }

        if (str_ends_with($word, 'ed')) {
            $stem = mb_substr($word, 0, -2);
            if (self::containsVowel($stem)) {
                $word = $stem;
            }
        } elseif (str_ends_with($word, 'ing')) {
            $stem = mb_substr($word, 0, -3);
            if (self::containsVowel($stem)) {
                $word = $stem;
            }
        }

        if ($word !== $original) {
            if (str_ends_with($word, 'at') || str_ends_with($word, 'bl') || str_ends_with($word, 'iz')) {
                return $word . 'e';
            }
            if (self::doubleConsonant($word) && !in_array(mb_substr($word, -1), ['l', 's', 'z'])) {
                return mb_substr($word, 0, -1);
            }
            if (self::measure($word) === 1 && self::cvc($word)) {
                return $word . 'e';
            }
        }

        return $word;
    }

    private static function step1c(string $word): string
    {
        if (str_ends_with($word, 'y') && self::containsVowel(mb_substr($word, 0, -1))) {
            return mb_substr($word, 0, -1) . 'i';
        }
        return $word;
    }

    private static function step2(string $word): string
    {
        $patterns = [
            'ational' => 'ate',
            'tional' => 'tion',
            'enci' => 'ence',
            'anci' => 'ance',
            'izer' => 'ize',
            'abli' => 'able',
            'alli' => 'al',
            'entli' => 'ent',
            'eli' => 'e',
            'ousli' => 'ous',
            'ization' => 'ize',
            'ation' => 'ate',
            'ator' => 'ate',
            'alism' => 'al',
            'iveness' => 'ive',
            'fulness' => 'ful',
            'ousness' => 'ous',
            'aliti' => 'al',
            'iviti' => 'ive',
            'biliti' => 'ble',
        ];

        foreach ($patterns as $suffix => $replacement) {
            if (str_ends_with($word, $suffix)) {
                $stem = mb_substr($word, 0, -strlen($suffix));
                if (self::measure($stem) > 0) {
                    return $stem . $replacement;
                }
            }
        }

        return $word;
    }

    private static function step3(string $word): string
    {
        $patterns = [
            'icate' => 'ic',
            'ative' => '',
            'alize' => 'al',
            'iciti' => 'ic',
            'ical' => 'ic',
            'ful' => '',
            'ness' => '',
        ];

        foreach ($patterns as $suffix => $replacement) {
            if (str_ends_with($word, $suffix)) {
                $stem = mb_substr($word, 0, -strlen($suffix));
                if (self::measure($stem) > 0) {
                    return $stem . $replacement;
                }
            }
        }

        return $word;
    }

    private static function step4(string $word): string
    {
        $suffixes = ['al', 'ance', 'ence', 'er', 'ic', 'able', 'ible', 'ant',
            'ement', 'ment', 'ent', 'ou', 'ism', 'ate', 'iti', 'ous', 'ive', 'ize'];

        foreach ($suffixes as $suffix) {
            if (str_ends_with($word, $suffix)) {
                $stem = mb_substr($word, 0, -strlen($suffix));
                if (self::measure($stem) > 1) {
                    return $stem;
                }
            }
        }

        if (str_ends_with($word, 'ion')) {
            $stem = mb_substr($word, 0, -3);
            if (self::measure($stem) > 1 && in_array(mb_substr($stem, -1), ['s', 't'])) {
                return $stem;
            }
        }

        return $word;
    }

    private static function step5(string $word): string
    {
        if (str_ends_with($word, 'e')) {
            $stem = mb_substr($word, 0, -1);
            $measure = self::measure($stem);
            if ($measure > 1 || ($measure === 1 && !self::cvc($stem))) {
                return $stem;
            }
        }

        if (str_ends_with($word, 'l') && self::doubleConsonant($word) && self::measure($word) > 1) {
            return mb_substr($word, 0, -1);
        }

        return $word;
    }
}