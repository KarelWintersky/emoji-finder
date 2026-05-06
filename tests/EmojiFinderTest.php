<?php

namespace Arris\Toolkit\EmojiFinder\Tests;

use PHPUnit\Framework\TestCase;
use Arris\Toolkit\EmojiFinder\EmojiFinder;
use Arris\Toolkit\EmojiFinder\Constants;
use Arris\Toolkit\EmojiFinder\Utils\PreProcessString;

class EmojiFinderTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        EmojiFinder::init(__DIR__ . '/../data');
    }

    public function testPreProcessString(): void
    {
        $this->assertEquals('japanese here button', PreProcessString::process('Japanese "here" button'));
        $this->assertEquals('person blond hair', PreProcessString::process('person: blond hair'));
        $this->assertEquals('llap live long and prosper', PreProcessString::process('LLAP (live long and prosper)'));
        $this->assertEquals('mrs claus', PreProcessString::process('Mrs. Claus'));
        $this->assertEquals('abcdef', PreProcessString::process('abc";,.!?def'));
        $this->assertEquals('upside down face', PreProcessString::process('upside-down face'));
        $this->assertEquals("twelve o'clock", PreProcessString::process('twelve o\'clock'));
        $this->assertEquals('ai', PreProcessString::process('AI'));
    }

    public function testSingleWordExactMatch(): void
    {
        $result = EmojiFinder::search('abacus');
        $this->assertContains('🧮', $result);
    }

    public function testSingleWordPrefixMatch(): void
    {
        $singleWordInput = 'abacus';
        for ($idx = 1; $idx < strlen($singleWordInput); $idx++) {
            $prefixInput = substr($singleWordInput, 0, $idx);
            $result = EmojiFinder::search($prefixInput, 999);
            $this->assertContains('🧮', $result);
        }
    }

    public function testNoMatch(): void
    {
        $this->assertEquals([], EmojiFinder::search('abacusabacus'));
    }

    public function testCustomEmojiKeywords(): void
    {
        $result = EmojiFinder::search('abacusabacus', 24, [
            'customEmojiKeywords' => [
                '❓' => ['abacusabacus'],
            ],
        ]);
        $this->assertEquals(['❓'], $result);
    }

    public function testMaxLimit(): void
    {
        $result = EmojiFinder::search('amazing', 2);
        $this->assertCount(2, $result);
    }

    public function testRecentlySearchedInputs(): void
    {
        $result1 = EmojiFinder::search('h', 4);
        $this->assertCount(4, $result1);

        $result2 = EmojiFinder::search('h', 4, [
            'recentlySearchedInputs' => ['hello'],
        ]);
        $this->assertCount(4, $result2);
        $this->assertContains('👋', $result2);
    }

    public function testCustomKeywordMostRelevantEmoji(): void
    {
        $result = EmojiFinder::search('amazing', 24, [
            'customKeywordMostRelevantEmoji' => [
                'amazing' => '💯',
            ],
        ]);
        $this->assertEquals('💯', $result[0]);
    }

    public function testSearchBestMatching(): void
    {
        $result = EmojiFinder::searchBestMatching('hello world', 4);
        $this->assertCount(4, $result);
        $this->assertContains('👋', $result);
    }

    public function testEmptyInput(): void
    {
        $this->assertEquals([], EmojiFinder::search(''));
        $this->assertEquals([], EmojiFinder::searchBestMatching(''));
    }

    public function testEmojiInput(): void
    {
        $result = EmojiFinder::search('😀');
        $this->assertEquals(['😀'], $result);
    }
}