<?php

namespace Arris\Toolkit\EmojiFinder;

use Arris\Toolkit\EmojiFinder\Utils\PreProcessString;

final class SearchEmojisSingleWordInput
{
    /**
     * Search emojis for a single word input, e.g. dog
     *
     * @param array{emojiKeywords?: array<string, array<int, string>>, keywordMostRelevantEmoji?: array<string, string>, recentlySearchedInputs?: array<int, string>} $options
     * @return array<int, string>
     */
    public static function search(string $inputWord, array $options): array
    {
        $customEmojiKeywords = $options['customEmojiKeywords'] ?? [];
        $customKeywordMostRelevantEmoji = $options['customKeywordMostRelevantEmoji'] ?? [];
        $recentlySearchedInputs = $options['recentlySearchedInputs'] ?? [];

        $wordToRecentlySearchedInputIdx = [];
        if (!empty($recentlySearchedInputs)) {
            foreach ($recentlySearchedInputs as $idx => $input) {
                $wordToRecentlySearchedInputIdx[$input] = $idx;
            }
        }

        $emojisAttributes = [];

        foreach (Constants::$emojiKeywords as $emoji => $keywords) {
            $allKeywords = isset($customEmojiKeywords[$emoji])
                ? array_merge($keywords, $customEmojiKeywords[$emoji])
                : $keywords;

            $emojiBestAttributes = self::getEmojiBestAttributes(
                $inputWord,
                $emoji,
                $allKeywords,
                $customKeywordMostRelevantEmoji,
                !empty($wordToRecentlySearchedInputIdx) ? $wordToRecentlySearchedInputIdx : null
            );

            if ($emojiBestAttributes !== null) {
                $emojisAttributes[] = [$emoji, $emojiBestAttributes];
            }
        }

        // Sort emojisAttributes from best attributes to lowest
        usort($emojisAttributes, function (array $a, array $b): int {
            return self::compareAttributes($a[1], $b[1]);
        });

        return array_column($emojisAttributes, 0);
    }

    /**
     * @param array<string, string> $customKeywordMostRelevantEmoji
     * @param array<string, int>|null $wordToRecentlySearchedInputIdx
     * @return array|null
     */
    private static function getEmojiBestAttributes(
        string $inputWord,
        string $emoji,
        array $keywords,
        array $customKeywordMostRelevantEmoji,
        ?array $wordToRecentlySearchedInputIdx
    ): ?array {
        $emojiBestAttributes = null;
        $hasRecentlySearchedInputs = $wordToRecentlySearchedInputIdx !== null;

        foreach ($keywords as $i => $keyword) {
            $keyword = PreProcessString::process($keyword);

            $isEmojiName = $i === 0;
            $isSingleWord = !str_contains($keyword, ' ');

            if ($isSingleWord) {
                $isExactMatch = self::computeIsExactMatch($inputWord, $keyword);

                if ($isExactMatch === null) {
                    continue;
                }

                $isMostRelevantEmoji = (Constants::$keywordMostRelevantEmoji[$keyword] ?? null) === $emoji;
                $isCustomMostRelevantEmoji = ($customKeywordMostRelevantEmoji[$keyword] ?? null) === $emoji;

                $attributes = [
                    'isExactMatch' => $isExactMatch,
                    'isCustomMostRelevantEmoji' => $isCustomMostRelevantEmoji,
                    'isMostRelevantEmoji' => $isMostRelevantEmoji,
                    'isEmojiName' => $isEmojiName,
                    'isSingleWord' => $isSingleWord,
                    'matchWord' => $keyword,
                    'prefixMatchRecentlySearchedInputsIdx' => $hasRecentlySearchedInputs && !$isExactMatch
                        ? ($wordToRecentlySearchedInputIdx[$keyword] ?? null)
                        : null,
                    'prefixMatchTop1000WordsIdx' => !$isExactMatch
                        ? (Constants::$wordToTop1000WordsIdx[$keyword] ?? null)
                        : null,
                ];

                if ($emojiBestAttributes === null || self::compareAttributes($attributes, $emojiBestAttributes) < 0) {
                    $emojiBestAttributes = $attributes;
                }
            } else {
                $words = explode(' ', $keyword);
                foreach ($words as $word) {
                    $isExactMatch = self::computeIsExactMatch($inputWord, $word);

                    if ($isExactMatch === null) {
                        continue;
                    }

                    $isMostRelevantEmoji = (Constants::$keywordMostRelevantEmoji[$word] ?? null) === $emoji;
                    $isCustomMostRelevantEmoji = ($customKeywordMostRelevantEmoji[$word] ?? null) === $emoji;

                    $attributes = [
                        'isExactMatch' => $isExactMatch,
                        'isCustomMostRelevantEmoji' => $isCustomMostRelevantEmoji,
                        'isMostRelevantEmoji' => $isMostRelevantEmoji,
                        'isEmojiName' => $isEmojiName,
                        'isSingleWord' => $isSingleWord,
                        'matchWord' => $word,
                        'prefixMatchRecentlySearchedInputsIdx' => $hasRecentlySearchedInputs && !$isExactMatch
                            ? ($wordToRecentlySearchedInputIdx[$keyword] ?? null)
                            : null,
                        'prefixMatchTop1000WordsIdx' => !$isExactMatch
                            ? (Constants::$wordToTop1000WordsIdx[$keyword] ?? null)
                            : null,
                    ];

                    if ($emojiBestAttributes === null || self::compareAttributes($attributes, $emojiBestAttributes) < 0) {
                        $emojiBestAttributes = $attributes;
                    }
                }
            }
        }

        return $emojiBestAttributes;
    }

    /**
     * Return true if inputWord equals keyword, false if inputWord is a prefix match,
     * and null if there is no match.
     */
    private static function computeIsExactMatch(string $inputWord, string $keyword): ?bool
    {
        if ($inputWord === $keyword) {
            return true;
        }
        if (str_starts_with($keyword, $inputWord)) {
            return false;
        }
        return null;
    }

    /**
     * Compare two attributes for sorting
     */
    private static function compareAttributes(array $a, array $b): int
    {
        if ($a['isExactMatch'] !== $b['isExactMatch']) {
            return $a['isExactMatch'] ? -1 : 1;
        }

        if ($a['isExactMatch']) {
            if ($a['isCustomMostRelevantEmoji'] !== $b['isCustomMostRelevantEmoji']) {
                return $a['isCustomMostRelevantEmoji'] ? -1 : 1;
            }

            if ($a['isMostRelevantEmoji'] !== $b['isMostRelevantEmoji']) {
                return $a['isMostRelevantEmoji'] ? -1 : 1;
            }

            if ($a['isEmojiName'] !== $b['isEmojiName']) {
                return $a['isEmojiName'] ? -1 : 1;
            }

            if ($a['isSingleWord'] !== $b['isSingleWord']) {
                return $a['isSingleWord'] ? -1 : 1;
            }

            return 0;
        }

        // Prefix match
        if ($a['prefixMatchRecentlySearchedInputsIdx'] !== $b['prefixMatchRecentlySearchedInputsIdx']) {
            if ($a['prefixMatchRecentlySearchedInputsIdx'] === null) return 1;
            if ($b['prefixMatchRecentlySearchedInputsIdx'] === null) return -1;
            return $a['prefixMatchRecentlySearchedInputsIdx'] < $b['prefixMatchRecentlySearchedInputsIdx'] ? -1 : 1;
        }

        if ($a['isSingleWord'] !== $b['isSingleWord']) {
            return $a['isSingleWord'] ? -1 : 1;
        }

        if ($a['prefixMatchTop1000WordsIdx'] !== $b['prefixMatchTop1000WordsIdx']) {
            if ($a['prefixMatchTop1000WordsIdx'] === null) return 1;
            if ($b['prefixMatchTop1000WordsIdx'] === null) return -1;
            return $a['prefixMatchTop1000WordsIdx'] < $b['prefixMatchTop1000WordsIdx'] ? -1 : 1;
        }

        if ($a['matchWord'] !== $b['matchWord']) {
            return strcmp($a['matchWord'], $b['matchWord']);
        }

        if ($a['isCustomMostRelevantEmoji'] !== $b['isCustomMostRelevantEmoji']) {
            return $a['isCustomMostRelevantEmoji'] ? -1 : 1;
        }

        if ($a['isMostRelevantEmoji'] !== $b['isMostRelevantEmoji']) {
            return $a['isMostRelevantEmoji'] ? -1 : 1;
        }

        return 0;
    }
}