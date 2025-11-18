<?php

declare(strict_types=1);

namespace Wafl\Core\Tests;

use Wafl\Core\ConfigLoader;
use PHPUnit\Framework\TestCase;

final class ConfigLoaderTest extends TestCase
{
    public function testLoadsResolvesEvaluatesAndValidatesFixture(): void
    {
        $fixture = __DIR__ . '/fixtures/base.wafl';
        $loader = new ConfigLoader();

        $result = $loader->load($fixture, [
            'env' => [
                'SHOW_EXTRA' => true,
            ],
        ]);

        $config = $result['config'];

        $this->assertSame('Demo', $config['app']['name']);
        $this->assertSame(2, $config['app']['version']);
        $this->assertSame('rgb(255, 0, 0)', $config['app']['colors']['primary']);
        $this->assertSame(['first', 'second'], $config['list']);
        $this->assertSame('import', $config['shared']['from']);
        $this->assertNotEmpty($result['meta']['schema']);
    }
}
