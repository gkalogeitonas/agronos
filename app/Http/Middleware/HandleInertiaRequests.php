<?php

namespace App\Http\Middleware;

use App\Enums\DeviceStatus;
use App\Enums\SensorType;
use App\Models\Device;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Session keys that controllers flash with `->with()` and the layout shows as toasts.
     *
     * @var list<string>
     */
    private const FLASH_MESSAGE_KEYS = ['success', 'error', 'info'];

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $this->flashSessionMessages($request);

        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user(),
            ],
            'ziggy' => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
            'lowBatteryPercent' => Device::lowBatteryPercent(),
            'enums' => [
                'deviceStatuses' => DeviceStatus::options(),
                'sensorTypes' => SensorType::options(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * Hand the messages flashed to the session over to Inertia flash data. The
     * client receives Inertia flash data once and keeps it out of browser
     * history, so partial reloads and the back button do not repeat a toast.
     */
    private function flashSessionMessages(Request $request): void
    {
        if (! $request->hasSession()) {
            return;
        }

        $messages = array_filter($request->session()->only(self::FLASH_MESSAGE_KEYS));

        if ($messages !== []) {
            Inertia::flash($messages);
        }
    }
}
