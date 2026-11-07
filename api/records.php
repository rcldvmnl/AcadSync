<?php
/**
 * Member records and officer assignments.
 *
 * GET  /api/records.php            -> every member's record, ranked by attendance (officers);
 *                                     a member gets only their own
 * GET  /api/records.php?id=7       -> one member's full record (officers, or the member themself)
 * POST action=assign_officer {position: vice_president|secretary, user_id|null}   the President
 *      action=set_status     {user_id, status: active|inactive|alumni}           Secretary / President
 *
 * [OOP] Person::fromRow() gives a Member, VicePresident, Secretary or President; each status is a
 *       MemberStatus subclass; MemberRecord works out attendance by asking each Activity.
 * [DSA] MERGE SORT ranks members by attendance; a HASHSET keeps one office per person.
 */

declare(strict_types=1);
require_once __DIR__ . '/../backend/bootstrap.php';

$user = current_member();
$me = Person::fromRow($user);
$ctx = load_context($user);
$sectionId = (int) $ctx['section']['id'];
$uid = $me->id();

/** Section members by id (with their membership row id, role, position and status). */
$members = [];
foreach ($ctx['members'] as $m) {
    $members[(int) $m['id']] = $m;
}

/** The President, Vice President and Secretary right now. */
$officers = function () use (&$members): array {   // by reference: $members is reloaded after a change
    $out = ['president' => null, Person::VICE_PRESIDENT => null, Person::SECRETARY => null];
    foreach ($members as $m) {
        $p = Person::fromRow($m);
        if ($p->isOfficer()) {
            $out[$p->position()] = ['id' => $p->id(), 'name' => $p->fullName(), 'student_id' => $p->studentId()];
        }
    }
    return $out;
};

/* ------------------------------------------------------------------ reads */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $records = member_records($ctx);
    $canViewAll = $me->can(Permission::VIEW_MEMBER_RECORDS);

    if (param('id')) {
        $id = (int) param('id');
        if ($id !== $uid && !$canViewAll) {
            fail('You can only open your own record.', 403);
        }
        $record = null;
        foreach ($records as $r) {
            if ($r->person()->id() === $id) {
                $record = $r;
            }
        }
        if ($record === null) {
            fail('That member isn’t in this section.', 404);
        }
        $groups = [];
        foreach (load_groups($sectionId) as $g) {
            if (in_array($id, $g['members'], true)) {
                $task = $g['task_id'] ? ($ctx['taskIndex'][(int) $g['task_id']] ?? null) : null;
                $groups[] = ['id' => $g['id'], 'name' => $g['name'], 'task' => $task['title'] ?? null, 'size' => count($g['members'])];
            }
        }
        $requests = 0;
        foreach ($ctx['tasks'] as $t) {
            $requests += (int) $t['requested_by'] === $id && $t['requested_by_role'] !== 'admin' ? 1 : 0;
        }
        respond([
            'record' => $record->toArray(true) + ['group_list' => $groups, 'requests' => $requests],
            'statuses' => array_map(fn (MemberStatus $s) => $s->toArray(), MemberStatus::all()),
            'can_update' => $me->can(Permission::UPDATE_MEMBER_RECORDS) && !$record->person()->can(Permission::MANAGE_SECTION),
            'is_me' => $id === $uid,
        ]);
    }

    // [ALGORITHM: MERGE SORT] — best attendance first (rate, then how many attended)
    $rows = [];
    foreach ($records as $r) {
        if ($canViewAll || $r->person()->id() === $uid) {
            $rows[] = $r->toArray();
        }
    }
    $steps = 0;
    $rows = Algorithms::mergeSortBy($rows, 'rank_key', true, $steps);
    respond([
        'records' => $rows,
        'officers' => $officers(),
        'statuses' => array_map(fn (MemberStatus $s) => $s->toArray(), MemberStatus::all()),
        'can_view_all' => $canViewAll,
        'can_assign' => $me->can(Permission::ASSIGN_OFFICERS),
        'can_update' => $me->can(Permission::UPDATE_MEMBER_RECORDS),
        'sorted_by' => 'Merge Sort', 'steps' => $steps,
    ]);
}

/* ------------------------------------------------------------------ writes */
require_method('POST');

switch (param('action')) {
    case 'assign_officer': {
        if (!$me->can(Permission::ASSIGN_OFFICERS)) {
            fail('Only the President chooses the Vice President and Secretary.', 403);
        }
        $position = (string) param('position', '');
        if ($position !== Person::VICE_PRESIDENT && $position !== Person::SECRETARY) {
            fail('Pick Vice President or Secretary.');
        }
        $title = $position === Person::VICE_PRESIDENT ? 'Vice President' : 'Secretary';
        $newId = param('user_id') ? (int) param('user_id') : null;

        // [DSA: HASHSET] — who already holds an office (one office per person)
        $holders = new HashSet();
        $current = null;
        foreach ($members as $m) {
            $p = Person::fromRow($m);
            if ($p->isOfficer()) {
                $holders->add((string) $p->id());
            }
            if ($p->position() === $position) {
                $current = $m;
            }
        }
        $candidate = null;
        if ($newId !== null) {
            $candidate = $members[$newId] ?? null;
            if ($candidate === null) {
                fail('Pick someone in this section.', 404);
            }
            $p = Person::fromRow($candidate);
            if ($current !== null && (int) $current['id'] === $newId) {
                respond(['officers' => $officers()]);                  // already in that office
            }
            if ($holders->contains((string) $newId)) {
                fail("{$p->fullName()} is already the {$p->roleLabel()}. Choose someone else, or change that office first.", 409);
            }
            if (!$p->status()->canHoldOffice()) {                     // [POLYMORPHISM] the status decides
                fail("{$p->fullName()} is {$p->status()->label()}, so they can’t hold an office.", 409);
            }
        }

        Store::begin();
        if ($current !== null) {                                       // the old holder steps down
            Store::update('memberships', (int) $current['membership_id'], ['position' => null]);
            end_officer_term($sectionId, (int) $current['id']);
        }
        if ($candidate !== null) {
            Store::update('memberships', (int) $candidate['membership_id'], ['position' => $position]);
            Store::insert('officer_terms', ['section_id' => $sectionId, 'user_id' => $newId, 'position' => $position]);
        }
        Store::commit();
        if ($candidate !== null) {
            notify($newId, $sectionId, 'members', "You’re now the $title of {$ctx['section']['name']}. You can review the approval queue.", 'queue.html');
        }
        if ($current !== null && (int) $current['id'] !== $newId) {
            notify((int) $current['id'], $sectionId, 'members', "You’re no longer the $title of {$ctx['section']['name']}.", 'records.html?id=' . (int) $current['id']);
        }
        // Fresh officer list after the change
        $ctx = load_context($user);
        $members = [];
        foreach ($ctx['members'] as $m) {
            $members[(int) $m['id']] = $m;
        }
        respond(['officers' => $officers()]);
    }

    case 'set_status': {
        if (!$me->can(Permission::UPDATE_MEMBER_RECORDS)) {
            fail('Only the Secretary or the President updates member records.', 403);
        }
        $id = (int) param('user_id', 0);
        $m = $members[$id] ?? null;
        if ($m === null) {
            fail('Pick someone in this section.', 404);
        }
        $key = (string) param('status', '');
        $status = MemberStatus::fromString($key);
        if ($status->key() !== $key) {
            fail('Pick Active, Inactive or Alumni.');
        }
        $person = Person::fromRow($m);
        if ($person->can(Permission::MANAGE_SECTION)) {
            fail('The President always stays active.', 409);
        }
        Store::begin();
        $changes = ['status' => $status->key()];
        if ($person->isOfficer() && !$status->canHoldOffice()) {      // an inactive / alumni officer steps down
            $changes['position'] = null;
            end_officer_term($sectionId, $id);
        }
        Store::update('memberships', (int) $m['membership_id'], $changes);
        Store::commit();
        if ($id !== $uid) {
            notify($id, $sectionId, 'members', "Your membership status is now {$status->label()}.", "records.html?id=$id");
        }
        // array_key_exists, not isset: the stored value is null ("no office"), which isset() would miss
        respond(['status' => $status->toArray(), 'stepped_down' => array_key_exists('position', $changes)]);
    }

    default:
        fail('Unknown action.');
}
