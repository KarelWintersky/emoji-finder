<?php

namespace Arris\Toolkit\EmojiFinder;

use Arris\Toolkit\EmojiFinder\Utils\PreProcessString;
use Arris\Toolkit\EmojiFinder\Utils\NLP\PartsOfSpeech;
use Arris\Toolkit\EmojiFinder\Utils\NLP\StemWord;

final class SearchBestMatchingEmojis
{
    /**
     * Search best matching emojis for multiple words input
     *
     * @param array{emojiKeywords?: array<string, array<int, string>>} $options
     * @return array<int, string>
     */
    public static function searchForMultipleWordsInput(string $inputWords, array $options): array
    {
        $customEmojiKeywords = $options['customEmojiKeywords'] ?? [];

        $emojisAttributes = [];

        $inputWordsArray = PartsOfSpeech::filter(explode(' ', $inputWords));
        $stemmedInputWordsArray = array_map(fn($word) => StemWord::stem($word), $inputWordsArray);

        foreach (Constants::$emojiKeywords as $emoji => $keywords) {
            $allKeywords = isset($customEmojiKeywords[$emoji])
                ? array_merge($keywords, $customEmojiKeywords[$emoji])
                : $keywords;

            $emojiBestAttributes = self::getEmojiBestAttributes(
                $inputWordsArray,
                $stemmedInputWordsArray,
                $allKeywords
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
     * @param array<int, string> $inputWordsArray
     * @param array<int, string> $stemmedInputWordsArray
     * @param array<int, string> $keywords
     * @return array|null
     */
    private static function getEmojiBestAttributes(
        array $inputWordsArray,
        array $stemmedInputWordsArray,
        array $keywords
    ): ?array {
        $keywords = array_map(fn($k) => PreProcessString::process($k), $keywords);

        $jointedKeywordsArray = explode(' ', implode(' ', $keywords));
        $jointedKeywordsSet = array_fill_keys($jointedKeywordsArray, true);

        [
            'numExactWordMatches' => $numExactWordMatches,
            'numExactStemmedWordMatches' => $numExactStemmedWordMatches,
            'numPrefixWordMatches' => $numPrefixWordMatches,
            'numPrefixStemmedWordMatches' => $numPrefixStemmedWordMatches,
        ] = self::getNumMatches(
            $inputWordsArray,
            $stemmedInputWordsArray,
            $jointedKeywordsArray,
            $jointedKeywordsSet
        );

        if (
            $numExactWordMatches === 0 &&
            $numExactStemmedWordMatches === 0 &&
            $numPrefixWordMatches === 0 &&
            $numPrefixStemmedWordMatches === 0
        ) {
            return null;
        }

        return [
            'numExactWordMatches' => $numExactWordMatches,
            'numExactStemmedWordMatches' => $numExactStemmedWordMatches,
            'numPrefixWordMatches' => $numPrefixWordMatches,
            'numPrefixStemmedWordMatches' => $numPrefixStemmedWordMatches,
        ];
    }

    /**
     * @param array<int, string> $inputWordsArray
     * @param array<int, string> $stemmedInputWordsArray
     * @param array<int, string> $keywordsArray
     * @param array<string, true> $keywordsSet
     * @return array{numExactWordMatches: int, numExactStemmedWordMatches: int, numPrefixWordMatches: int, numPrefixStemmedWordMatches: int}
     */
    private static function getNumMatches(
        array $inputWordsArray,
        array $stemmedInputWordsArray,
        array $keywordsArray,
        array $keywordsSet
    ): array {
        $numExactWordMatches = 0;
        $numExactStemmedWordMatches = 0;
        $numPrefixWordMatches = 0;
        $numPrefixStemmedWordMatches = 0;

        foreach ($inputWordsArray as $i => $inputWord) {
            $stemmedInputWord = $stemmedInputWordsArray[$i];

            if (isset($keywordsSet[$inputWord])) {
                $numExactWordMatches++;
            } elseif ($inputWord !== $stemmedInputWord && isset($keywordsSet[$stemmedInputWord])) {
                $numExactStemmedWordMatches++;
            } else {
                $prefixMatchStemmedWord = false;
                foreach ($keywordsArray as $keyword) {
                    if (str_starts_with($keyword, $stemmedInputWord)) {
                        $prefixMatchStemmedWord = true;
                        if (str_starts_with($keyword, $inputWord)) {
                            $numPrefixWordMatches++;
                            $prefixMatchStemmedWord = false;
                            break;
                        }
                    }
                }
                if ($prefixMatchStemmedWord) {
                    $numPrefixStemmedWordMatches++;
                }
            }
        }

        return [
            'numExactWordMatches' => $numExactWordMatches,
            'numExactStemmedWordMatches' => $numExactStemmedWordMatches,
            'numPrefixWordMatches' => $numPrefixWordMatches,
            'numPrefixStemmedWordMatches' => $numPrefixStemmedWordMatches,
        ];
    }

    private static function compareAttributes(array $a, array $b): int
    {
        $aNumExactMatches = $a['numExactWordMatches'] + $a['numExactStemmedWordMatches'];
        $bNumExactMatches = $b['numExactWordMatches'] + $b['numExactStemmedWordMatches'];

        if ($aNumExactMatches !== $bNumExactMatches) {
            return $aNumExactMatches > $bNumExactMatches ? -1 : 1;
        }

        if ($a['numExactWordMatches'] !== $b['numExactWordMatches']) {
            return $a['numExactWordMatches'] > $b['numExactWordMatches'] ? -1 : 1;
        }

        if ($a['numPrefixWordMatches'] !== $b['numPrefixWordMatches']) {
            return $a['numPrefixWordMatches'] > $b['numPrefixWordMatches'] ? -1 : 1;
        }

        if ($a['numPrefixStemmedWordMatches'] !== $b['numPrefixStemmedWordMatches']) {
            return $a['numPrefixStemmedWordMatches'] > $b['numPrefixStemmedWordMatches'] ? -1 : 1;
        }

        return 0;
    }
}