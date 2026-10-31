<?php
/**
 * GET  /api/section.php?code=B2826K   -> preview a section before joining
 * POST /api/section.php action=join   {code}
 * POST /api/section.php action=create {name, program, semester}  (creator becomes admin)
 */

declare(strict_types=1);
require_once __DIR__ . '/../backend/bootstrap.php';

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

function new_class_code(): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
    } while (find_section_by_code($code) !== null);
    return $code;
}

$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $section = find_section_by_code((string) param('code', ''));
    if (!$section) {
        fail('No section uses that code.', 404);
    }
    respond(['section' => present_section($section, count(section_members((int) $section['id'])))]);
}

require_method('POST');

switch (param('action')) {
    case 'join': {
        $section = find_section_by_code((string) param('code', ''));
        if (!$section) {
            fail('No section uses that code.', 404);
        }
        Store::update('users', (int) $user['id'], [
            'section_id' => (int) $section['id'],
            'role' => 'student',
            'joined_at' => date('Y-m-d H:i:s'),
        ]);
        notify_admins((int) $section['id'], 'members', "{$user['first_name']} {$user['last_name']} joined {$section['name']}", 'members.html');
        respond(['section' => present_section($section, count(section_members((int) $section['id'])))]);
    }

    case 'create': {
        $name = strtoupper((string) param('name', ''));
        if ($name === '') {
            fail('Give your section a name, e.g. BSINFO-2A.');
        }
        $id = Store::insert('sections', [
            'name' => $name,
            'class_code' => new_class_code(),
            'program' => (string) param('program', 'BS Information Technology'),
            'semester' => (string) param('semester', '2nd Semester · 2026'),
            'created_by' => (int) $user['id'],
        ]);
        // FR-2.2 — the creator automatically becomes the section admin
        Store::update('users', (int) $user['id'], ['section_id' => $id, 'role' => 'admin', 'joined_at' => date('Y-m-d H:i:s')]);
        $section = Store::find('sections', $id);
        respond(['section' => present_section($section, 1)], 201);
    }

    default:
        fail('Unknown action.');
}
