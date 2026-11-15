<?php

namespace App\Providers;

use Native\Desktop\Contracts\ProvidesPhpIni;
use Native\Desktop\Facades\Window;

class NativeAppServiceProvider implements ProvidesPhpIni
{
    /**
     * Executed once the native application has been booted.
     * Use this method to open windows, register global shortcuts, etc.
     */
    public function boot(): void
    {
        Window::open()
            ->title(config('app.name'))
            ->width(1280)
            ->height(860)
            ->minWidth(960)
            ->minHeight(680)
            ->rememberState()
            ->route('clients.index');
    }

    /**
     * Return an array of php.ini directives to be set.
     */
    public function phpIni(): array
    {
        return [
            'memory_limit' => '512M',
            'max_execution_time' => '0',
            'upload_max_filesize' => '64M',
            'post_max_size' => '64M',
        ];
    }
}
