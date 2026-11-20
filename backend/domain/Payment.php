<?php
/**
 * [OOP] Dues and payments, kept by the Treasurer.
 *
 *   Due                     one fee the organization collects (e.g. "Membership fee", ₱150)
 *   Payment (abstract)      money a member handed in for a due
 *    ├─ CashPayment         paid in person; an official receipt number is optional
 *    └─ GcashPayment        paid by GCash; needs the 13-digit reference number
 *
 *   ABSTRACTION   Payment is abstract: callers ask for the method, its label and a receipt line,
 *                 never how each method checks its reference.
 *   INHERITANCE   CashPayment and GcashPayment share the amount, payer, due and date from Payment.
 *   POLYMORPHISM  cleanReference(), receiptLine() and referenceIsUnique() answer differently per method; dues.php
 *                 builds the right object with Payment::create() and never tests the method string.
 *   ENCAPSULATION amounts are whole centavos in private fields, checked once in the constructor
 *                 (more than zero; a valid reference for the method); there are no setters.
 *
 * Amounts are stored as centavos (₱150.00 = 15000) so adding them up never has rounding errors.
 */

declare(strict_types=1);

abstract class Payment
{
    public const CASH = 'cash';
    public const GCASH = 'gcash';

    private ?string $reference;

    public function __construct(
        private int $id,
        private int $dueId,
        private int $userId,
        private int $amount,
        ?string $reference,
        private int $receivedBy,
        private string $paidAt,
    ) {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Enter an amount more than ₱0.');
        }
        $this->reference = $this->cleanReference($reference);   // each method checks its own reference
    }

    /** Factory: the method decides which class is built. Throws InvalidArgumentException for bad input. */
    public static function create(string $method, int $dueId, int $userId, int $amount, ?string $reference, int $receivedBy, string $paidAt = '', int $id = 0): Payment
    {
        $args = [$id, $dueId, $userId, $amount, $reference, $receivedBy, $paidAt ?: date('Y-m-d H:i:s')];
        return match ($method) {
            self::CASH => new CashPayment(...$args),
            self::GCASH => new GcashPayment(...$args),
            default => throw new InvalidArgumentException('Pick Cash or GCash.'),
        };
    }

    public static function fromRow(array $row): Payment
    {
        return self::create((string) $row['method'], (int) $row['due_id'], (int) $row['user_id'], (int) $row['amount'],
            $row['reference'], (int) $row['received_by'], (string) $row['paid_at'], (int) $row['id']);
    }

    /** Every method, for the "Record a payment" form. */
    public static function methods(): array
    {
        return [
            ['key' => self::CASH, 'label' => 'Cash', 'reference' => 'Receipt no. (optional)', 'needs_reference' => false],
            ['key' => self::GCASH, 'label' => 'GCash', 'reference' => 'GCash reference no. (13 digits)', 'needs_reference' => true],
        ];
    }

    /** cash | gcash — the value stored in payments.method. */
    abstract public function method(): string;

    abstract public function methodLabel(): string;

    /** Checks and tidies the reference for this method (null = none). Throws when it isn't valid. */
    abstract protected function cleanReference(?string $reference): ?string;

    /** One line for the payment history, e.g. "GCash · ref 1009 8765 4321 0". */
    abstract public function receiptLine(): string;

    /** Must no other payment of this method use the same reference? (a GCash ref proves one transfer) */
    abstract public function referenceIsUnique(): bool;

    public function id(): int
    {
        return $this->id;
    }

    public function dueId(): int
    {
        return $this->dueId;
    }

    public function userId(): int
    {
        return $this->userId;
    }

    /** In centavos. */
    public function amount(): int
    {
        return $this->amount;
    }

    public function reference(): ?string
    {
        return $this->reference;
    }

    public function receivedBy(): int
    {
        return $this->receivedBy;
    }

    public function paidAt(): string
    {
        return $this->paidAt;
    }

    /** The row to INSERT into payments. */
    public function toRow(int $sectionId): array
    {
        return [
            'section_id' => $sectionId, 'due_id' => $this->dueId, 'user_id' => $this->userId, 'amount' => $this->amount,
            'method' => $this->method(), 'reference' => $this->reference, 'received_by' => $this->receivedBy, 'paid_at' => $this->paidAt,
        ];
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id, 'due_id' => $this->dueId, 'user_id' => $this->userId, 'amount' => $this->amount,
            'amount_label' => Due::peso($this->amount), 'method' => $this->method(), 'method_label' => $this->methodLabel(),
            'reference' => $this->reference, 'receipt' => $this->receiptLine(), 'received_by' => $this->receivedBy, 'paid_at' => $this->paidAt,
        ];
    }
}

/** Paid in person. The official receipt number is optional. */
final class CashPayment extends Payment
{
    public function method(): string
    {
        return self::CASH;
    }

    public function methodLabel(): string
    {
        return 'Cash';
    }

    protected function cleanReference(?string $reference): ?string
    {
        $reference = trim((string) $reference);
        if ($reference === '') {
            return null;
        }
        if (strlen($reference) > 20) {
            throw new InvalidArgumentException('A receipt number has at most 20 characters.');
        }
        return $reference;
    }

    public function receiptLine(): string
    {
        return $this->reference() === null ? 'Cash' : 'Cash · receipt ' . $this->reference();
    }

    /** Receipt numbers are the Treasurer's own; repeating one is their call. */
    public function referenceIsUnique(): bool
    {
        return false;
    }
}

/** Paid by GCash. The 13-digit reference number proves it and can only be used once. */
final class GcashPayment extends Payment
{
    public function method(): string
    {
        return self::GCASH;
    }

    public function methodLabel(): string
    {
        return 'GCash';
    }

    /** Keeps only the digits ("1009 8765 4321 0" -> "1009876543210"); there must be exactly 13. */
    protected function cleanReference(?string $reference): ?string
    {
        $digits = '';
        $text = (string) $reference;
        $len = strlen($text);
        for ($i = 0; $i < $len; $i++) {
            if ($text[$i] >= '0' && $text[$i] <= '9') {
                $digits .= $text[$i];
            } elseif ($text[$i] !== ' ' && $text[$i] !== '-') {
                throw new InvalidArgumentException('A GCash reference number has only digits.');
            }
        }
        if (strlen($digits) !== 13) {
            throw new InvalidArgumentException('Enter the 13-digit GCash reference number.');
        }
        return $digits;
    }

    public function receiptLine(): string
    {
        $r = (string) $this->reference();
        return 'GCash · ref ' . substr($r, 0, 4) . ' ' . substr($r, 4, 4) . ' ' . substr($r, 8, 4) . ' ' . substr($r, 12);
    }

    public function referenceIsUnique(): bool
    {
        return true;
    }
}

/** One fee the organization collects from its members. */
final class Due
{
    public function __construct(
        private int $id,
        private string $title,
        private int $amount,
        private ?string $dueDate,
        private string $status,
        private int $createdBy,
    ) {
        if (trim($title) === '' || strlen($title) > 60) {
            throw new InvalidArgumentException('Give the due a name (up to 60 characters).');
        }
        if ($amount <= 0 || $amount > 10_000_00) {
            throw new InvalidArgumentException('The amount must be between ₱0.01 and ₱10,000.');
        }
        if ($dueDate !== null && !is_real_date($dueDate)) {
            throw new InvalidArgumentException('Pick a valid date.');
        }
    }

    public static function fromRow(array $row): Due
    {
        return new Due((int) $row['id'], (string) $row['title'], (int) $row['amount'], $row['due_date'] ?: null,
            (string) $row['status'], (int) $row['created_by']);
    }

    /** "150", "150.5" or "1,500.00" pesos -> centavos, or null when it isn't an amount. */
    public static function centavos(string $pesos): ?int
    {
        $pesos = str_replace([',', '₱', ' '], '', trim($pesos));
        if (!preg_match('/^(\d{1,6})(?:\.(\d{1,2}))?$/', $pesos, $m)) {
            return null;
        }
        return (int) $m[1] * 100 + (int) str_pad($m[2] ?? '0', 2, '0');
    }

    /** 15000 -> "₱150.00". */
    public static function peso(int $centavos): string
    {
        return '₱' . number_format($centavos / 100, 2);
    }

    public function id(): int
    {
        return $this->id;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function amount(): int
    {
        return $this->amount;
    }

    public function dueDate(): ?string
    {
        return $this->dueDate;
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /** What is still owed after $paid centavos (never below zero). */
    public function balance(int $paid): int
    {
        return $this->amount - $paid > 0 ? $this->amount - $paid : 0;
    }

    /** paid | partial | overdue | unpaid, for one member who has paid $paid so far. */
    public function standing(int $paid, int $now): string
    {
        if ($paid >= $this->amount) {
            return 'paid';
        }
        if ($this->dueDate !== null && strtotime($this->dueDate . ' 23:59:59') < $now) {
            return 'overdue';
        }
        return $paid > 0 ? 'partial' : 'unpaid';
    }

    public function toRow(int $sectionId): array
    {
        return ['section_id' => $sectionId, 'title' => $this->title, 'amount' => $this->amount, 'due_date' => $this->dueDate,
            'status' => $this->status, 'created_by' => $this->createdBy];
    }

    public function toArray(): array
    {
        return ['id' => $this->id, 'title' => $this->title, 'amount' => $this->amount, 'amount_label' => self::peso($this->amount),
            'due_date' => $this->dueDate, 'status' => $this->status];
    }
}
