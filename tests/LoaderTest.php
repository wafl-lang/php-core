<?php

declare(strict_types=1);

namespace Wafl\Core\Tests;

use Wafl\Core\Loader;
use PHPUnit\Framework\TestCase;

final class LoaderTest extends TestCase
{
    public function testLoadsImportsAndExtractsMetadata(): void
    {
        $fixture = __DIR__ . '/fixtures/base.wafl';
        $loader = new Loader();
        $result = $loader->load($fixture);

        $this->assertArrayHasKey('doc', $result);
        $this->assertArrayHasKey('meta', $result);
        $this->assertSame('Demo', $result['doc']['app<App>']['name']);
        $this->assertSame(['./shared.wafl'], $result['meta']['imports']);
        $this->assertNotNull($result['meta']['schema']);
        $this->assertSame(dirname($fixture), $result['meta']['baseDir']);
    }
}
