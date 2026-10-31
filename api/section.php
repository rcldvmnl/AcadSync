<?php
/**
 * GET  /api/section.php?code=B2826K   -> preview a section before joining
 * POST /api/section.php
 *   action=join    {code}                    join as a student and open it
 *   action=create  {name, program, year_level, semester, school_year, preset}
 *                                            creator becomes the section admin
 *   action=switch  {section_id}              open another section you belong to
 *   action=leave   {section_id}              leave a section (students only)
 */

declare(strict_types=1);
require_once __DIR__ . '/../backend/bootstrap.php';
require_once __DIR__ . '/../backend/seed.php';

function find_section_by_code(string $code): ?array
{
    $code = strtoupper(trim($code));
    foreach (Store::all('sections') as $s) {
        if ($s['class_code'] === $code) {
            return $s;
        }
    }
    return null;
}

/** [DSA: HASHSET] — keeps generating until the code is not in the set of existing codes. */
function new_class_code(): string
{
    $codes = hashset_of('sections', 'class_code');
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
    } while ($codes->contains($code));
    return $code;
}

function open_section(int $userId, int $sectionId): void
{
    Store::update('users', $userId, ['active_section_id' => $sectionId]);
}

$user = current_user();
$uid = (int) $user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $section = find_section_by_code((string) param('code', ''));
    if (!$section) {
        fail('No section uses that code.', 404);
    }
    $already = membership($uid, (int) $section['id']);
    respond([
        'section' => present_section($section, count(section_members((int) $section['id']))),
        'already_member' => $already !== null,
        'role' => $already['role'] ?? null,
    ]);
}

require_method('POST');

switch (param('action')) {
    case 'join': {
        $section = find_section_by_code((string) param('code', ''));
        if (!$section) {
            fail('No section uses that code.', 404);
        }
        $sid = (int) $section['id'];
        if (membership($uid, $sid) === null) {
            Store::insert('memberships', ['section_id' => $sid, 'user_id' => $uid, 'role' => 'student']);
            notify_admins($sid, 'members', "{$user['first_name']} {$user['last_name']} joined {$section['name']}", 'classmates.html');
        }
        open_section($uid, $sid);
        respond(['section' => present_section($section, count(section_members($sid)))]);
    }

    case 'create': {
        $name = strtoupper(preg_replace('/\s+/', ' ', (string) param('name', '')));
        if (!preg_match('/^[A-Z0-9][A-Z0-9 \-]{1,29}$/', $name)) {
            fail('Enter a section name like BSINFO-2A (letters, numbers, spaces or dashes).');
        }
        // [DSA: HASHSET] — every section name must be unique
        if (hashset_of('sections', 'name')->contains($name)) {
            fail("$name already exists. Ask its officer for the class code, or use a different name.", 409);
        }

        Store::begin();
        $sid = Store::insert('sections', [
            'name' => $name,
            'class_code' => new_class_code(),
            'program' => (string) param('program', 'BS Information Technology'),
            'year_level' => (string) param('year_level', ''),
            'semester' => (string) param('semester', ''),
            'school_year' => (string) param('school_year', ''),
            'created_by' => $uid,
        ]);
        // FR-2.2 — the creator automatically becomes the section admin
        Store::insert('memberships', ['section_id' => $sid, 'user_id' => $uid, 'role' => 'admin']);
        // FR-2.3 — optionally preload the 8 BSINFO 2A core subjects (times are added later)
        if (param('preset')) {
            foreach (core_curriculum() as [$code, $title, $color]) {
                Store::insert('subjects', ['section_id' => $sid, 'code' => $code, 'title' => $title, 'color_tag' => $color]);
            }
        }
        open_section($uid, $sid);
        Store::commit();
        respond(['section' => present_section(Store::find('sections', $sid), 1)], 201);
    }

    case 'switch': {
        $sid = (int) param('section_id', 0);
        if (membership($uid, $sid) === null) {
            fail('You are not a member of that section.', 403);
        }
        open_section($uid, $sid);
        respond();
    }

    case 'leave': {
        $sid = (int) param('section_id', 0);
        $m = membership($uid, $sid);
        if ($m === null) {
            fail('You are not a member of that section.', 404);
        }
        if ($m['role'] === 'admin') {
            fail('Class admins can’t leave their own section.', 409);
        }
        Store::delete('memberships', (int) $m['id']);
        if ((int) $user['active_section_id'] === $sid) {
            $others = user_sections($uid);
            Store::update('users', $uid, ['active_section_id' => $others ? $others[0]['id'] : null]);
        }
        respond();
    }

    default:
        fail('Unknown action.');
}
