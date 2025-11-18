<?php

declare(strict_types=1);

namespace Wafl\Core\Tests;

use Wafl\Core\Parser;
use PHPUnit\Framework\TestCase;

final class ParserTest extends TestCase
{
    public function testParsesSectionsExpressionsAndTags(): void
    {
        $source = <<<'WAFL'
app:
  name: "Demo"
value = 1 + 1
color: !rgb(255, 0, 0)
WAFL;

        $parser = new Parser();
        $doc = $parser->parseString($source);

        $this->assertSame('Demo', $doc['app']['name']);
        $this->assertSame('1 + 1', $doc['value']['__expr']);
        $this->assertSame('rgb', $doc['color']['__tag']);
        $this->assertSame(['255', '0', '0'], $doc['color']['args']);
    }
}
