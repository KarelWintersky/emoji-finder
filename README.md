# emoji-finder

A PHP emoji search engine optimized for search-as-you-type experience

Based on https://github.com/xitanggg/emoogle-emoji-search-engine
Vibecoded by Deepseek

# Usage

```php

<?php

require_once __DIR__ . '/vendor/autoload.php';

use Arris\Toolkit\EmojiFinder\EmojiFinder;

// Initialize with data directory
EmojiFinder::init(__DIR__ . '/data');

// Basic usage
$result = EmojiFinder::search('amazing');
// => ['🤩', '💯', '🙌', '🌈']
var_dump($result);

// With max limit
$result = EmojiFinder::search('amazing', 2);
// => ['🤩', '💯']
var_dump($result);

// Personalize with custom emoji keywords
$result = EmojiFinder::search('amazing', 24, [
    'customEmojiKeywords' => [
        '🏆' => ['amazing'],
    ],
]);
// => ['🤩', '💯', '🙌', '🌈', '🏆']
var_dump($result);

// Personalize with user preferred keyword to emoji
$result = EmojiFinder::search('amazing', 24, [
    'customKeywordMostRelevantEmoji' => [
        'amazing' => '💯',
    ],
]);
// => ['💯', '🤩', '🙌', '🌈']
var_dump($result);

// Personalize with user recently searched inputs
$result = EmojiFinder::search('h', 4, [
    'recentlySearchedInputs' => ['hello'],
]);
// => ['👋', '🫂', '🤝', '🙏']
var_dump($result);

// Search for best match
$result = EmojiFinder::searchBestMatching('hello world', 4);
// => ['👋', '🫂', '🌍', '🌎']
var_dump($result);

```