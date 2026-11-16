<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class InstallElectronPluginsCommandTest extends TestCase
{
    private string $electronPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->electronPath = sys_get_temp_dir().'/electron-'.Str::random(8);
        File::ensureDirectoryExists($this->electronPath.'/src/main');
        File::put($this->electronPath.'/src/main/index.js', "import NativePHP from '#plugin';\n");
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->electronPath);

        parent::tearDown();
    }

    public function test_imports_plugins_into_electron_main_process_only_once(): void
    {
        $this->artisan('app:install-electron-plugins', ['--electron-path' => $this->electronPath])->assertSuccessful();
        $this->artisan('app:install-electron-plugins', ['--electron-path' => $this->electronPath])->assertSuccessful();

        $main = File::get($this->electronPath.'/src/main/index.js');
        $this->assertSame(1, substr_count($main, "import './app-plugins/microphone-access.js';"));
        $this->assertStringContainsString("import NativePHP from '#plugin';", $main);
        $this->assertFileEquals(
            resource_path('electron/plugins/microphone-access.js'),
            $this->electronPath.'/src/main/app-plugins/microphone-access.js',
        );
    }

    public function test_fails_when_electron_main_process_is_missing(): void
    {
        $this->artisan('app:install-electron-plugins', ['--electron-path' => $this->electronPath.'/missing'])->assertFailed();
    }
}
