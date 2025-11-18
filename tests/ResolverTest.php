<?php

declare(strict_types=1);

namespace Wafl\Core\Tests;

use Wafl\Core\Resolver;
use PHPUnit\Framework\TestCase;

final class ResolverTest extends TestCase
{
    public function testResolvesExpressionsTagsAndConditionalLists(): void
    {
        $resolver = new Resolver();
        $doc = [
            'count' => ['__expr' => '1 + 1'],
            'color' => ['__tag' => 'rgb', 'args' => ['255', '0', '0']],
            'items' => [
                'first',
                ['__if' => '$ENV.FLAG', 'value' => 'second'],
            ],
            'fallback' => ['__expr' => '$ENV.MISSING || 42'],
        ];

        $result = $resolver->resolve($doc, [
            'env' => ['FLAG' => true],
            'ctx' => ['baseDir' => __DIR__],
        ]);

        $this->assertSame(2, $result['count']);
        $this->assertSame('rgb(255, 0, 0)', $result['color']);
        $this->assertSame(['first', 'second'], $result['items']);
        $this->assertSame(42, $result['fallback']);

        $resultWithoutFlag = $resolver->resolve($doc, [
            'env' => ['FLAG' => false],
            'ctx' => ['baseDir' => __DIR__],
        ]);

        $this->assertSame(['first'], $resultWithoutFlag['items']);
    }
}
