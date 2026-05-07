<?php

declare(strict_types=1);

namespace Arris\Toolkit\EmojiFinder\Utils\NLP;

/**
 * Porter Stemmer for English
 *
 * Based on Snowball stemming algorithm
 * Reference: https://snowballstem.org/texts/introduction.html
 *
 * This is a PHP port of the original Snowball English stemmer.
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
                self::endsWith($word, $wordSuffix) &&
                self::endsWith($stemmedWord, $stemmedWordSuffix)
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
     * Multibyte-safe ends_with
     */
    private static function endsWith(string $haystack, string $needle): bool
    {
        $length = mb_strlen($needle);
        if ($length === 0) {
            return true;
        }
        return mb_substr($haystack, -$length) === $needle;
    }

    /**
     * Porter Stemmer implementation
     * This is a direct port from the Snowball C implementation
     */
    private static function porterStem(string $word): string
    {
        if (strlen($word) <= 2) {
            return $word;
        }

        // Special case words that should not be stemmed
        $exceptions = [
            'succeed', 'proceed', 'exceed', 'canning', 'inning',
            'earring', 'herring', 'outing',
        ];

        if (in_array($word, $exceptions)) {
            return $word;
        }

        $word = self::step1a($word);
        $word = self::step1b($word);
        $word = self::step1c($word);
        $word = self::step2($word);
        $word = self::step3($word);
        $word = self::step4($word);
        $word = self::step5a($word);
        $word = self::step5b($word);

        return $word;
    }

    /**
     * Checks if a letter is a vowel
     */
    private static function isVowel(string $word, int $index): bool
    {
        $letter = $word[$index];

        if ($letter === 'a' || $letter === 'e' || $letter === 'i' ||
            $letter === 'o' || $letter === 'u') {
            return true;
        }

        if ($letter === 'y') {
            return $index === 0 ? false : self::isConsonant($word, $index - 1);
        }

        return false;
    }

    /**
     * Checks if a letter is a consonant
     */
    private static function isConsonant(string $word, int $index): bool
    {
        return !self::isVowel($word, $index);
    }

    /**
     * Counts the number of VC sequences (measure)
     */
    private static function measureWord(string $word): int
    {
        $measure = 0;
        $len = strlen($word);

        if ($len < 2) {
            return 0;
        }

        $prevConsonant = self::isConsonant($word, 0);

        for ($i = 1; $i < $len; $i++) {
            $isCons = self::isConsonant($word, $i);

            if ($prevConsonant && !$isCons) {
                // End of consonant sequence, start of vowel sequence
                $measure++;
            }

            $prevConsonant = $isCons;
        }

        return $measure;
    }

    /**
     * Checks if word contains a vowel
     */
    private static function hasVowel(string $word): bool
    {
        $len = strlen($word);
        for ($i = 0; $i < $len; $i++) {
            if (self::isVowel($word, $i)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Checks if word ends in double consonant
     */
    private static function endsDoubleConsonant(string $word): bool
    {
        $len = strlen($word);
        if ($len < 2) {
            return false;
        }

        $last = $word[$len - 1];
        $prev = $word[$len - 2];

        return $last === $prev && self::isConsonant($word, $len - 1);
    }

    /**
     * Checks if word ends in cvc pattern
     */
    private static function endsCvc(string $word): bool
    {
        $len = strlen($word);
        if ($len < 3) {
            return false;
        }

        $c1 = self::isConsonant($word, $len - 3);
        $v1 = self::isVowel($word, $len - 2);
        $c2 = self::isConsonant($word, $len - 1);

        $last = $word[$len - 1];

        return $c1 && $v1 && $c2 && $last !== 'w' && $last !== 'x' && $last !== 'y';
    }

    /**
     * Step 1a: Handle plurals and -ed/-ing
     */
    private static function step1a(string $word): string
    {
        // SSES -> SS
        if (self::endsWith($word, 'sses')) {
            return mb_substr($word, 0, -2);
        }

        // IES -> I
        if (self::endsWith($word, 'ies')) {
            return mb_substr($word, 0, -2);
        }

        // SS -> SS (do nothing)
        if (self::endsWith($word, 'ss')) {
            return $word;
        }

        // S -> (remove)
        if (self::endsWith($word, 's') && !self::endsWith($word, 'us') && !self::endsWith($word, 'ss')) {
            return mb_substr($word, 0, -1);
        }

        return $word;
    }

    /**
     * Step 1b: Handle -ed and -ing
     */
    private static function step1b(string $word): string
    {
        // Check for -eed
        if (self::endsWith($word, 'eed')) {
            $stem = mb_substr($word, 0, -3);
            if (self::measureWord($stem) > 0) {
                return $stem . 'ee';
            }
            return $word;
        }

        // Check for -eedly
        if (self::endsWith($word, 'eedly')) {
            $stem = mb_substr($word, 0, -5);
            if (self::measureWord($stem) > 0) {
                return $stem . 'ee';
            }
            return $word;
        }

        // Handle -ed and -ing
        $hasEd = self::endsWith($word, 'ed');
        $hasIng = self::endsWith($word, 'ing');
        $hasEdly = self::endsWith($word, 'edly');
        $hasIngly = self::endsWith($word, 'ingly');

        if (!$hasEd && !$hasIng && !$hasEdly && !$hasIngly) {
            return $word;
        }

        if ($hasEdly) {
            $suffixLen = 4;
        } elseif ($hasIngly) {
            $suffixLen = 5;
        } elseif ($hasEd) {
            $suffixLen = 2;
        } else {
            $suffixLen = 3;
        }

        $stem = mb_substr($word, 0, -$suffixLen);

        if (!self::hasVowel($stem)) {
            return $word;
        }

        $word = $stem;

        // AT, BL, IZ -> add E
        if (self::endsWith($word, 'at') ||
            self::endsWith($word, 'bl') ||
            self::endsWith($word, 'iz')) {
            return $word . 'e';
        }

        // Double consonant (not L, S, Z) -> remove last
        if (self::endsDoubleConsonant($word)) {
            $last = $word[strlen($word) - 1];
            if ($last !== 'l' && $last !== 's' && $last !== 'z') {
                return mb_substr($word, 0, -1);
            }
        }

        // *o -> add E
        if (self::measureWord($word) === 1 && self::endsCvc($word)) {
            return $word . 'e';
        }

        return $word;
    }

    /**
     * Step 1c: Handle -y
     */
    private static function step1c(string $word): string
    {
        if (self::endsWith($word, 'y')) {
            $stem = mb_substr($word, 0, -1);
            if (self::hasVowel($stem)) {
                return $stem . 'i';
            }
        }
        return $word;
    }

    /**
     * Step 2: Handle double suffixes
     */
    private static function step2(string $word): string
    {
        $suffixes = [
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

        foreach ($suffixes as $suffix => $replacement) {
            if (self::endsWith($word, $suffix)) {
                $stem = mb_substr($word, 0, -mb_strlen($suffix));
                if (self::measureWord($stem) > 0) {
                    return $stem . $replacement;
                }
                return $word;
            }
        }

        return $word;
    }

    /**
     * Step 3: Handle -ic-, -full, -ness etc
     */
    private static function step3(string $word): string
    {
        $suffixes = [
            'icate' => 'ic',
            'ative' => '',
            'alize' => 'al',
            'iciti' => 'ic',
            'ical' => 'ic',
            'ful' => '',
            'ness' => '',
        ];

        foreach ($suffixes as $suffix => $replacement) {
            if (self::endsWith($word, $suffix)) {
                $stem = mb_substr($word, 0, -mb_strlen($suffix));
                if (self::measureWord($stem) > 0) {
                    return $stem . $replacement;
                }
                return $word;
            }
        }

        return $word;
    }

    /**
     * Step 4: Handle -al, -ance, -ence etc
     */
    private static function step4(string $word): string
    {
        $suffixes = [
            'al', 'ance', 'ence', 'er', 'ic', 'able', 'ible', 'ant',
            'ement', 'ment', 'ent', 'ou', 'ism', 'ate', 'iti', 'ous', 'ive', 'ize'
        ];

        // Handle -ion first
        if (self::endsWith($word, 'ion')) {
            $stem = mb_substr($word, 0, -3);
            if (self::measureWord($stem) > 1 &&
                (self::endsWith($stem, 's') || self::endsWith($stem, 't'))) {
                return $stem;
            }
        }

        // Handle other suffixes
        foreach ($suffixes as $suffix) {
            if (self::endsWith($word, $suffix)) {
                $stem = mb_substr($word, 0, -mb_strlen($suffix));
                if (self::measureWord($stem) > 1) {
                    return $stem;
                }
                return $word;
            }
        }

        return $word;
    }

    /**
     * Step 5a: Handle final -e
     */
    private static function step5a(string $word): string
    {
        if (!self::endsWith($word, 'e')) {
            return $word;
        }

        $stem = mb_substr($word, 0, -1);
        $measure = self::measureWord($stem);

        // Remove if measure > 1 or (measure == 1 and not *o)
        if ($measure > 1) {
            return $stem;
        }

        if ($measure === 1 && !self::endsCvc($stem)) {
            return $stem;
        }

        return $word;
    }

    /**
     * Step 5b: Handle final -ll
     */
    private static function step5b(string $word): string
    {
        if (self::measureWord($word) > 1 &&
            self::endsDoubleConsonant($word) &&
            self::endsWith($word, 'll')) {
            return mb_substr($word, 0, -1);
        }
        return $word;
    }
}