<?php

namespace App\Http\Controllers;

use App\Models\QmsDynamicField;
use App\Models\QmsTemplate;
use App\Services\ActivityLogService;
use App\Services\QmsDynamicFieldValidator;
use App\Support\QmsTemplateModules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class QmsTemplateSettingsController extends Controller
{
    public function __construct(
        protected QmsDynamicFieldValidator $dynamicFieldValidator,
        protected ActivityLogService $activityLogService,
    ) {
    }

    public function index(string $module)
    {
        $module = $this->resolveModule($module);

        $activeTemplate = QmsTemplate::query()
            ->forModule($module)
            ->active()
            ->latestFirst()
            ->first();

        $templates = QmsTemplate::query()
            ->forModule($module)
            ->latestFirst()
            ->get()
            ->map(fn(QmsTemplate $template) => $this->serializeTemplate($template))
            ->values();

        $fields = QmsDynamicField::query()
            ->forModule($module)
            ->sorted()
            ->get()
            ->map(fn(QmsDynamicField $field) => $this->serializeField($field))
            ->values();

        return response()->json([
            'module' => $module,
            'active_template' => $activeTemplate
                ? $this->serializeTemplate($activeTemplate)
                : null,
            'templates' => $templates,
            'fields' => $fields,
        ]);
    }

    public function uploadTemplate(Request $request, string $module)
    {
        abort_unless(auth()->user()?->role === 'admin', 403, 'Unauthorized.');

        $module = $this->resolveModule($module);

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'template_file' => [
                'required',
                'file',
                'mimes:docx',
                'max:20480',
            ],
            'set_active' => ['nullable', 'boolean'],
        ]);

        $file = $request->file('template_file');
        $setActive = $request->boolean('set_active', true);

        $originalFileName = $file->getClientOriginalName();
        $baseName = pathinfo($originalFileName, PATHINFO_FILENAME);

        $safeBaseName = Str::of($baseName)
            ->replaceMatches('/[^A-Za-z0-9 _\-\(\)]/', '')
            ->trim()
            ->replaceMatches('/\s+/', ' ')
            ->value();

        if ($safeBaseName === '') {
            $safeBaseName = "{$module} Template";
        }

        $storedFileName = now()->format('Ymd_His') . '_' . Str::slug($safeBaseName, '_') . '.docx';
        $storageDisk = $this->templateStorageDisk();
        $storedPath = $file->storeAs(
            'qms/templates/' . strtolower($module),
            $storedFileName,
            $storageDisk
        );

        try {
            $template = DB::transaction(function () use ($module, $validated, $originalFileName, $storedFileName, $storedPath, $storageDisk, $setActive) {
                if ($setActive) {
                    DB::table('qms_templates')
                        ->where('module', $module)
                        ->where('is_active', true)
                        ->lockForUpdate()
                        ->get();

                    QmsTemplate::query()
                        ->forModule($module)
                        ->where('is_active', true)
                        ->update(['is_active' => false]);
                }

                return QmsTemplate::create([
                    'module' => $module,
                    'name' => $validated['name'] ?? $originalFileName,
                    'original_file_name' => $originalFileName,
                    'file_name' => $storedFileName,
                    'file_path' => $storedPath,
                    'storage_disk' => $storageDisk,
                    'is_active' => $setActive,
                    'uploaded_by' => auth()->id(),
                ]);
            });
        } catch (\Throwable $e) {
            Storage::disk($storageDisk)->delete($storedPath);
            throw $e;
        }

        $this->activityLogService->log([
            'module' => 'settings',
            'action' => 'uploaded',
            'record_label' => $template->name,
            'file_type' => 'docx',
            'description' => "Uploaded {$module} template: {$template->name}",
        ]);

        return response()->json([
            'message' => "{$module} template uploaded successfully.",
            'template' => $this->serializeTemplate($template),
        ]);
    }

    public function setActiveTemplate(string $module, QmsTemplate $template)
    {
        abort_unless(auth()->user()?->role === 'admin', 403, 'Unauthorized.');

        $module = $this->resolveModule($module);

        abort_unless(
            $template->isForModule($module),
            404,
            "{$module} template not found."
        );

        DB::transaction(function () use ($module, $template) {
            DB::table('qms_templates')
                ->where('module', $module)
                ->where('is_active', true)
                ->lockForUpdate()
                ->get();

            QmsTemplate::query()
                ->forModule($module)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $template->update([
                'is_active' => true,
            ]);
        });

        $this->activityLogService->log([
            'module' => 'settings',
            'action' => 'updated',
            'record_label' => $template->name,
            'description' => "Set active {$module} template to: {$template->name}",
        ]);

        return response()->json([
            'message' => "{$module} active template updated successfully.",
            'template_id' => $template->id,
        ]);
    }

    public function storeField(Request $request, string $module)
    {
        abort_unless(auth()->user()?->role === 'admin', 403, 'Unauthorized.');

        $module = $this->resolveModule($module);

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'field_key' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[A-Za-z][A-Za-z0-9_]*$/',
                'unique:qms_dynamic_fields,field_key,NULL,id,module,' . $module,
            ],
            'field_type' => ['required', 'string', 'in:text,textarea,date'],
            'is_required' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $fieldKey = $validated['field_key'] ?? $this->makeFieldKey($module, $validated['label']);

        $this->dynamicFieldValidator->ensureFieldKeyIsNotReserved($module, $fieldKey);

        $field = QmsDynamicField::create([
            'module' => $module,
            'label' => $validated['label'],
            'field_key' => $fieldKey,
            'field_type' => $validated['field_type'],
            'is_required' => $request->boolean('is_required', false),
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        $this->activityLogService->log([
            'module' => 'settings',
            'action' => 'created',
            'record_label' => $field->label,
            'description' => "Created {$module} dynamic field: {$field->label} ({$field->field_key})",
        ]);

        return response()->json([
            'message' => "{$module} dynamic field created successfully.",
            'field' => $this->serializeField($field),
        ]);
    }

    public function updateField(Request $request, string $module, QmsDynamicField $field)
    {
        abort_unless(auth()->user()?->role === 'admin', 403, 'Unauthorized.');

        $module = $this->resolveModule($module);

        abort_unless(
            strtoupper((string) $field->module) === $module,
            404,
            "{$module} dynamic field not found."
        );

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'field_key' => [
                'required',
                'string',
                'max:255',
                'regex:/^[A-Za-z][A-Za-z0-9_]*$/',
                'unique:qms_dynamic_fields,field_key,' . $field->id . ',id,module,' . $module,
            ],
            'field_type' => ['required', 'string', 'in:text,textarea,date'],
            'is_required' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $this->dynamicFieldValidator->ensureFieldKeyIsNotReserved($module, $validated['field_key']);

        $field->update([
            'label' => $validated['label'],
            'field_key' => $validated['field_key'],
            'field_type' => $validated['field_type'],
            'is_required' => $request->boolean('is_required', false),
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        $this->activityLogService->log([
            'module' => 'settings',
            'action' => 'updated',
            'record_label' => $field->label,
            'description' => "Updated {$module} dynamic field: {$field->label} ({$field->field_key})",
        ]);

        return response()->json([
            'message' => "{$module} dynamic field updated successfully.",
            'field' => $this->serializeField($field),
        ]);
    }

    public function destroyField(string $module, QmsDynamicField $field)
    {
        abort_unless(auth()->user()?->role === 'admin', 403, 'Unauthorized.');

        $module = $this->resolveModule($module);

        abort_unless(
            strtoupper((string) $field->module) === $module,
            404,
            "{$module} dynamic field not found."
        );

        $field->delete();

        $this->activityLogService->log([
            'module' => 'settings',
            'action' => 'deleted',
            'record_label' => $field->label,
            'description' => "Deleted {$module} dynamic field: {$field->label} ({$field->field_key})",
        ]);

        return response()->json([
            'message' => "{$module} dynamic field deleted successfully.",
        ]);
    }

    protected function templateStorageDisk(): string
    {
        return 'private';
    }

    private function resolveModule(string $module): string
    {
        try {
            return QmsTemplateModules::ensureAllowed($module);
        } catch (InvalidArgumentException) {
            abort(404, 'QMS module not found.');
        }
    }

    private function makeFieldKey(string $module, string $label): string
    {
        $key = Str::of($label)
            ->replaceMatches('/[^A-Za-z0-9 ]/', ' ')
            ->trim()
            ->squish()
            ->camel()
            ->value();

        if ($key === '') {
            $key = 'customField';
        }

        if (!preg_match('/^[A-Za-z]/', $key)) {
            $key = 'field' . ucfirst($key);
        }

        $originalKey = $key;
        $counter = 2;

        while (
            QmsDynamicField::query()
                ->forModule($module)
                ->where('field_key', $key)
                ->exists()
        ) {
            $key = $originalKey . $counter;
            $counter++;
        }

        return $key;
    }

    private function serializeTemplate(QmsTemplate $template): array
    {
        return [
            'id' => $template->id,
            'module' => $template->module,
            'name' => $template->name,
            'original_file_name' => $template->original_file_name,
            'file_name' => $template->file_name,
            'file_path' => $template->file_path,
            'storage_disk' => $template->storage_disk,
            'is_active' => (bool) $template->is_active,
            'uploaded_by' => $template->uploaded_by,
            'created_at' => $template->created_at,
            'updated_at' => $template->updated_at,
        ];
    }

    private function serializeField(QmsDynamicField $field): array
    {
        return [
            'id' => $field->id,
            'module' => $field->module,
            'label' => $field->label,
            'field_key' => $field->field_key,
            'field_type' => $field->field_type,
            'is_required' => (bool) $field->is_required,
            'is_active' => (bool) $field->is_active,
            'sort_order' => (int) $field->sort_order,
            'created_at' => $field->created_at,
            'updated_at' => $field->updated_at,
        ];
    }
}
