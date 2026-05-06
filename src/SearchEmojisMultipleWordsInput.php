<?php

namespace Arris\Toolkit\EmojiFinder;

use Arris\Toolkit\EmojiFinder\Utils\PreProcessString;

final class SearchEmojisMultipleWordsInput
{
    /**
     * Search emojis for an input phrase that contains multiple words, e.g. dog face
     *
     * @param array{emojiKeywords?: array<string, array<int, string>>, keywordMostRelevantEmoji?: array<string, string>} $options
     * @return array<int, string>
     */
    public static function search(string $inputWords, array $options): array
    {
        $customEmojiKeywords = $options['customEmojiKeywords'] ?? [];
        $customKeywordMostRelevantEmoji = $options['customKeywordMostRelevantEmoji'] ?? [];

        $emojisAttributes = [];
        $inputWordsArray = explode(' ', $inputWords);

        foreach (Constants::$emojiKeywords as $emoji => $keywords) {
            $allKeywords = isset($customEmojiKeywords[$emoji])
                ? array_merge($keywords, $customEmojiKeywords[$emoji])
                : $keywords;

            $emojiBestAttributes = self::getEmojiBestAttributes(
                $inputWords,
                $inputWordsArray,
                $emoji,
                $allKeywords,
                $customKeywordMostRelevantEmoji
            );

            if ($emojiBestAttributes !== null) {
                $emojisAttributes[] = [$emoji, $emojiBestAttributes];
            }
        }

        usort($emojisAttributes, function (array $a, array $b): int {
            return self::compareAttributes($a[1], $b[1]);
        });

        return array_column($emojisAttributes, 0);
    }

    /**
     * @param array<string, string> $customKeywordMostRelevantEmoji
     * @return array|null
     */
    private static function getEmojiBestAttributes(
        string $inputWords,
        array $inputWordsArray,
        string $emoji,
        array $keywords,
        array $customKeywordMostRelevantEmoji
    ): ?array {
        $emojiBestAttributes = null;

        $keywords = array_map(fn($k) => PreProcessString::process($k), $keywords);

        // First, attempt to find a match against multiple words keywords
        $multipleWordsKeywords = array_filter($keywords, fn($k) => str_contains($k, ' '));

        foreach ($multipleWordsKeywords as $keyword) {
            // Check if there is an in-order exact match
            if ($keyword === $inputWords) {
                $isCustomMostRelevantEmoji = ($customKeywordMostRelevantEmoji[$keyword] ?? null) === $emoji;
                $attributes = [
                    'isMultipleWordsKeywordMatch' => true,
                    'isMultipleWordsKeywordInOrderMatch' => true,
                    'isMultipleWordsKeywordInOrderMatchExactMatch' => true,
                    'isCustomMostRelevantEmoji' => $isCustomMostRelevantEmoji,
                    'numExactMatches' => 0,
                    'numPrefixMatches' => 0,
                    'numWordsInMultipleWordsKeyword' => 0,
                ];

                if ($emojiBestAttributes === null || self::compareAttributes($attributes, $emojiBestAttributes) < 0) {
                    $emojiBestAttributes = $attributes;
                }
            }
            // Check if there is an in-order partial match
            elseif (str_starts_with($keyword, $inputWords) || str_contains($keyword, ' ' . $inputWords)) {
                $keywordWordsArray = explode(' ', $keyword);
                $isCustomMostRelevantEmoji = ($customKeywordMostRelevantEmoji[$keyword] ?? null) === $emoji;
                $attributes = [
                    'isMultipleWordsKeywordMatch' => true,
                    'isMultipleWordsKeywordInOrderMatch' => true,
                    'isMultipleWordsKeywordInOrderMatchExactMatch' => false,
                    'isCustomMostRelevantEmoji' => $isCustomMostRelevantEmoji,
                    'numExactMatches' => 0,
                    'numPrefixMatches' => 0,
                    'numWordsInMultipleWordsKeyword' => count($keywordWordsArray),
                ];

                if ($emojiBestAttributes === null || self::compareAttributes($attributes, $emojiBestAttributes) < 0) {
                    $emojiBestAttributes = $attributes;
                }
            }
            // Check if there is an out of order match
            else {
                $keywordWordsArray = explode(' ', $keyword);

                if (count($keywordWordsArray) < count($inputWordsArray)) {
                    continue;
                }

                ['numExactMatches' => $numExactMatches, 'numPrefixMatches' => $numPrefixMatches] =
                    self::getNumMatches($inputWordsArray, $keywordWordsArray);

                if ($numExactMatches === 0 && $numPrefixMatches === 0) {
                    continue;
                }

                $attributes = [
                    'isMultipleWordsKeywordMatch' => true,
                    'isMultipleWordsKeywordInOrderMatch' => false,
                    'isMultipleWordsKeywordInOrderMatchExactMatch' => false,
                    'isCustomMostRelevantEmoji' => false,
                    'numExactMatches' => $numExactMatches,
                    'numPrefixMatches' => $numPrefixMatches,
                    'numWordsInMultipleWordsKeyword' => count($keywordWordsArray),
                ];

                if ($emojiBestAttributes === null || self::compareAttributes($attributes, $emojiBestAttributes) < 0) {
                    $emojiBestAttributes = $attributes;
                }
            }
        }

        // If there isn't a multiple words keyword match, search through the jointed keywords
        if ($emojiBestAttributes === null) {
            $jointedKeywordsArray = explode(' ', implode(' ', $keywords));

            ['numExactMatches' => $numExactMatches, 'numPrefixMatches' => $numPrefixMatches] =
                self::getNumMatches($inputWordsArray, $jointedKeywordsArray);

            if ($numExactMatches !== 0 || $numPrefixMatches !== 0) {
                $attributes = [
                    'isMultipleWordsKeywordMatch' => false,
                    'isMultipleWordsKeywordInOrderMatch' => false,
                    'isMultipleWordsKeywordInOrderMatchExactMatch' => false,
                    'isCustomMostRelevantEmoji' => false,
                    'numExactMatches' => $numExactMatches,
                    'numPrefixMatches' => $numPrefixMatches,
                    'numWordsInMultipleWordsKeyword' => 0,
                ];

                $emojiBestAttributes = $attributes;
            }
        }

        return $emojiBestAttributes;
    }

    /**
     * Return the number of exact matches and prefix matches
     *
     * @param array<int, string> $inputWordsArray
     * @param array<int, string> $keywordsArray
     * @return array{numExactMatches: int, numPrefixMatches: int}
     */
    private static function getNumMatches(array $inputWordsArray, array $keywordsArray): array
    {
        $numExactMatches = 0;
        $numPrefixMatches = 0;

        foreach ($inputWordsArray as $inputWord) {
            $bestMatchType = null;

            foreach ($keywordsArray as $keyword) {
                if ($keyword === $inputWord) {
                    $bestMatchType = 'Exact';
                    break;
                }
                if (str_starts_with($keyword, $inputWord)) {
                    $bestMatchType = 'Prefix';
                }
            }

            if ($bestMatchType === null) {
                return ['numExactMatches' => 0, 'numPrefixMatches' => 0];
            }

            if ($bestMatchType === 'Exact') {
                $numExactMatches++;
            } else {
                $numPrefixMatches++;
            }
        }

        return ['numExactMatches' => $numExactMatches, 'numPrefixMatches' => $numPrefixMatches];
    }

    private static function compareAttributes(array $a, array $b): int
    {
        if ($a['isMultipleWordsKeywordMatch'] !== $b['isMultipleWordsKeywordMatch']) {
            return $a['isMultipleWordsKeywordMatch'] ? -1 : 1;
        }

        if ($a['isMultipleWordsKeywordMatch']) {
            if ($a['isMultipleWordsKeywordInOrderMatch'] !== $b['isMultipleWordsKeywordInOrderMatch']) {
                return $a['isMultipleWordsKeywordInOrderMatch'] ? -1 : 1;
            }

            if ($a['isMultipleWordsKeywordInOrderMatch']) {
                if ($a['isMultipleWordsKeywordInOrderMatchExactMatch'] !== $b['isMultipleWordsKeywordInOrderMatchExactMatch']) {
                    return $a['isMultipleWordsKeywordInOrderMatchExactMatch'] ? -1 : 1;
                }

                if ($a['isCustomMostRelevantEmoji'] !== $b['isCustomMostRelevantEmoji']) {
                    return $a['isCustomMostRelevantEmoji'] ? -1 : 1;
                }
            } else {
                if ($a['numExactMatches'] !== $b['numExactMatches']) {
                    return $a['numExactMatches'] > $b['numExactMatches'] ? -1 : 1;
                }

                if ($a['numPrefixMatches'] !== $b['numPrefixMatches']) {
                    return $a['numPrefixMatches'] > $b['numPrefixMatches'] ? -1 : 1;
                }
            }

            if ($a['numWordsInMultipleWordsKeyword'] !== $b['numWordsInMultipleWordsKeyword']) {
                return $a['numWordsInMultipleWordsKeyword'] < $b['numWordsInMultipleWordsKeyword'] ? -1 : 1;
            }

            return 0;
        }

        // Jointed keywords match
        if ($a['numExactMatches'] !== $b['numExactMatches']) {
            return $a['numExactMatches'] > $b['numExactMatches'] ? -1 : 1;
        }

        if ($a['numPrefixMatches'] !== $b['numPrefixMatches']) {
            return $a['numPrefixMatches'] > $b['numPrefixMatches'] ? -1 : 1;
        }

        return 0;
    }
}