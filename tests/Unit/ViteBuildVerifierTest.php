<?php

namespace Tests\Unit;

use App\Support\ViteBuildVerifier;
use RuntimeException;
use Tests\TestCase;

final class ViteBuildVerifierTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'raoza-vite-'.bin2hex(random_bytes(6));
        mkdir($this->directory.DIRECTORY_SEPARATOR.'assets', 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
            @unlink($file);
        }
        @unlink($this->directory.DIRECTORY_SEPARATOR.'manifest.json');
        @rmdir($this->directory.DIRECTORY_SEPARATOR.'assets');
        @rmdir($this->directory);
        parent::tearDown();
    }

    public function test_it_accepts_a_complete_build_and_rejects_missing_assets(): void
    {
        file_put_contents($this->directory.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'app.js', 'ok');
        file_put_contents($this->directory.DIRECTORY_SEPARATOR.'manifest.json', json_encode([
            'resources/js/app.tsx' => ['file' => 'assets/app.js', 'isEntry' => true],
        ], JSON_THROW_ON_ERROR));

        $this->assertSame(['entries' => 1, 'files' => 1], app(ViteBuildVerifier::class)->verify($this->directory));

        unlink($this->directory.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'app.js');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('missing asset');
        app(ViteBuildVerifier::class)->verify($this->directory);
    }
}
