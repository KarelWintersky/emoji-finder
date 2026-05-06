<?php

namespace Arris\Toolkit\EmojiFinder;

final class Constants
{
    /** @var array<string, array<int, string>> */
    public static array $emojiKeywords = [];

    /** @var array<string, string> */
    public static array $keywordMostRelevantEmoji = [];

    /** @var array<string, array<int, string>> */
    public static array $emojiGlossary = [];

    /** @var array<int, string> */
    public static array $top1000WordsByFrequency = [];

    /** @var array<string, int> */
    public static array $wordToTop1000WordsIdx = [];

    /** @var array<string, true> */
    public static array $emojiSet = [];

    private static bool $initialized = false;

    public static function init(string $dataDir): void
    {
        if (self::$initialized) {
            return;
        }

        self::$emojiKeywords = self::loadJson($dataDir . '/emoji-keywords.json');
        self::$keywordMostRelevantEmoji = self::loadJson($dataDir . '/keyword-most-relevant-emoji.json');
        self::$emojiGlossary = self::loadJson($dataDir . '/emoji-glossary.json');
        self::$top1000WordsByFrequency = self::loadJson($dataDir . '/top-1000-words-by-frequency.json');

        self::$emojiSet = array_fill_keys(array_keys(self::$emojiKeywords), true);

        foreach (self::$top1000WordsByFrequency as $idx => $word) {
            self::$wordToTop1000WordsIdx[$word] = $idx;
        }

        self::$initialized = true;
    }

    /**
     * @param string $filePath
     *
     * @return array
     */
    private static function loadJson(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("Data file not found: $filePath");
        }

        $content = file_get_contents($filePath);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("Invalid JSON in file: $filePath");
        }

        return $data;
    }
}