<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Services\ActivityLogService;
use App\Services\BackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function __construct(
        private ActivityLogService $activityLogService,
        private BackupService $backupService,
    ) {}

    public function index(): Response
    {
        $settings = SystemSetting::instance();

        return Inertia::render('Settings/Index', [
            'backup_settings' => [
                'backup_frequency' => $settings->backup_frequency,
                'storage_location' => $settings->storage_location,
                'auto_backup' => $settings->auto_backup,
                'latest_backup' => $this->backupService->getLatestBackup(),
            ],
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],

            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],

            'position' => ['nullable', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'office_location' => ['nullable', 'string', 'max:255'],

            'current_password' => ['nullable', 'required_with:new_password', 'current_password'],
            'new_password' => ['nullable', 'string', 'min:8', 'confirmed'],

            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $fullName = collect([
            $validated['first_name'] ?? '',
            $validated['middle_name'] ?? '',
            $validated['last_name'] ?? '',
        ])->filter(fn ($value) => filled($value))->implode(' ');

        $user->name = $fullName;
        $user->email = $validated['email'];
        $user->position = $validated['position'] ?? null;
        $user->department = $validated['department'] ?? null;
        $user->office_location = $validated['office_location'] ?? null;

        if ($request->hasFile('profile_photo')) {
            if ($user->profile_photo && Storage::disk('public')->exists($user->profile_photo)) {
                Storage::disk('public')->delete($user->profile_photo);
            }

            $user->profile_photo = $request->file('profile_photo')->store('profile-photos', 'public');
        }

        if (! empty($validated['new_password'])) {
            $user->password = $validated['new_password'];
        }

        $user->save();

        return back()->with('success', 'Profile updated successfully.');
    }

    public function updateSystem(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'system_name' => ['required', 'string', 'max:255'],
            'institution_name' => ['required', 'string', 'max:255'],
            'office_name' => ['required', 'string', 'max:255'],
            'maintenance_mode' => ['boolean'],
        ]);

        $settings = SystemSetting::instance();
        $oldValues = $settings->only(['system_name', 'institution_name', 'office_name', 'maintenance_mode']);

        $settings->update($validated);

        $this->activityLogService->log([
            'module' => 'settings',
            'action' => 'updated',
            'entity_type' => SystemSetting::class,
            'entity_id' => $settings->id,
            'record_label' => 'General Settings',
            'description' => 'Updated general system settings.',
            'old_values' => $oldValues,
            'new_values' => $validated,
        ]);

        return back()->with('success', 'General settings updated successfully.');
    }

    public function uploadSignature(Request $request): RedirectResponse
    {
        $request->validate([
            'e_signature' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $settings = SystemSetting::instance();

        if ($settings->e_signature_path && Storage::disk('public')->exists($settings->e_signature_path)) {
            Storage::disk('public')->delete($settings->e_signature_path);
        }

        $path = $request->file('e_signature')->store('signatures', 'public');
        $settings->update(['e_signature_path' => $path]);

        $this->activityLogService->log([
            'module' => 'settings',
            'action' => 'uploaded',
            'entity_type' => SystemSetting::class,
            'entity_id' => $settings->id,
            'record_label' => 'E-Signature',
            'description' => 'Uploaded authorized e-signature image.',
        ]);

        return back()->with('success', 'E-signature uploaded successfully.');
    }

    public function removeSignature(): RedirectResponse
    {
        $settings = SystemSetting::instance();

        if ($settings->e_signature_path && Storage::disk('public')->exists($settings->e_signature_path)) {
            Storage::disk('public')->delete($settings->e_signature_path);
        }

        $settings->update(['e_signature_path' => null]);

        $this->activityLogService->log([
            'module' => 'settings',
            'action' => 'removed',
            'entity_type' => SystemSetting::class,
            'entity_id' => $settings->id,
            'record_label' => 'E-Signature',
            'description' => 'Removed authorized e-signature image.',
        ]);

        return back()->with('success', 'E-signature removed successfully.');
    }

    public function uploadLogo(Request $request): RedirectResponse
    {
        $request->validate([
            'logo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $settings = SystemSetting::instance();

        if ($settings->logo_path && Storage::disk('public')->exists($settings->logo_path)) {
            Storage::disk('public')->delete($settings->logo_path);
        }

        $path = $request->file('logo')->store('settings', 'public');
        $settings->update(['logo_path' => $path]);

        $this->activityLogService->log([
            'module' => 'settings',
            'action' => 'uploaded',
            'entity_type' => SystemSetting::class,
            'entity_id' => $settings->id,
            'record_label' => 'System Logo',
            'description' => 'Uploaded system logo image.',
        ]);

        return back()->with('success', 'Logo uploaded successfully.');
    }

    public function removeLogo(): RedirectResponse
    {
        $settings = SystemSetting::instance();

        if ($settings->logo_path && Storage::disk('public')->exists($settings->logo_path)) {
            Storage::disk('public')->delete($settings->logo_path);
        }

        $settings->update(['logo_path' => null]);

        $this->activityLogService->log([
            'module' => 'settings',
            'action' => 'removed',
            'entity_type' => SystemSetting::class,
            'entity_id' => $settings->id,
            'record_label' => 'System Logo',
            'description' => 'Removed system logo image.',
        ]);

        return back()->with('success', 'Logo removed successfully.');
    }
}
