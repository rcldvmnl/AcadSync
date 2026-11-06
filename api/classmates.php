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
// [DSA: GRAPH] — classmates are vertices, study-group links are edges (tagged with the group's id)
$groupRows = load_groups($sectionId);
$graph = build_graph($sectionId, $members, $groupRows);

/*
 * GET ?path_to=ID -> how you're connected to a classmate through groups.
 * [DSA: GRAPH + BFS] — shortest chain of classmates from you to them.
 */
if (param('path_to')) {
    $me = (int) $user['id'];
    $path = $graph->shortestPath($me, (int) param('path_to'));
    $users = user_index();
    $named = [];
    foreach ($path ?? [] as $id) {
        $named[] = ['id' => $id, 'name' => isset($users[$id]) ? $users[$id]['first_name'] . ' ' . $users[$id]['last_name'] : '?'];
    }
    respond(['path' => $path === null ? null : $named, 'algorithm' => 'Graph breadth-first search (shortest path)']);
}

/*
 * GET ?mates=1 -> "My groupmates": everyone who shares a group with me.
 * [DSA: GRAPH + BFS] — start at my own vertex and walk breadth-first along the edges of each
 * group I'm in (matched by group id, so two groups both called "Group 3" never mix).
 */
if (param('mates')) {
    $me = (int) $user['id'];
    $mates = [];          // id => true
    $walks = [];          // per group: name + who the BFS reached, so the result can be explained
    foreach ($groupRows as $g) {
        if (!in_array($me, $g['members'], true)) {
            continue;
        }
        $reached = $graph->teamMembers($me, $g['name'], (int) $g['id']);
        $found = [];
        foreach ($reached as $id) {
            if ($id !== $me) {
                $mates[$id] = true;
                $found[] = $id;
            }
        }
        $walks[] = ['group_id' => (int) $g['id'], 'group' => $g['name'], 'reached' => $found];
    }
    $ids = [];
    foreach (Algorithms::selectionSortByLastName($members) as $m) {   // A–Z, like the directory
        if (isset($mates[(int) $m['id']])) {
            $ids[] = (int) $m['id'];
        }
    }
    respond([
        'mates' => $ids,
        'walks' => $walks,
        'algorithm' => 'Graph breadth-first search from you through ' . count($walks) . ' group' . (count($walks) === 1 ? '' : 's'),
    ]);
}

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
