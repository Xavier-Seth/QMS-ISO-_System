<?php

namespace App\Policies;

use App\Models\DocumentType;
use App\Models\User;

class DocumentTypePolicy
{
    /**
     * View a manual page slot/type.
     *
     * Rules:
     * - Controlled manual: admin only
     * - Uncontrolled manual: any authenticated user
     */
    public function viewManual(User $user, DocumentType $documentType): bool
    {
        if (!$documentType->isManual()) {
            return false;
        }

        if ($documentType->isControlledManual()) {
            return $user->role === 'admin';
        }

        if ($documentType->isUncontrolledManual()) {
            return true;
        }

        return false;
    }

    /**
     * Upload / replace manual file.
     *
     * Rule:
     * - Only admin can upload or replace manuals
     */
    public function manageManual(User $user, DocumentType $documentType): bool
    {
        return $documentType->isManual() && $user->role === 'admin';
    }

    /**
     * Preview/download a specific manual upload.
     *
     * Rules are based on the parent manual type:
     * - Controlled manual: admin only
     * - Uncontrolled manual: any authenticated user
     */
    public function accessManualFile(User $user, DocumentType $documentType): bool
    {
        return $this->viewManual($user, $documentType);
    }
}