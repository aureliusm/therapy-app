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
        File::put($this->electronPath.'/electron-builder.mjs', "export default {\n    mac: {\n        artifactName: 'app',\n    },\n};\n");
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

    public function test_enables_ad_hoc_mac_signing_only_once(): void
    {
        $this->artisan('app:install-electron-plugins', ['--electron-path' => $this->electronPath])->assertSuccessful();
        $this->artisan('app:install-electron-plugins', ['--electron-path' => $this->electronPath])->assertSuccessful();

        $config = File::get($this->electronPath.'/electron-builder.mjs');
        $this->assertSame(1, substr_count($config, "identity: '-',"));
        $this->assertSame(1, substr_count($config, 'hardenedRuntime: false,'));
        $this->assertMatchesRegularExpression("/mac: \\{\n        identity: '-',.*\n        hardenedRuntime: false,.*\n        artifactName: 'app',/", $config);
    }

    public function test_fails_when_builder_config_has_no_mac_section(): void
    {
        File::put($this->electronPath.'/electron-builder.mjs', "export default {};\n");

        $this->artisan('app:install-electron-plugins', ['--electron-path' => $this->electronPath])->assertFailed();
    }

    public function test_fails_when_electron_main_process_is_missing(): void
    {
        $this->artisan('app:install-electron-plugins', ['--electron-path' => $this->electronPath.'/missing'])->assertFailed();
    }
}
