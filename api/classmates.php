<?php
/**
 * GET  /api/classmates.php[?q=...&mode=name|id&group=Capstone A]
 *   - directory sorted A–Z by last name (Selection Sort)
 *   - mode=id  -> Binary Search by numeric Student ID
 *   - mode=name-> Linear Search by name
 *   - group    -> members reachable inside that study group (Graph BFS)
 * Creating / joining groups lives in api/groups.php.
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
