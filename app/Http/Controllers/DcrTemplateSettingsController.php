<?php

namespace App\Http\Controllers;

use App\Models\QmsDynamicField;
use App\Models\QmsTemplate;
use App\Support\QmsTemplateModules;
use Illuminate\Http\Request;

class DcrTemplateSettingsController extends Controller
{
    public function __construct(
        protected QmsTemplateSettingsController $settingsController
    ) {
    }

    public function index()
    {
        return $this->settingsController->index(QmsTemplateModules::DCR);
    }

    public function uploadTemplate(Request $request)
    {
        return $this->settingsController->uploadTemplate($request, QmsTemplateModules::DCR);
    }

    public function setActiveTemplate(QmsTemplate $template)
    {
        return $this->settingsController->setActiveTemplate(QmsTemplateModules::DCR, $template);
    }

    public function storeField(Request $request)
    {
        return $this->settingsController->storeField($request, QmsTemplateModules::DCR);
    }

    public function updateField(Request $request, QmsDynamicField $field)
    {
        return $this->settingsController->updateField($request, QmsTemplateModules::DCR, $field);
    }

    public function destroyField(QmsDynamicField $field)
    {
        return $this->settingsController->destroyField(QmsTemplateModules::DCR, $field);
    }
}
