<?php
/**
 * GET  /api/classmates.php[?q=...&mode=name|id&group=Capstone A]
 *   - directory sorted A–Z by last name (Selection Sort)
 *   - mode=id  -> Binary Search by numeric Student ID
 *   - mode=name-> Linear Search by name
 *   - group    -> members reachable inside that study group (Graph BFS)
 * POST action=create_group {name, member_ids[]}  (admin) -> adds Graph edges
 */

declare(strict_types=1);
require_once __DIR__ . '/../backend/bootstrap.php';

$user = current_member();
$sectionId = (int) $user['section_id'];
$members = section_members($sectionId);
// [DSA: GRAPH] — classmates are vertices, study-group links are edges
$graph = build_graph($sectionId, $members);

function present_member(array $m, DependencyGraph $graph): array
{
    return public_user($m) + ['groups' => $graph->groupsOf((int) $m['id'])];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_admin();
    if (param('action') !== 'create_group') {
        fail('Unknown action.');
    }
    $name = (string) param('name', '');
    if ($name === '') {
        fail('Name the group.');
    }
    $inSection = [];
    foreach ($members as $m) {
        $inSection[(int) $m['id']] = $m;
    }
    $ids = [];
    foreach ((array) param('member_ids', []) as $id) {
        if (isset($inSection[(int) $id])) {
            $ids[] = (int) $id;
        }
    }
    if (count($ids) < 2) {
        fail('A study group needs at least 2 members.');
    }
    $existing = [];
    foreach (section_rows('study_groups', $sectionId) as $e) {
        $existing[$e['student_a_id'] . '-' . $e['student_b_id'] . '-' . $e['project_context']] = true;
    }
    Store::begin();
    $n = count($ids);
    for ($i = 0; $i < $n; $i++) {
        for ($j = $i + 1; $j < $n; $j++) {
            $key = $ids[$i] . '-' . $ids[$j] . '-' . $name;
            $rev = $ids[$j] . '-' . $ids[$i] . '-' . $name;
            if (!isset($existing[$key]) && !isset($existing[$rev])) {
                Store::insert('study_groups', ['section_id' => $sectionId, 'student_a_id' => $ids[$i], 'student_b_id' => $ids[$j], 'project_context' => $name]);
            }
        }
    }
    Store::commit();
    foreach ($ids as $id) {
        notify($id, $sectionId, 'group', "You were added to study group “{$name}”", 'classmates.html');
    }
    respond(['group' => $name, 'members' => $n], 201);
}

$q = (string) param('q', '');
$mode = (string) param('mode', 'name');
$group = (string) param('group', '');
$result = null;
$algorithm = 'Selection Sort (A–Z by last name)';

if ($q !== '' && $mode === 'id') {
    // [ALGORITHM: SELECTION SORT + BINARY SEARCH] — sort by ID, then halve the range
    $byId = Algorithms::selectionSortByStudentId($members);
    $found = Algorithms::binarySearchByStudentId($byId, $q);
    $list = $found ? [$found] : [];
    $algorithm = 'Binary Search by Student ID';
} else {
    // [ALGORITHM: SELECTION SORT] — A–Z by last name
    $list = Algorithms::selectionSortByLastName($members);
    if ($q !== '') {
        $list = Algorithms::linearSearchByName($list, $q);
        $algorithm = 'Linear Search by name';
    }
}

if ($group !== '') {
    // [DSA: GRAPH] — breadth-first walk of the group's edges from any member to collect the team
    $team = [];
    foreach ($members as $m) {
        if ($team) {
            break;
        }
        foreach ($graph->groupsOf((int) $m['id']) as $g) {
            if ($g === $group) {
                foreach ($graph->teamMembers((int) $m['id'], $group) as $id) {
                    $team[$id] = true;
                }
                break;
            }
        }
    }
    $filtered = [];
    foreach ($list as $m) {
        if (isset($team[(int) $m['id']])) {
            $filtered[] = $m;
        }
    }
    $list = $filtered;
}

$out = [];
foreach ($list as $m) {
    $out[] = present_member($m, $graph);
}

$section = Store::find('sections', $sectionId);
respond([
    'members' => $out,
    'total' => count($members),
    'groups' => $graph->allGroups(),
    'algorithm' => $algorithm,
    'section' => present_section($section, count($members)),
    'role' => $user['role'],
    'me' => (int) $user['id'],
]);
