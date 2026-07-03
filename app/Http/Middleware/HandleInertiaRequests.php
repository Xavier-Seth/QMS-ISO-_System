<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),

            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'username' => $request->user()->username,
                    'email' => $request->user()->email,
                    'role' => $request->user()->role,
                    'position' => $request->user()->position,
                    'department' => $request->user()->department,
                    'office_location' => $request->user()->office_location,
                    'profile_photo' => $request->user()->profile_photo
                        ? route('profile.photo').'?v='.md5($request->user()->profile_photo)
                        : null,
                ] : null,
            ],

            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],

            'notifications_unread_count' => fn () => $request->user()
                ? rescue(fn () => $request->user()->unreadNotifications()->count(), 0, true)
                : 0,

            'system_settings' => fn () => rescue(function () use ($request) {
                $settings = SystemSetting::first();

                if (! $settings) {
                    return $this->defaultSystemSettings();
                }

                return [
                    'system_name' => $settings->system_name,
                    'institution_name' => $settings->institution_name,
                    'office_name' => $settings->office_name,
                    'maintenance_mode' => $settings->maintenance_mode,
                    'e_signature_url' => $settings->e_signature_path && $request->user()?->can('admin-only')
                        ? route('settings.signature.image').'?v='.md5($settings->e_signature_path)
                        : null,
                    'logo_url' => $settings->logo_path
                        ? Storage::url($settings->logo_path)
                        : null,
                ];
            }, $this->defaultSystemSettings(), true),
        ];
    }

    /**
     * @return array{system_name: string, institution_name: string, office_name: string, maintenance_mode: bool, e_signature_url: null, logo_url: null}
     */
    private function defaultSystemSettings(): array
    {
        return [
            'system_name' => 'Quality Management System',
            'institution_name' => 'Leyte Normal University',
            'office_name' => 'QMS (ISO) Office',
            'maintenance_mode' => false,
            'e_signature_url' => null,
            'logo_url' => null,
        ];
    }
}
