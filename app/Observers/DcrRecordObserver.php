<?php

namespace App\Observers;

/**
 * Draft activity (record creation, edits, and deletion) is intentionally not
 * audit-logged — only open drafts can ever be deleted (destroy() state
 * guard), so no deletion logging exists here. Formal actions —
 * submitted/approved/rejected/published/resolution changes — are logged
 * once each by DcrRecordController.
 */
class DcrRecordObserver {}
