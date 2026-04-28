<?php

namespace App\Http\Controllers;

use App\Models\QmsDynamicField;
use App\Support\QmsTemplateModules;
use InvalidArgumentException;

class QmsDynamicFieldController extends Controller
{
    public function index(string $module)
    {
        try {
            $module = QmsTemplateModules::ensureAllowed($module);
        } catch (InvalidArgumentException) {
            abort(404, 'QMS module not found.');
        }

        $fields = QmsDynamicField::query()
            ->forModule($module)
            ->active()
            ->sorted()
            ->get()
            ->map(fn(QmsDynamicField $field) => [
                'id' => $field->id,
                'label' => $field->label,
                'field_key' => $field->field_key,
                'field_type' => $field->field_type,
                'is_required' => (bool) $field->is_required,
                'sort_order' => (int) $field->sort_order,
            ])
            ->values();

        return response()->json([
            'fields' => $fields,
        ]);
    }

    public function dcr()
    {
        return $this->index(QmsTemplateModules::DCR);
    }
}
