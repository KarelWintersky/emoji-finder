<?php

namespace Arris\Toolkit\EmojiFinder\Tests;

use PHPUnit\Framework\TestCase;
use Arris\Toolkit\EmojiFinder\Utils\NLP\StemWord;

class StemWordTest extends TestCase
{
    /**
     * @dataProvider stemWordProvider
     */
    public function testStemWord(string $word, string $expected): void
    {
        $this->assertEquals($expected, StemWord::stem($word));
    }

    public function stemWordProvider(): array
    {
        return [
            ['happy', 'happy'],
            ['DIY', 'DIY'],
            ['crying', 'cry'],
            ['carryings', 'carry'],
            ['smiling', 'smil'],
            ['codings', 'cod'],
            ['blazingly', 'blaz'],
            ['disability', 'disabi'],
            ['capabilities', 'capabi'],
            ['candys', 'candy'],
            ['coolest', 'cool'],
        ];
    }
}