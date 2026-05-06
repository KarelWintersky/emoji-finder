<?php

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
     * This is a direct port from the Snowball C implementation
     */
    private static function porterStem(string $word): string
    {
        if (strlen($word) <= 2) {
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
     * Checks if a letter is a consonant
     */
    private static function isConsonant(string $word, int $index): bool
    {
        $letter = $word[$index];

        if ($letter === 'a' || $letter === 'e' || $letter === 'i' ||
            $letter === 'o' || $letter === 'u') {
            return false;
        }

        if ($letter === 'y') {
            return $index === 0 || !self::isConsonant($word, $index - 1);
        }

        return true;
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

        $prevVowel = false;

        for ($i = 0; $i < $len; $i++) {
            $isVowel = !self::isConsonant($word, $i);

            if ($isVowel && !$prevVowel) {
                // Start of vowel sequence
            } elseif (!$isVowel && $prevVowel) {
                $measure++;
            }

            $prevVowel = $isVowel;
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
            if (!self::isConsonant($word, $i)) {
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
        $v1 = !self::isConsonant($word, $len - 2);
        $c2 = self::isConsonant($word, $len - 1);

        $last = $word[$len - 1];

        return $c1 && $v1 && $c2 && $last !== 'w' && $last !== 'x' && $last !== 'y';
    }

    /**
     * Replaces suffix if measure > 0
     */
    private static function replaceSuffix(string $word, string $suffix, string $replacement): string
    {
        if (str_ends_with($word, $suffix)) {
            $stem = substr($word, 0, -strlen($suffix));
            if (self::measureWord($stem) > 0) {
                return $stem . $replacement;
            }
        }
        return $word;
    }

    /**
     * Step 1a: Handle plurals and -ed/-ing
     */
    private static function step1a(string $word): string
    {
        // SSES -> SS
        if (str_ends_with($word, 'sses')) {
            return substr($word, 0, -2);
        }

        // IES -> I
        if (str_ends_with($word, 'ies')) {
            return substr($word, 0, -2);
        }

        // SS -> SS (do nothing)
        if (str_ends_with($word, 'ss')) {
            return $word;
        }

        // S -> (remove)
        if (str_ends_with($word, 's')) {
            return substr($word, 0, -1);
        }

        return $word;
    }

    /**
     * Step 1b: Handle -ed and -ing
     */
    private static function step1b(string $word): string
    {
        $hasEd = str_ends_with($word, 'ed');
        $hasIng = str_ends_with($word, 'ing');

        if (!$hasEd && !$hasIng) {
            return $word;
        }

        $suffixLen = $hasEd ? 2 : 3;
        $stem = substr($word, 0, -$suffixLen);

        if (!self::hasVowel($stem)) {
            return $word;
        }

        $word = $stem;

        // AT, BL, IZ -> add E
        if (str_ends_with($word, 'at') ||
            str_ends_with($word, 'bl') ||
            str_ends_with($word, 'iz')) {
            return $word . 'e';
        }

        // Double consonant (not L, S, Z) -> remove last
        if (self::endsDoubleConsonant($word)) {
            $last = $word[strlen($word) - 1];
            if ($last !== 'l' && $last !== 's' && $last !== 'z') {
                return substr($word, 0, -1);
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
        if (str_ends_with($word, 'y')) {
            $stem = substr($word, 0, -1);
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
        // ATIONAL -> ATE
        $word = self::replaceSuffix($word, 'ational', 'ate');

        // TIONAL -> TION
        $word = self::replaceSuffix($word, 'tional', 'tion');

        // ENCI -> ENCE
        $word = self::replaceSuffix($word, 'enci', 'ence');

        // ANCI -> ANCE
        $word = self::replaceSuffix($word, 'anci', 'ance');

        // IZER -> IZE
        $word = self::replaceSuffix($word, 'izer', 'ize');

        // ABLI -> ABLE
        $word = self::replaceSuffix($word, 'abli', 'able');

        // ALLI -> AL
        $word = self::replaceSuffix($word, 'alli', 'al');

        // ENTLI -> ENT
        $word = self::replaceSuffix($word, 'entli', 'ent');

        // ELI -> E
        $word = self::replaceSuffix($word, 'eli', 'e');

        // OUSLI -> OUS
        $word = self::replaceSuffix($word, 'ousli', 'ous');

        // IZATION -> IZE
        $word = self::replaceSuffix($word, 'ization', 'ize');

        // ATION -> ATE
        $word = self::replaceSuffix($word, 'ation', 'ate');

        // ATOR -> ATE
        $word = self::replaceSuffix($word, 'ator', 'ate');

        // ALISM -> AL
        $word = self::replaceSuffix($word, 'alism', 'al');

        // IVENESS -> IVE
        $word = self::replaceSuffix($word, 'iveness', 'ive');

        // FULNESS -> FUL
        $word = self::replaceSuffix($word, 'fulness', 'ful');

        // OUSNESS -> OUS
        $word = self::replaceSuffix($word, 'ousness', 'ous');

        // ALITI -> AL
        $word = self::replaceSuffix($word, 'aliti', 'al');

        // IVITI -> IVE
        $word = self::replaceSuffix($word, 'iviti', 'ive');

        // BILITI -> BLE
        $word = self::replaceSuffix($word, 'biliti', 'ble');

        return $word;
    }

    /**
     * Step 3: Handle -ic-, -full, -ness etc
     */
    private static function step3(string $word): string
    {
        // ICATE -> IC
        $word = self::replaceSuffix($word, 'icate', 'ic');

        // ATIVE -> (null)
        $word = self::replaceSuffix($word, 'ative', '');

        // ALIZE -> AL
        $word = self::replaceSuffix($word, 'alize', 'al');

        // ICITI -> IC
        $word = self::replaceSuffix($word, 'iciti', 'ic');

        // ICAL -> IC
        $word = self::replaceSuffix($word, 'ical', 'ic');

        // FUL -> (null)
        $word = self::replaceSuffix($word, 'ful', '');

        // NESS -> (null)
        $word = self::replaceSuffix($word, 'ness', '');

        return $word;
    }

    /**
     * Step 4: Handle -al, -ance, -ence etc
     */
    private static function step4(string $word): string
    {
        // AL
        if (str_ends_with($word, 'al')) {
            $stem = substr($word, 0, -2);
            if (self::measureWord($stem) > 1) {
                return $stem;
            }
        }

        // ANCE
        if (str_ends_with($word, 'ance')) {
            $stem = substr($word, 0, -4);
            if (self::measureWord($stem) > 1) {
                return $stem;
            }
        }

        // ENCE
        if (str_ends_with($word, 'ence')) {
            $stem = substr($word, 0, -4);
            if (self::measureWord($stem) > 1) {
                return $stem;
            }
        }

        // ER
        if (str_ends_with($word, 'er')) {
            $stem = substr($word, 0, -2);
            if (self::measureWord($stem) > 1) {
                return $stem;
            }
        }

        // IC
        if (str_ends_with($word, 'ic')) {
            $stem = substr($word, 0, -2);
            if (self::measureWord($stem) > 1) {
                return $stem;
            }
        }

        // ABLE
        if (str_ends_with($word, 'able')) {
            $stem = substr($word, 0, -4);
            if (self::measureWord($stem) > 1) {
                return $stem;
            }
        }

        // IBLE
        if (str_ends_with($word, 'ible')) {
            $stem = substr($word, 0, -4);
            if (self::measureWord($stem) > 1) {
                return $stem;
            }
        }

        // ANT
        if (str_ends_with($word, 'ant')) {
            $stem = substr($word, 0, -3);
            if (self::measureWord($stem) > 1) {
                return $stem;
            }
        }

        // EMENT
        if (str_ends_with($word, 'ement')) {
            $stem = substr($word, 0, -5);
            if (self::measureWord($stem) > 1) {
                return $stem;
            }
        }

        // MENT
        if (str_ends_with($word, 'ment')) {
            $stem = substr($word, 0, -4);
            if (self::measureWord($stem) > 1) {
                return $stem;
            }
        }

        // ENT
        if (str_ends_with($word, 'ent')) {
            $stem = substr($word, 0, -3);
            if (self::measureWord($stem) > 1) {
                return $stem;
            }
        }

        // OU
        if (str_ends_with($word, 'ou')) {
            $stem = substr($word, 0, -2);
            if (self::measureWord($stem) > 1) {
                return $stem;
            }
        }

        // ISM
        if (str_ends_with($word, 'ism')) {
            $stem = substr($word, 0, -3);
            if (self::measureWord($stem) > 1) {
                return $stem;
            }
        }

        // ATE
        if (str_ends_with($word, 'ate')) {
            $stem = substr($word, 0, -3);
            if (self::measureWord($stem) > 1) {
                return $stem;
            }
        }

        // ITI
        if (str_ends_with($word, 'iti')) {
            $stem = substr($word, 0, -3);
            if (self::measureWord($stem) > 1) {
                return $stem;
            }
        }

        // OUS
        if (str_ends_with($word, 'ous')) {
            $stem = substr($word, 0, -3);
            if (self::measureWord($stem) > 1) {
                return $stem;
            }
        }

        // IVE
        if (str_ends_with($word, 'ive')) {
            $stem = substr($word, 0, -3);
            if (self::measureWord($stem) > 1) {
                return $stem;
            }
        }

        // IZE
        if (str_ends_with($word, 'ize')) {
            $stem = substr($word, 0, -3);
            if (self::measureWord($stem) > 1) {
                return $stem;
            }
        }

        // ION
        if (str_ends_with($word, 'ion')) {
            $stem = substr($word, 0, -3);
            if (self::measureWord($stem) > 1 &&
                (str_ends_with($stem, 's') || str_ends_with($stem, 't'))) {
                return $stem;
            }
        }

        return $word;
    }

    /**
     * Step 5a: Handle final -e
     */
    private static function step5a(string $word): string
    {
        if (!str_ends_with($word, 'e')) {
            return $word;
        }

        $stem = substr($word, 0, -1);
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
            str_ends_with($word, 'l')) {
            return substr($word, 0, -1);
        }
        return $word;
    }
}