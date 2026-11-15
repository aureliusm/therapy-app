<?php

namespace App\Jobs;

use App\Services\AppUpdater;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

class DownloadAppUpdate implements ShouldQueue
{
    use Queueable;

    public const CACHE_KEY = 'app-update.download';

    public int $tries = 1;

    public int $timeout = 1800;

    /**
     * @param  array{version: string, notes: ?string, dmg_url: ?string, html_url: ?string}  $latest
     */
    public function __construct(public array $latest) {}

    /**
     * @param  array{state: string, percent?: int, message?: ?string}  $state
     */
    public static function putState(array $state): void
    {
        Cache::put(self::CACHE_KEY, $state + ['percent' => 0, 'message' => null], now()->addHours(2));
    }

    public function handle(AppUpdater $updater): void
    {
        $lastPercent = -1;
        self::putState(['state' => 'downloading']);

        try {
            $message = $updater->install($this->latest, function (int $totalBytes, int $downloadedBytes) use (&$lastPercent): void {
                if ($totalBytes <= 0) {
                    return;
                }

                $percent = (int) floor($downloadedBytes / $totalBytes * 100);

                if ($percent !== $lastPercent) {
                    $lastPercent = $percent;
                    self::putState(['state' => 'downloading', 'percent' => $percent]);
                }
            });

            self::putState(['state' => 'done', 'percent' => 100, 'message' => $message]);
        } catch (Throwable $e) {
            self::putState(['state' => 'failed', 'message' => $e->getMessage()]);
        }
    }

    public function failed(?Throwable $exception): void
    {
        self::putState(['state' => 'failed', 'message' => $exception?->getMessage() ?: 'Prenos posodobitve ni uspel.']);
    }
}
