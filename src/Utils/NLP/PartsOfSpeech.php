<?php

namespace Arris\Toolkit\EmojiFinder\Utils\NLP;

final class PartsOfSpeech
{
    private const PRONOUNS = [
        'i', 'you', 'he', 'she', 'it', 'we', 'they',
        'me', 'you', 'him', 'her', 'it', 'us', 'them',
        'my', 'your', 'his', 'her', 'its', 'our', 'their',
        'mine', 'yours', 'his', 'hers', 'its', 'ours', 'theirs',
        'myself', 'yourself', 'himself', 'herself', 'itself', 'ourselves', 'themselves', 'yourselves',
        'this', 'that', 'these', 'those',
        'who', 'whom', 'which', 'what',
    ];

    private const PREPOSITIONS = [
        'about', 'across', 'after', 'against', 'along', 'among', 'around', 'as', 'at',
        'before', 'behind', 'beneath', 'beside', 'between', 'beyond', 'by',
        'despite', 'during', 'except', 'for', 'from', 'in', 'inside', 'into', 'near',
        'of', 'on', 'onto', 'out', 'outside', 'over', 'since', 'than', 'through', 'throughout', 'to', 'toward',
        'under', 'until', 'upon', 'via', 'with', 'within', 'without',
    ];

    private const CONJUNCTIONS = ['for', 'and', 'nor', 'but', 'or', 'yet', 'so'];

    private const ARTICLES = ['a', 'an', 'the'];

    private const PREDETERMINERS = ['all', 'both'];
    private const PREDETERMINERS_EXCEPTIONS_PREVIOUS_WORDS = ['calling'];

    private const OTHERS = [
        'is', 'are', 'was', 'were', 'if', 'will', 'would', 'be', 'being', 'one',
        'have', 'has', 'had', 'can', 'more', 'then', 'do', "don't", 'first', 'even',
        'there', 'only', 'also', 'such', 'each', 'because', 'however', 'very',
        'must', 'due',
    ];

    private static ?array $pronounsSet = null;
    private static ?array $prepositionsSet = null;
    private static ?array $conjunctionsSet = null;
    private static ?array $articlesSet = null;
    private static ?array $predeterminersSet = null;
    private static ?array $predeterminersExceptionsPreWordsSet = null;
    private static ?array $othersSet = null;

    private static function init(): void
    {
        if (self::$pronounsSet !== null) {
            return;
        }

        self::$pronounsSet = array_fill_keys(self::PRONOUNS, true);
        self::$prepositionsSet = array_fill_keys(self::PREPOSITIONS, true);
        self::$conjunctionsSet = array_fill_keys(self::CONJUNCTIONS, true);
        self::$articlesSet = array_fill_keys(self::ARTICLES, true);
        self::$predeterminersSet = array_fill_keys(self::PREDETERMINERS, true);
        self::$predeterminersExceptionsPreWordsSet = array_fill_keys(self::PREDETERMINERS_EXCEPTIONS_PREVIOUS_WORDS, true);
        self::$othersSet = array_fill_keys(self::OTHERS, true);
    }

    /**
     * Filter out words that are pronouns, prepositions, conjunctions, articles or some others.
     *
     * @param array<int, string> $words
     * @return array<int, string>
     */
    public static function filter(array $words): array
    {
        self::init();

        return array_values(array_filter(
            $words,
            function (string $word, int $idx) use ($words): bool {
                $previousWord = $words[$idx - 1] ?? '';

                return !(
                    isset(self::$pronounsSet[$word]) ||
                    isset(self::$prepositionsSet[$word]) ||
                    isset(self::$conjunctionsSet[$word]) ||
                    isset(self::$articlesSet[$word]) ||
                    (isset(self::$predeterminersSet[$word]) &&
                        !isset(self::$predeterminersExceptionsPreWordsSet[$previousWord])) ||
                    isset(self::$othersSet[$word])
                );
            },
            ARRAY_FILTER_USE_BOTH
        ));
    }
}