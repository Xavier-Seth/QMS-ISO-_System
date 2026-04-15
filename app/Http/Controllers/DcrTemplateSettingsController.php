<?php

namespace App\Http\Controllers;

use App\Models\QmsDynamicField;
use App\Models\QmsTemplate;
use App\Services\DcrDynamicFieldValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DcrTemplateSettingsController extends Controller
{
    private const MODULE = 'DCR';

    public function __construct(
        protected DcrDynamicFieldValidator $dynamicFieldValidator
    ) {
    }

    public function index()
    {
        $activeTemplate = QmsTemplate::query()
            ->forModule(self::MODULE)
            ->active()
            ->latestFirst()
            ->first();

        $templates = QmsTemplate::query()
            ->forModule(self::MODULE)
            ->latestFirst()
            ->get()
            ->map(fn(QmsTemplate $template) => [
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
            ])
            ->values();

        $fields = QmsDynamicField::query()
            ->forModule(self::MODULE)
            ->sorted()
            ->get()
            ->map(fn(QmsDynamicField $field) => [
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
            ])
            ->values();

        return response()->json([
            'module' => self::MODULE,
            'active_template' => $activeTemplate ? [
                'id' => $activeTemplate->id,
                'module' => $activeTemplate->module,
                'name' => $activeTemplate->name,
                'original_file_name' => $activeTemplate->original_file_name,
                'file_name' => $activeTemplate->file_name,
                'file_path' => $activeTemplate->file_path,
                'storage_disk' => $activeTemplate->storage_disk,
                'is_active' => (bool) $activeTemplate->is_active,
                'uploaded_by' => $activeTemplate->uploaded_by,
                'created_at' => $activeTemplate->created_at,
                'updated_at' => $activeTemplate->updated_at,
            ] : null,
            'templates' => $templates,
            'fields' => $fields,
        ]);
    }

    public function uploadTemplate(Request $request)
    {
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
            $safeBaseName = 'DCR Template';
        }

        $storedFileName = now()->format('Ymd_His') . '_' . Str::slug($safeBaseName, '_') . '.docx';
        $storedPath = $file->storeAs('qms/templates/dcr', $storedFileName, 'public');

        $template = DB::transaction(function () use ($validated, $originalFileName, $storedFileName, $storedPath, $setActive) {
            if ($setActive) {
                QmsTemplate::query()
                    ->forModule(self::MODULE)
                    ->where('is_active', true)
                    ->update(['is_active' => false]);
            }

            return QmsTemplate::create([
                'module' => self::MODULE,
                'name' => $validated['name'] ?? $originalFileName,
                'original_file_name' => $originalFileName,
                'file_name' => $storedFileName,
                'file_path' => $storedPath,
                'storage_disk' => 'public',
                'is_active' => $setActive,
                'uploaded_by' => auth()->id(),
            ]);
        });

        return response()->json([
            'message' => 'DCR template uploaded successfully.',
            'template' => [
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
            ],
        ]);
    }

    public function setActiveTemplate(QmsTemplate $template)
    {
        abort_unless(
            $template->isForModule(self::MODULE),
            404,
            'DCR template not found.'
        );

        DB::transaction(function () use ($template) {
            QmsTemplate::query()
                ->forModule(self::MODULE)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $template->update([
                'is_active' => true,
            ]);
        });

        return response()->json([
            'message' => 'DCR active template updated successfully.',
            'template_id' => $template->id,
        ]);
    }

    public function storeField(Request $request)
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'field_key' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[A-Za-z][A-Za-z0-9_]*$/',
                'unique:qms_dynamic_fields,field_key,NULL,id,module,' . self::MODULE,
            ],
            'field_type' => ['required', 'string', 'in:text,textarea,date'],
            'is_required' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $fieldKey = $validated['field_key'] ?? $this->makeFieldKey($validated['label']);

        $this->dynamicFieldValidator->ensureFieldKeyIsNotReserved($fieldKey);

        $field = QmsDynamicField::create([
            'module' => self::MODULE,
            'label' => $validated['label'],
            'field_key' => $fieldKey,
            'field_type' => $validated['field_type'],
            'is_required' => $request->boolean('is_required', false),
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return response()->json([
            'message' => 'DCR dynamic field created successfully.',
            'field' => [
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
            ],
        ]);
    }

    public function updateField(Request $request, QmsDynamicField $field)
    {
        abort_unless(
            strtoupper((string) $field->module) === self::MODULE,
            404,
            'DCR dynamic field not found.'
        );

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'field_key' => [
                'required',
                'string',
                'max:255',
                'regex:/^[A-Za-z][A-Za-z0-9_]*$/',
                'unique:qms_dynamic_fields,field_key,' . $field->id . ',id,module,' . self::MODULE,
            ],
            'field_type' => ['required', 'string', 'in:text,textarea,date'],
            'is_required' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $this->dynamicFieldValidator->ensureFieldKeyIsNotReserved($validated['field_key']);

        $field->update([
            'label' => $validated['label'],
            'field_key' => $validated['field_key'],
            'field_type' => $validated['field_type'],
            'is_required' => $request->boolean('is_required', false),
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return response()->json([
            'message' => 'DCR dynamic field updated successfully.',
            'field' => [
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
            ],
        ]);
    }

    public function destroyField(QmsDynamicField $field)
    {
        abort_unless(
            strtoupper((string) $field->module) === self::MODULE,
            404,
            'DCR dynamic field not found.'
        );

        $field->delete();

        return response()->json([
            'message' => 'DCR dynamic field deleted successfully.',
        ]);
    }

    private function makeFieldKey(string $label): string
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
                ->forModule(self::MODULE)
                ->where('field_key', $key)
                ->exists()
        ) {
            $key = $originalKey . $counter;
            $counter++;
        }

        return $key;
    }
}
