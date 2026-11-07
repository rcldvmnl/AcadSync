<?php
/**
 * GET  /api/undo.php  -> what Undo would reverse next: {size, top: label}
 * POST /api/undo.php  -> POP the latest action off the student's UndoStack and reverse it
 *
 * [DSA: STACK] — every reversible action PUSHES an UndoAction object (see undo_push in services.php):
 *   leave a group         -> RejoinGroupAction  (rejoin, or re-make the group if it was deleted)
 *   cancel a join request -> RequestAgainAction (send the request again)
 *   mark a task done/not  -> ToggleDoneAction   (set it back)
 * [OOP: POLYMORPHISM] — this endpoint never checks which kind it popped: it calls $action->undo()
 * and the object's own class decides what that means. Undo always reverses the MOST RECENT action.
 */

declare(strict_types=1);
require_once __DIR__ . '/../backend/bootstrap.php';

$user = current_member();
$ctx = load_context($user);
$sectionId = (int) $ctx['section']['id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stack = undo_stack($sectionId);
    respond(['size' => $stack->size(), 'top' => $stack->peek()?->label()]);
}

require_method('POST');
$action = undo_pop($sectionId);
if ($action === null) {
    fail('Nothing to undo.', 409);
}

$context = new UndoContext($sectionId, (int) $user['id'], trim($user['first_name'] . ' ' . $user['last_name']), $ctx['taskIndex'], load_groups($sectionId));
try {
    $action->undo($context);      // polymorphic call
} catch (UndoNotPossible $e) {
    fail($e->getMessage(), 409);
}

respond(['undone' => $action->label(), 'size' => undo_stack($sectionId)->size()]);
