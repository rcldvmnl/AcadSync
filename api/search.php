<?php
/**
 * GET /api/search.php?q=reporting
 * Searches the open section:
 *   - subjects and tasks with a depth-first TREE search (Section > Subject > Task)
 *   - classmates with a LINEAR SEARCH by name
 */

declare(strict_types=1);
require_once __DIR__ . '/../backend/bootstrap.php';

$user = current_member();
$ctx = load_context($user);
$q = (string) param('q', '');
if (strlen($q) < 2) {
    respond(['subjects' => [], 'tasks' => [], 'classmates' => [], 'q' => $q]);
}

// [DSA: TREE] — tree-based searching over the section hierarchy
$timeline = build_timeline($ctx['tasks'], time());
$tree = build_tree($ctx['section'], $ctx['subjects'], $timeline);
$subjects = [];
$tasks = [];
foreach ($tree->search($q) as $hit) {
    $node = $hit['node'];
    if ($node['type'] === 'subject') {
        $subjects[] = ['id' => (int) $node['id'], 'code' => $node['code'], 'title' => $node['title'], 'color' => $node['color_tag'], 'path' => $hit['path']];
    } else {
        $full = $ctx['taskIndex'][(int) $node['id']] ?? null;
        if ($full) {
            $tasks[] = present_task($full, $ctx['subjectIndex'], $ctx['users']) + ['path' => $hit['path']];
        }
    }
}

// [ALGORITHM: LINEAR SEARCH] — classmates by name
$people = [];
foreach (Algorithms::linearSearchByName(Algorithms::selectionSortByLastName($ctx['members']), $q) as $m) {
    $people[] = public_user($m);
}

respond(['q' => $q, 'subjects' => $subjects, 'tasks' => $tasks, 'classmates' => $people]);
