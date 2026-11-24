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
 *   action=confirm_claim   {claim_id}                                    "I already paid" → a real payment
 *   action=reject_claim    {claim_id, reason?}                           not received; the member is told why
 * POST (any member charged the due)
 *   action=claim_payment   {due_id, amount, method, reference?, note?}  "I already paid": the Treasurer checks it
 *   action=withdraw_claim  {claim_id}                                    your own waiting claim
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

/**
 * "I already paid" claims. $pending[dueId][userId] = the member's waiting claim (one at a time);
 * $lastClaim[dueId][userId] = their newest claim (shows "not received" until they send another).
 */
$claims = [];
$pending = [];
$lastClaim = [];
foreach (section_rows('payment_claims', $sectionId) as $row) {
    $c = PaymentClaim::fromRow($row);
    $p = $c->payment();
    $claims[$c->id()] = $c;
    if ($c->isPending()) {
        $pending[$p->dueId()][$p->userId()] = $c;
    }
    $before = $lastClaim[$p->dueId()][$p->userId()] ?? null;
    if ($before === null || $c->id() > $before->id()) {
        $lastClaim[$p->dueId()][$p->userId()] = $c;
    }
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

    // ?export=<due id>: the Treasurer's report as a CSV file (one row per charged member, A–Z, then the total)
    if (param('export')) {
        if (!$canManage) {
            fail('Only the Treasurer or the President can export dues.', 403);
        }
        $due = $dues[(int) param('export', 0)] ?? null;
        if ($due === null) {
            fail('That due doesn’t exist.', 404);
        }
        $pesos = fn (int $centavos) => number_format($centavos / 100, 2, '.', '');
        $csv = "\xEF\xBB\xBF"                               // UTF-8 BOM: Excel then shows ₱ and ñ correctly
            . csv_line([$due->title()])
            . csv_line(['Amount (PHP)', $pesos($due->amount()), 'Due', $due->dueDate() ?? 'No date', 'Exported', date('Y-m-d H:i', $now)])
            . csv_line([])
            . csv_line(['Student ID', 'Name', 'Status', 'Paid (PHP)', 'Balance (PHP)', 'Standing', 'Payments']);
        $collected = 0;
        $owed = 0;
        foreach ($sortedMembers as $m) {
            $mid = (int) $m['id'];
            if (!$isCharged($m, $due->id())) {
                continue;
            }
            $got = $paid[$due->id()][$mid] ?? 0;
            $collected += $got;
            $owed += $due->balance($got);
            $lines = [];
            foreach ($payments as $p) {
                if ($p->dueId() === $due->id() && $p->userId() === $mid) {
                    // [OOP: POLYMORPHISM] each kind of payment writes its own receipt line
                    $lines[] = substr($p->paidAt(), 0, 10) . ' ' . $pesos($p->amount()) . ' ' . $p->receiptLine()
                        . ' (received by ' . ($names[$p->receivedBy()] ?? 'Former officer') . ')';
                }
            }
            $csv .= csv_line([$m['student_id'], $names[$mid] ?? '', MemberStatus::fromString($m['status'] ?? 'active')->label(),
                $pesos($got), $pesos($due->balance($got)), $due->standing($got, $now), implode('; ', $lines)]);
        }
        $csv .= csv_line(['Total', '', '', $pesos($collected), $pesos($owed), '', '']);
        $file = trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $due->title()), '-') ?: 'due';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $file . '-' . date('Y-m-d', $now) . '.csv"');
        echo $csv;
        exit;
    }

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
        // "I already paid": your waiting claim, or the last one the Treasurer couldn't confirm
        $open = $charged && $due->balance($mine) > 0;
        $last = $lastClaim[$id][$uid] ?? null;
        $item['my_claim'] = $open && isset($pending[$id][$uid]) ? $pending[$id][$uid]->toArray() : null;
        $item['my_rejected'] = $open && $item['my_claim'] === null && $last !== null && $last->isRejected() ? $last->toArray() : null;
        $myBalance += $item['my_balance'];

        if ($canManage) {
            // [DSA: HASHSET] — members who have paid this due in full
            $fullyPaid = new HashSet();
            $rows = [];
            $collected = 0;
            $toCheck = 0;                                   // "I already paid" claims waiting on this due
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
                    // what the member says they paid, waiting for a check (only while something is left)
                    'claim' => $due->balance($got) > 0 && isset($pending[$id][$mid]) ? $pending[$id][$mid]->toArray() : null,
                ];
                if ($rows[count($rows) - 1]['claim'] !== null) {
                    $toCheck++;
                }
            }
            $item += [
                'members' => $rows,
                'expected' => count($rows),
                'paid_count' => $fullyPaid->size(),
                'collected' => $collected,
                'collected_label' => Due::peso($collected),
                'target_label' => Due::peso($due->amount() * count($rows)),
                'claims_count' => $toCheck,
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
$fullName = $me->fullName();

/** The open due named by due_id, or a 404. */
$openDue = function () use ($dues): Due {
    $due = $dues[(int) param('due_id', 0)] ?? null;
    if ($due === null || !$due->isOpen()) {
        fail('That due was cancelled or doesn’t exist.', 404);
    }
    return $due;
};

/**
 * [DSA: HASHSET] the references of this method already used by a payment, or by another member's waiting
 * claim (a GCash reference proves one transfer, so it counts once). $payments may be re-read rows.
 */
$usedRefs = function (string $method, array $paymentList, int $skipClaimId = 0) use (&$claims): HashSet {
    $refs = new HashSet();
    foreach ($paymentList as $p) {
        if ($p->referenceIsUnique() && $p->method() === $method) {
            $refs->add((string) $p->reference());
        }
    }
    foreach ($claims as $c) {
        if ($c->isPending() && $c->id() !== $skipClaimId && $c->payment()->referenceIsUnique() && $c->payment()->method() === $method) {
            $refs->add((string) $c->payment()->reference());
        }
    }
    return $refs;
};

/** The waiting claim named by claim_id (in this section), or a 404. */
$pendingClaim = function () use (&$claims): PaymentClaim {
    $c = $claims[(int) param('claim_id', 0)] ?? null;
    if ($c === null || !$c->isPending()) {
        fail('That claim was already handled.', 404);
    }
    return $c;
};

/* ---- "I already paid": any member who still owes a due (no MANAGE_DUES needed) ---- */
$action = (string) param('action');
try {
    if ($action === 'claim_payment') {
        $due = $openDue();
        $mine = $paid[$due->id()][$uid] ?? 0;
        if (!$me->status()->owesDues() && $mine === 0) {   // [POLYMORPHISM] the status decides who is charged
            fail('You aren’t charged this due.', 403);
        }
        $balance = $due->balance($mine);
        if ($balance === 0) {
            fail("You’ve already paid {$due->title()} in full.", 409);
        }
        if (isset($pending[$due->id()][$uid])) {
            fail('You already told the Treasurer about a payment for this. Wait for them to check it, or withdraw it first.', 409);
        }
        $amount = Due::centavos((string) param('amount', ''));
        if ($amount === null) {
            fail('Enter the amount in pesos, e.g. 150 or 150.50.');
        }
        if ($amount > $balance) {
            fail('That’s more than what’s left: ' . Due::peso($balance) . '.');
        }
        // [OOP: ENCAPSULATION] the claim builds a CashPayment / GcashPayment, which checks the reference
        $claim = new PaymentClaim(0, $due->id(), $uid, $amount, (string) param('method', ''), (string) param('reference', ''), (string) param('note', ''));
        $p = $claim->payment();
        if ($p->referenceIsUnique() && $usedRefs($p->method(), $payments)->contains((string) $p->reference())) {
            fail('That GCash reference number was already used for another payment.', 409);
        }
        $id = Store::insert('payment_claims', $claim->toRow($sectionId));
        foreach ($ctx['members'] as $m) {                  // the Treasurer and the President check it
            if ((int) $m['id'] !== $uid && Person::fromRow($m)->can(Permission::MANAGE_DUES)) {
                notify((int) $m['id'], $sectionId, 'dues', "$fullName says they paid " . Due::peso($amount) . " for {$due->title()} ({$p->receiptLine()}). Check it and confirm.", 'dues.html');
            }
        }
        respond(['id' => $id], 201);
    }
    if ($action === 'withdraw_claim') {
        $c = $pendingClaim();
        if ($c->payment()->userId() !== $uid) {
            fail('You can only withdraw your own claim.', 403);
        }
        Store::delete('payment_claims', $c->id());
        respond();
    }
} catch (InvalidArgumentException $e) {
    fail($e->getMessage());   // the claim's Payment refused the method, amount or reference
}

if (!$canManage) {
    fail('Only the Treasurer or the President handles dues.', 403);
}

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

        case 'confirm_claim': {
            // The money is there: the claim becomes a real payment, received by this officer
            $c = $pendingClaim();
            $due = $dues[$c->payment()->dueId()] ?? null;
            if ($due === null || !$due->isOpen()) {
                fail('That due was cancelled.', 404);
            }
            $payment = $c->confirm($uid);                   // [OOP: COMPOSITION] built from the claim's own Payment
            Store::begin();
            Store::update('payment_claims', $c->id(), ['status' => PaymentClaim::APPROVED, 'reviewed_by' => $uid]);   // takes the write lock first
            $fresh = [];                                    // re-read under the lock: nobody records the same money twice
            $got = 0;
            foreach (section_rows('payments', $sectionId) as $row) {
                $p = Payment::fromRow($row);
                $fresh[] = $p;
                if ($p->dueId() === $due->id() && $p->userId() === $payment->userId()) {
                    $got += $p->amount();
                }
            }
            $balance = $due->balance($got);
            if ($payment->amount() > $balance) {
                Store::rollbackIfOpen();
                fail($balance === 0 ? 'They’ve already paid this in full. Mark it not received instead.'
                    : 'That’s more than what’s left: ' . Due::peso($balance) . '. Record the right amount instead.', 409);
            }
            if ($payment->referenceIsUnique() && $usedRefs($payment->method(), $fresh, $c->id())->contains((string) $payment->reference())) {
                Store::rollbackIfOpen();
                fail('That GCash reference number was already used for another payment.', 409);
            }
            $id = Store::insert('payments', $payment->toRow($sectionId));
            Store::commit();
            $left = $balance - $payment->amount();
            notify($payment->userId(), $sectionId, 'dues', "$fullName confirmed your " . Due::peso($payment->amount()) . " payment for {$due->title()}"
                . ($left > 0 ? ' (' . Due::peso($left) . ' left)' : ' (paid in full)'), 'dues.html');
            respond(['id' => $id, 'left' => $left, 'left_label' => Due::peso($left)], 201);
        }

        case 'reject_claim': {
            // Not received: the member is told why and can check and send it again
            $c = $pendingClaim();
            $reason = trim((string) preg_replace('/\s+/', ' ', (string) param('reason', '')));
            if (mb_strlen($reason) > 200) {
                fail('Keep the reason under 200 characters.');
            }
            Store::update('payment_claims', $c->id(), ['status' => PaymentClaim::REJECTED, 'reason' => $reason ?: null, 'reviewed_by' => $uid]);
            $title = isset($dues[$c->payment()->dueId()]) ? $dues[$c->payment()->dueId()]->title() : 'a due';
            notify($c->payment()->userId(), $sectionId, 'dues', "$fullName couldn’t confirm your " . Due::peso($c->payment()->amount()) . " payment for $title"
                . ($reason !== '' ? ": $reason" : '') . '. Check it and tell them again.', 'dues.html');
            respond();
        }

        default:
            fail('Unknown action.');
    }
} catch (InvalidArgumentException $e) {
    fail($e->getMessage());   // a Due or Payment refused the input
}
