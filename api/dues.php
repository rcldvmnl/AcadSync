<?php
/**
 * Dues and payments (kept by the Treasurer and the President).
 *
 * GET  /api/dues.php      -> open dues with what you paid; for the Treasurer, every member's standing
 * POST (Permission::MANAGE_DUES — Treasurer, President)
 *   action=create_due      {title, amount, due_date?}                    amount in pesos, e.g. "150"
 *   action=cancel_due      {due_id}
 *   action=record_payment  {due_id, user_id, amount, method: cash|gcash, reference?}
 *   action=remove_payment  {payment_id}                                  a payment entered by mistake
 *
 * [OOP] Payment::create() builds a CashPayment or GcashPayment; each checks its own reference and
 *       writes its own receipt line. MemberStatus::owesDues() decides who is charged (active members).
 * [DSA] HASHSET: a GCash reference number is accepted once; who has paid a due in full.
 *       MERGE SORT: dues by date, payment history newest first. SELECTION SORT: members by last name.
 */

declare(strict_types=1);
require_once __DIR__ . '/../backend/bootstrap.php';

$user = current_member();
$me = Person::fromRow($user);
$ctx = load_context($user);
$sectionId = (int) $ctx['section']['id'];
$uid = $me->id();
$canManage = $me->can(Permission::MANAGE_DUES);
$now = time();

/** The section's dues as Due objects, by id. */
$dues = [];
foreach (section_rows('dues', $sectionId) as $row) {
    $dues[(int) $row['id']] = Due::fromRow($row);
}

/** The section's payments as CashPayment / GcashPayment objects. */
$payments = [];
foreach (section_rows('payments', $sectionId) as $row) {
    $payments[] = Payment::fromRow($row);
}

/** Total paid per due per member: [dueId][userId] => centavos. */
$paid = [];
foreach ($payments as $p) {
    $paid[$p->dueId()][$p->userId()] = ($paid[$p->dueId()][$p->userId()] ?? 0) + $p->amount();
}

$members = [];
foreach ($ctx['members'] as $m) {
    $members[(int) $m['id']] = $m;
}

/** Is this member charged? Active members are ([POLYMORPHISM] the status answers); anyone who paid stays listed. */
$isCharged = fn (array $m, int $dueId) => Person::fromRow($m)->status()->owesDues() || isset($paid[$dueId][(int) $m['id']]);

/* ------------------------------------------------------------------ reads */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $names = [];
    foreach ($ctx['users'] as $u) {
        $names[(int) $u['id']] = trim($u['first_name'] . ' ' . $u['last_name']);
    }
    $sortedMembers = Algorithms::selectionSortByLastName($ctx['members']);   // [ALGORITHM: SELECTION SORT]
    $myCharged = Person::fromRow($user)->status()->owesDues();

    $list = [];
    $myBalance = 0;
    foreach ($dues as $due) {
        if (!$due->isOpen()) {
            continue;
        }
        $id = $due->id();
        $mine = $paid[$id][$uid] ?? 0;
        $charged = $myCharged || $mine > 0;
        $item = $due->toArray() + [
            'sort_ts' => $due->dueDate() !== null ? (int) strtotime($due->dueDate()) : PHP_INT_MAX,
            'my_paid' => $mine,
            'my_paid_label' => Due::peso($mine),
            'my_balance' => $charged ? $due->balance($mine) : 0,
            'my_balance_label' => Due::peso($charged ? $due->balance($mine) : 0),
            'my_standing' => $charged ? $due->standing($mine, $now) : 'not_charged',
        ];
        $myBalance += $item['my_balance'];

        if ($canManage) {
            // [DSA: HASHSET] — members who have paid this due in full
            $fullyPaid = new HashSet();
            $rows = [];
            $collected = 0;
            foreach ($sortedMembers as $m) {
                $mid = (int) $m['id'];
                if (!$isCharged($m, $id)) {
                    continue;
                }
                $got = $paid[$id][$mid] ?? 0;
                $collected += $got;
                if ($got >= $due->amount()) {
                    $fullyPaid->add((string) $mid);
                }
                $history = [];
                foreach ($payments as $p) {
                    if ($p->dueId() === $id && $p->userId() === $mid) {
                        $history[] = $p->toArray() + ['received_by_name' => $names[$p->receivedBy()] ?? 'Former officer'];
                    }
                }
                $rows[] = [
                    'id' => $mid, 'name' => $names[$mid] ?? '', 'student_id' => $m['student_id'],
                    'status' => MemberStatus::fromString($m['status'] ?? 'active')->label(),
                    'paid' => $got, 'paid_label' => Due::peso($got),
                    'balance' => $due->balance($got), 'balance_label' => Due::peso($due->balance($got)),
                    'standing' => $due->standing($got, $now), 'payments' => $history,
                ];
            }
            $item += [
                'members' => $rows,
                'expected' => count($rows),
                'paid_count' => $fullyPaid->size(),
                'collected' => $collected,
                'collected_label' => Due::peso($collected),
                'target_label' => Due::peso($due->amount() * count($rows)),
            ];
        }
        $list[] = $item;
    }
    $steps = 0;
    $list = Algorithms::mergeSortBy($list, 'sort_ts', false, $steps);   // [ALGORITHM: MERGE SORT] — soonest due first

    // Your own payment history, newest first
    $history = [];
    foreach ($payments as $p) {
        if ($p->userId() === $uid && isset($dues[$p->dueId()])) {
            $history[] = $p->toArray() + ['due' => $dues[$p->dueId()]->title(), 'sort_key' => $p->id()];
        }
    }
    $history = Algorithms::mergeSortBy($history, 'sort_key', true);

    respond([
        'dues' => $list,
        'my_balance' => $myBalance,
        'my_balance_label' => Due::peso($myBalance),
        'my_charged' => $myCharged,
        'my_payments' => $history,
        'methods' => Payment::methods(),
        'can_manage' => $canManage,
        'sorted_by' => 'Merge Sort', 'steps' => $steps,
    ]);
}

/* ------------------------------------------------------------------ writes */
require_method('POST');
if (!$canManage) {
    fail('Only the Treasurer or the President handles dues.', 403);
}
$fullName = $me->fullName();

/** The open due named by due_id, or a 404. */
$openDue = function () use ($dues): Due {
    $due = $dues[(int) param('due_id', 0)] ?? null;
    if ($due === null || !$due->isOpen()) {
        fail('That due was cancelled or doesn’t exist.', 404);
    }
    return $due;
};

try {
    switch (param('action')) {
        case 'create_due': {
            $amount = Due::centavos((string) param('amount', ''));
            if ($amount === null) {
                fail('Enter the amount in pesos, e.g. 150 or 150.50.');
            }
            $date = (string) param('due_date', '') ?: null;
            // [OOP: ENCAPSULATION] — the Due constructor rejects a bad title, amount or date
            $due = new Due(0, preg_replace('/\s+/', ' ', trim((string) param('title', ''))), $amount, $date, 'open', $uid);
            $id = Store::insert('dues', $due->toRow($sectionId));
            $when = $date ? ', due ' . date('M j', (int) strtotime($date)) : '';
            foreach ($ctx['members'] as $m) {
                if ((int) $m['id'] !== $uid && Person::fromRow($m)->status()->owesDues()) {
                    notify((int) $m['id'], $sectionId, 'dues', "New due: {$due->title()}, " . Due::peso($amount) . $when, 'dues.html');
                }
            }
            respond(['id' => $id], 201);
        }

        case 'cancel_due': {
            $due = $openDue();
            Store::update('dues', $due->id(), ['status' => 'cancelled']);
            respond();
        }

        case 'record_payment': {
            $due = $openDue();
            $payer = $members[(int) param('user_id', 0)] ?? null;
            if ($payer === null) {
                fail('Pick someone in this section.', 404);
            }
            $amount = Due::centavos((string) param('amount', ''));
            if ($amount === null) {
                fail('Enter the amount in pesos, e.g. 150 or 150.50.');
            }
            $balance = $due->balance($paid[$due->id()][(int) $payer['id']] ?? 0);
            if ($balance === 0) {
                fail("{$payer['first_name']} has already paid {$due->title()} in full.", 409);
            }
            if ($amount > $balance) {
                fail('That’s more than what’s left: ' . Due::peso($balance) . '.');
            }
            // [OOP: POLYMORPHISM] — CashPayment or GcashPayment, each checks its own reference
            $payment = Payment::create((string) param('method', ''), $due->id(), (int) $payer['id'], $amount, (string) param('reference', ''), $uid);
            if ($payment->referenceIsUnique()) {
                // [DSA: HASHSET] — a GCash reference number proves one transfer, so it is accepted once
                $refs = new HashSet();
                foreach ($payments as $p) {
                    if ($p->referenceIsUnique() && $p->method() === $payment->method()) {
                        $refs->add((string) $p->reference());
                    }
                }
                if ($refs->contains((string) $payment->reference())) {
                    fail('That GCash reference number was already used for another payment.', 409);
                }
            }
            $id = Store::insert('payments', $payment->toRow($sectionId));
            $left = $balance - $amount;
            notify((int) $payer['id'], $sectionId, 'dues', "$fullName recorded your " . Due::peso($amount) . " payment for {$due->title()}"
                . ($left > 0 ? ' (' . Due::peso($left) . ' left)' : ' (paid in full)'), 'dues.html');
            respond(['id' => $id, 'receipt' => $payment->receiptLine(), 'left' => $left, 'left_label' => Due::peso($left)], 201);
        }

        case 'remove_payment': {
            $target = null;
            foreach ($payments as $p) {
                if ($p->id() === (int) param('payment_id', 0)) {
                    $target = $p;
                }
            }
            if ($target === null) {
                fail('That payment was already removed.', 404);
            }
            Store::delete('payments', $target->id());
            $title = isset($dues[$target->dueId()]) ? $dues[$target->dueId()]->title() : 'a due';
            notify($target->userId(), $sectionId, 'dues', "$fullName removed a " . Due::peso($target->amount()) . " payment for $title (entered by mistake)", 'dues.html');
            respond();
        }

        default:
            fail('Unknown action.');
    }
} catch (InvalidArgumentException $e) {
    fail($e->getMessage());   // a Due or Payment refused the input
}
