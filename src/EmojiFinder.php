<?php

namespace Arris\Toolkit\EmojiFinder;

use Arris\Toolkit\EmojiFinder\Utils\PreProcessString;
use Arris\Toolkit\EmojiFinder\Utils\NLP\StemWord;

final class EmojiFinder
{
    /**
     * Initialize the emoji finder with data files
     */
    public static function init(string $dataDir): void
    {
        Constants::init($dataDir);
    }

    /**
     * Search emojis util that is optimized for the search as you type experience.
     *
     * The more characters/words a user types, the narrower the set of emojis return.
     *
     * For input that contains a phrase of multiple words, it performs an AND operation
     * and returns only emojis that match all input words.
     *
     * @param array{customEmojiKeywords?: array<string, array<int, string>>, customKeywordMostRelevantEmoji?: array<string, string>, recentlySearchedInputs?: array<int, string>} $options
     * @return array<int, string>
     */
    public static function search(string $input, int $maxLimit = 24, array $options = []): array
    {
        $input = trim(PreProcessString::process($input));

        if ($input === '') {
            return [];
        }

        // Return the input itself if it is an emoji
        if (isset(Constants::$emojiSet[$input])) {
            return [$input];
        }

        $isSingleWordInput = !str_contains($input, ' ');

        if ($isSingleWordInput) {
            return array_slice(
                SearchEmojisSingleWordInput::search($input, $options),
                0,
                $maxLimit
            );
        }

        return array_slice(
            SearchEmojisMultipleWordsInput::search($input, $options),
            0,
            $maxLimit
        );
    }

    /**
     * Search emojis util that is optimized for the best matching experience.
     *
     * Unlike the search emojis util, this is a more forgiving search that would also
     * match the stemmed input words by stripping off the suffixes, e.g. -s, -ing, -ed, etc.
     *
     * For input with multiple words, it strips off some parts of speeches (e.g.
     * pronouns, prepositions) and then performs an OR operation and returns all
     * emojis that contain a match with any of the remaining words.
     *
     * @param array{customEmojiKeywords?: array<string, array<int, string>>, customKeywordMostRelevantEmoji?: array<string, string>, recentlySearchedInputs?: array<int, string>} $options
     * @return array<int, string>
     */
    public static function searchBestMatching(string $input, int $maxLimit = 24, array $options = []): array
    {
        $input = trim(PreProcessString::process($input));

        if ($input === '') {
            return [];
        }

        $isSingleWordInput = !str_contains($input, ' ');

        if ($isSingleWordInput) {
            $emojisForSingleWordInput = array_slice(
                SearchEmojisSingleWordInput::search($input, $options),
                0,
                $maxLimit
            );

            if (!empty($emojisForSingleWordInput)) {
                return $emojisForSingleWordInput;
            }

            $stemmedInput = StemWord::stem($input);

            if ($stemmedInput === $input) {
                return [];
            }

            return array_slice(
                SearchEmojisSingleWordInput::search($stemmedInput, $options),
                0,
                $maxLimit
            );
        }

        $emojisForMultipleWordsInput = array_slice(
            SearchEmojisMultipleWordsInput::search($input, $options),
            0,
            $maxLimit
        );

        if (!empty($emojisForMultipleWordsInput)) {
            return $emojisForMultipleWordsInput;
        }

        return array_slice(
            SearchBestMatchingEmojis::searchForMultipleWordsInput($input, $options),
            0,
            $maxLimit
        );
    }
}