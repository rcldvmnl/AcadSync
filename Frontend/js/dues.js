// Dues: fees the organization collects.
//   Members see what they owe and their payments.
//   The Treasurer (and the President) add dues, record cash / GCash payments and see who has paid.
// The server builds a CashPayment or GcashPayment for each payment; GCash needs its 13-digit reference.

const STANDING = {
  paid: ['Paid', 'bg-mint-tint text-success'],
  partial: ['Partly paid', 'bg-sun-tint'],
  unpaid: ['Unpaid', 'bg-canvas'],
  overdue: ['Overdue', 'bg-danger-tint'],
  not_charged: ['Not charged', 'bg-canvas text-muted'],
};
let data = null;
const open = new Set();   // dues whose member list is expanded

(async () => {
  await Shell.init({ active: 'account', eyebrow: sectionEyebrow, title: 'Dues' });
  await load();
})().catch((e) => { if (e.message !== 'no section') $('#main').innerHTML = UI.errorBox(e); });

async function load() {
  data = await API.get('dues.php');
  if (data.can_manage && !open.size && data.dues[0]) open.add(data.dues[0].id);
  // a due with "I already paid" claims to check opens by itself (first load only, like the first due)
  if (data.can_manage && !load.done) data.dues.forEach((d) => { if (d.claims_count) open.add(d.id); });
  load.done = true;
  render();
}

const standingChip = (s) => UI.chip(...STANDING[s]);
const dueDate = (d) => (d.due_date ? `Due ${Fmt.short(d.due_date)}` : 'No deadline');
const bar = (part, whole) => `<div class="act-bar"><span style="width:${whole ? Math.min(100, Math.round((part / whole) * 100)) : 0}%"></span></div>`;

function render() {
  $('#main').innerHTML = `
    <div class="grid gap-5 lg:grid-cols-[1fr_22rem] lg:items-start lg:gap-6">
      <div class="space-y-4">
        ${data.can_manage ? manageHead() : `<div class="lg:hidden">${summaryCard()}</div>`}
        ${data.dues.length ? data.dues.map((d) => (data.can_manage ? manageCard(d) : memberCard(d))).join('')
          : UI.empty('No dues right now', data.can_manage ? 'Add a due and every active member is notified.' : 'When the Treasurer adds a fee, it shows up here.', 'clipboard')}
      </div>
      <aside class="space-y-4"><div class="${data.can_manage ? '' : 'hidden lg:block'}">${summaryCard()}</div>${historyCard()}</aside>
    </div>`;
  bind();
}

/* ------------------------------------------------------------ everyone */
function summaryCard() {
  const owe = data.my_balance > 0;
  return `
    <section class="card-pastel ${owe ? 'peach' : 'mint-soft'} rounded-[1.75rem] p-5" aria-label="Your dues">
      <span class="sparkle" aria-hidden="true"></span><span class="ring-deco"></span>
      <div class="text-[11px] font-semibold uppercase tracking-wider">Your dues</div>
      <div class="mt-1 text-4xl font-extrabold tracking-tight">${owe ? esc(data.my_balance_label) : 'All paid'}</div>
      <p class="mt-1 text-sm">${!data.my_charged ? 'Dues are charged to active members only.' : owe ? 'Pay the Treasurer in cash or by GCash. They record it here.' : 'Nothing left to pay. Thank you!'}</p>
    </section>`;
}

function historyCard() {
  const list = data.my_payments;
  return `
    <section class="rounded-[1.75rem] bg-white p-5 shadow-card" aria-labelledby="hist-h">
      <h2 id="hist-h" class="font-semibold">Your payments</h2>
      ${list.length ? `<ul class="mt-3 space-y-2">${list.map((p) => `
        <li class="flex items-center gap-3 rounded-2xl bg-canvas p-3">
          <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-white">${icon('check', 16, 'text-success')}</span>
          <span class="min-w-0 flex-1"><span class="block truncate text-sm font-semibold">${esc(p.due)}</span>
            <span class="block truncate text-xs text-muted">${esc(p.receipt)} · ${esc(Fmt.monthDay(p.paid_at.slice(0, 10)))}</span></span>
          <span class="text-sm font-semibold">${esc(p.amount_label)}</span>
        </li>`).join('')}</ul>` : '<p class="mt-2 text-sm text-muted">No payments yet.</p>'}
    </section>`;
}

function memberCard(d) {
  return `
    <article class="rounded-[1.75rem] bg-white p-5 shadow-card">
      <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
          <h2 class="text-lg font-semibold">${esc(d.title)}</h2>
          <p class="text-xs text-muted">${esc(d.amount_label)} · ${esc(dueDate(d))}</p>
        </div>
        ${standingChip(d.my_standing)}
      </div>
      ${d.my_standing === 'not_charged' ? '' : `
        <div class="mt-4">${bar(d.my_paid, d.amount)}</div>
        <p class="mt-1.5 text-xs text-muted">Paid <b class="font-semibold text-charcoal">${esc(d.my_paid_label)}</b> of ${esc(d.amount_label)}${d.my_balance ? ` · ${esc(d.my_balance_label)} left` : ''}</p>
        ${d.my_balance ? claimBox(d) : ''}`}
    </article>`;
}

/** "I already paid": waiting for the Treasurer (with Withdraw), or why the last one wasn't confirmed, or the button. */
function claimBox(d) {
  const c = d.my_claim;
  if (c) {
    return `
      <div class="mt-4 flex items-start gap-3 rounded-2xl bg-sun-tint p-3 text-xs">
        <span class="min-w-0 flex-1">You said you paid <b class="font-semibold">${esc(c.amount_label)}</b> · ${esc(c.receipt)}${c.note ? ` · “${esc(c.note)}”` : ''}.
          Waiting for the Treasurer to check it.</span>
        <button type="button" data-withdraw-claim="${c.id}" class="shrink-0 font-semibold text-danger">Withdraw</button>
      </div>`;
  }
  const r = d.my_rejected;
  return `
    ${r ? `<p class="mt-4 rounded-2xl bg-danger-tint p-3 text-xs">The Treasurer couldn’t confirm your ${esc(r.amount_label)} payment (${esc(r.receipt)})${r.reason ? `: ${esc(r.reason)}` : ''}. Check it and tell them again.</p>` : ''}
    <div class="mt-4"><button type="button" data-claim="${d.id}" class="btn btn-canvas h-10 px-4 text-sm">${icon('check', 16)}I already paid</button></div>`;
}

/* ------------------------------------------------------------ Treasurer */
function manageHead() {
  const collected = data.dues.reduce((n, d) => n + d.collected, 0);
  return `
    <section class="card-pastel dark flex flex-wrap items-center justify-between gap-4 rounded-[1.75rem] p-5">
      <span class="sparkle" aria-hidden="true"></span><span class="ring-deco"></span>
      <div class="relative">
        <div class="text-[11px] font-semibold uppercase tracking-wider text-white/60">Collected so far</div>
        <div class="mt-1 text-4xl font-extrabold">₱${(collected / 100).toLocaleString('en-PH', { minimumFractionDigits: 2 })}</div>
        <div class="text-xs text-white/70">${plural(data.dues.length, 'open due')} · cash and GCash</div>
      </div>
      <button type="button" data-new-due class="btn btn-yellow relative">${icon('plus', 18)}New due</button>
    </section>`;
}

function manageCard(d) {
  const expanded = open.has(d.id);
  const row = (m) => `
    <li class="rounded-2xl bg-canvas p-3">
      <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
        ${UI.avatar(m.name, 'bg-white', 'h-9 w-9 text-[11px]')}
        <!-- The name keeps at least 9rem; on a narrow phone the standing and the button wrap to their own line -->
        <span class="min-w-[9rem] flex-1"><span class="block text-sm font-semibold leading-snug">${esc(m.name)}</span>
          <span class="block text-xs text-muted">${esc(m.student_id)} · paid ${esc(m.paid_label)}${m.balance ? ` · ${esc(m.balance_label)} left` : ''}${m.status !== 'Active' ? ` · ${esc(m.status)}` : ''}</span></span>
        <span class="ml-auto flex shrink-0 items-center gap-2">
          ${standingChip(m.standing)}
          ${m.balance ? `<button type="button" data-pay="${d.id}:${m.id}" class="rounded-full bg-charcoal px-4 py-2 text-xs font-semibold text-white">Record payment</button>` : ''}
        </span>
      </div>
      ${m.claim ? `
        <div class="mt-2 flex flex-wrap items-center gap-2 rounded-2xl bg-sun-tint p-3 text-xs">
          <span class="min-w-[9rem] flex-1">Says they paid <b class="font-semibold">${esc(m.claim.amount_label)}</b> · ${esc(m.claim.receipt)} · ${esc(Fmt.monthDay(m.claim.created_at.slice(0, 10)))}${m.claim.note ? ` · “${esc(m.claim.note)}”` : ''}</span>
          <span class="ml-auto flex shrink-0 items-center gap-3">
            <button type="button" data-reject-claim="${m.claim.id}" class="font-semibold text-danger">Not received</button>
            <button type="button" data-confirm-claim="${d.id}:${m.id}" class="rounded-full bg-charcoal px-4 py-2 text-xs font-semibold text-white">Confirm</button>
          </span>
        </div>` : ''}
      ${m.payments.length ? `<ul class="mt-2 space-y-1 border-t border-black/5 pt-2">${m.payments.map((p) => `
        <li class="flex items-start gap-2 text-xs">
          <span class="min-w-0 flex-1 text-muted">${esc(p.amount_label)} · ${esc(p.receipt)} · ${esc(Fmt.monthDay(p.paid_at.slice(0, 10)))} · by ${esc(p.received_by_name)}</span>
          <button type="button" data-remove-payment="${p.id}" class="shrink-0 font-semibold text-danger">Remove</button>
        </li>`).join('')}</ul>` : ''}
    </li>`;
  return `
    <article class="rounded-[1.75rem] bg-white p-5 shadow-card">
      <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
          <h2 class="text-lg font-semibold">${esc(d.title)}</h2>
          <p class="text-xs text-muted">${esc(d.amount_label)} each · ${esc(dueDate(d))}</p>
        </div>
        <span class="flex shrink-0 flex-wrap justify-end gap-2">
          ${d.claims_count ? `<span class="chip bg-sun-tint">${d.claims_count} to check</span>` : ''}
          <span class="chip ${d.paid_count === d.expected && d.expected ? 'bg-mint-tint text-success' : 'bg-canvas'}">${d.paid_count} of ${d.expected} paid</span>
        </span>
      </div>
      <div class="mt-4">${bar(d.collected, d.amount * d.expected)}</div>
      <p class="mt-1.5 text-xs text-muted"><b class="font-semibold text-charcoal">${esc(d.collected_label)}</b> of ${esc(d.target_label)} collected</p>
      <div class="mt-4 flex flex-wrap items-center gap-2">
        <button type="button" data-toggle="${d.id}" aria-expanded="${expanded}" class="btn btn-canvas h-10 px-4 text-sm">${expanded ? 'Hide members' : `Show ${plural(d.expected, 'member')}`}</button>
        <a href="${API_BASE}dues.php?export=${d.id}" download data-export-due="${d.id}" class="btn btn-canvas h-10 px-4 text-sm">${icon('download', 16)}Export CSV</a>
        <button type="button" data-cancel-due="${d.id}" class="ml-auto text-xs font-semibold text-danger">Cancel due</button>
      </div>
      ${expanded ? `<ul class="mt-3 space-y-2">${d.members.map(row).join('') || '<li class="text-sm text-muted">No active members to charge.</li>'}</ul>` : ''}
    </article>`;
}

function bind() {
  $('[data-new-due]')?.addEventListener('click', newDue);
  $$('[data-toggle]').forEach((b) => b.onclick = () => {
    const id = Number(b.dataset.toggle);
    if (open.has(id)) open.delete(id); else open.add(id);
    render();
  });
  $$('[data-pay]').forEach((b) => b.onclick = () => {
    const [dueId, userId] = b.dataset.pay.split(':').map(Number);
    const d = data.dues.find((x) => x.id === dueId);
    recordPayment(d, d.members.find((m) => m.id === userId));
  });
  // "I already paid" (members) — the same form as Record a payment, sent to the Treasurer to check
  $$('[data-claim]').forEach((b) => b.onclick = () => {
    const d = data.dues.find((x) => x.id === Number(b.dataset.claim));
    recordPayment(d, { name: 'You', balance: d.my_balance, balance_label: d.my_balance_label }, true);
  });
  $$('[data-withdraw-claim]').forEach((b) => b.onclick = () => confirmSheet('Withdraw this claim?',
    'The Treasurer won’t check it any more. You can tell them again later.', 'Withdraw',
    () => post({ action: 'withdraw_claim', claim_id: Number(b.dataset.withdrawClaim) }, 'Claim withdrawn')));
  // Treasurer: the money is there → a real payment; or not received (with an optional reason)
  $$('[data-confirm-claim]').forEach((b) => b.onclick = () => {
    const [dueId, userId] = b.dataset.confirmClaim.split(':').map(Number);
    const m = data.dues.find((x) => x.id === dueId).members.find((x) => x.id === userId);
    confirmSheet(`Confirm ${m.name}’s payment?`, `${m.claim.amount_label} · ${m.claim.receipt}. Make sure you received it. It is recorded as a payment you received.`,
      'Confirm', () => post({ action: 'confirm_claim', claim_id: m.claim.id }, 'Payment confirmed'));
  });
  $$('[data-reject-claim]').forEach((b) => b.onclick = () => rejectClaim(Number(b.dataset.rejectClaim)));
  $$('[data-remove-payment]').forEach((b) => b.onclick = () => confirmSheet('Remove this payment?',
    'Use this only for a payment entered by mistake. The member is notified.', 'Remove payment',
    () => post({ action: 'remove_payment', payment_id: Number(b.dataset.removePayment) }, 'Payment removed')));
  $$('[data-cancel-due]').forEach((b) => b.onclick = () => confirmSheet('Cancel this due?',
    'It disappears for everyone. Payments already recorded stay in each member’s history.', 'Cancel due',
    () => post({ action: 'cancel_due', due_id: Number(b.dataset.cancelDue) }, 'Due cancelled')));
}

async function post(body, msg) {
  try {
    await API.post('dues.php', body);
    UI.toast(msg);
    await load();
  } catch (e) { UI.toast(e.message, { iconName: 'alert' }); }
}

function confirmSheet(title, text, ok, run) {
  UI.sheet(`<h2 class="text-2xl font-bold">${esc(title)}</h2>
    <p class="mt-2 text-sm text-[#5C5C66]">${esc(text)}</p>
    <div class="mt-6 grid grid-cols-2 gap-3"><button data-close class="btn btn-canvas">Keep it</button><button data-ok class="btn btn-dark">${esc(ok)}</button></div>`, {
    label: title,
    onMount: (el, close) => { $('[data-ok]', el).onclick = () => { close(); run(); }; },
  });
}

/** Not received: an optional reason the member sees ("No GCash transfer with that reference"). */
function rejectClaim(claimId) {
  UI.sheet(`
    <form>
      <h2 class="text-2xl font-bold">Not received?</h2>
      <p class="mt-1 text-sm text-muted">The member is told, so they can check and tell you again.</p>
      <div class="mt-4">${UI.field({ name: 'reason', label: 'Reason (optional)', placeholder: 'e.g. No GCash transfer with that reference' })}</div>
      <p data-error class="mt-3 rounded-2xl bg-danger-tint px-4 py-3 text-sm" hidden></p>
      <div class="mt-5 grid grid-cols-2 gap-3"><button type="button" data-close class="btn btn-canvas">Keep it</button><button type="submit" class="btn btn-dark">Not received</button></div>
    </form>`, {
    label: 'Not received',
    onMount(el, close) {
      $('input[name=reason]', el).maxLength = 200;
      UI.bindForm($('form', el), async (f) => {
        await API.post('dues.php', { action: 'reject_claim', claim_id: claimId, reason: f.reason });
        close();
        UI.toast('Marked not received', { sub: 'The member was notified' });
        await load();
      });
    },
  });
}

function newDue() {
  UI.sheet(`
    <form>
      <h2 class="text-2xl font-bold">New due</h2>
      <p class="mt-1 text-sm text-muted">Every active member is charged and gets a notification.</p>
      <div class="mt-4 space-y-4">
        ${UI.field({ name: 'title', label: 'What is it for', placeholder: 'e.g. Membership fee', required: true })}
        ${UI.field({ name: 'amount', label: 'Amount per member (₱)', placeholder: '150', required: true })}
        ${UI.field({ name: 'due_date', label: 'Due date (optional)', type: 'date' })}
      </div>
      <p data-error class="mt-3 rounded-2xl bg-danger-tint px-4 py-3 text-sm" hidden></p>
      <button type="submit" class="btn btn-dark mt-5 w-full">Add due</button>
    </form>`, {
    label: 'New due',
    onMount(el, close) {
      $('input[name=amount]', el).inputMode = 'decimal';
      $('input[name=title]', el).maxLength = 60;
      UI.bindForm($('form', el), async (f) => {
        await API.post('dues.php', { action: 'create_due', ...f });
        close();
        UI.toast('Due added', { sub: 'Active members were notified' });
        await load();
      });
    },
  });
}

/**
 * Cash or GCash. GCash asks for its 13-digit reference; the server checks it and that it's unused.
 * claim = true: a member's "I already paid" (same form + a note); the Treasurer checks it before it counts.
 */
function recordPayment(d, m, claim = false) {
  const methods = data.methods;
  const title = claim ? 'I already paid' : 'Record a payment';
  UI.sheet(`
    <form>
      <h2 class="text-2xl font-bold">${title}</h2>
      <p class="mt-1 text-sm text-muted">${claim ? `${esc(d.title)} · ${esc(m.balance_label)} left. The Treasurer checks it, then it counts.` : `${esc(m.name)} · ${esc(d.title)} · ${esc(m.balance_label)} left`}</p>
      <div class="mt-4 space-y-4">
        <fieldset>
          <legend class="mb-1.5 text-xs text-muted">Paid with</legend>
          <div class="grid grid-cols-2 gap-2">${methods.map((x, i) => `
            <label data-method class="flex cursor-pointer items-center justify-center gap-2 rounded-2xl p-3 text-sm font-semibold focus-within:ring-2 focus-within:ring-lavender">
              <input type="radio" name="method" value="${x.key}" ${i === 0 ? 'checked' : ''} class="sr-only">${esc(x.label)}</label>`).join('')}
          </div>
        </fieldset>
        ${UI.field({ name: 'amount', label: 'Amount (₱)', value: (m.balance / 100).toFixed(2), required: true })}
        <div data-ref>${UI.field({ name: 'reference', label: methods[0].reference })}</div>
        ${claim ? UI.field({ name: 'note', label: 'Note (optional)', placeholder: 'e.g. Paid during the meeting on Friday' }) : ''}
      </div>
      <p data-error class="mt-3 rounded-2xl bg-danger-tint px-4 py-3 text-sm" hidden></p>
      <button type="submit" class="btn btn-dark mt-5 w-full">${claim ? 'Send to the Treasurer' : 'Save payment'}</button>
    </form>`, {
    label: title,
    onMount(el, close) {
      const form = $('form', el);
      $('input[name=amount]', el).inputMode = 'decimal';
      const ref = $('input[name=reference]', el);
      const sync = () => {
        const x = methods.find((y) => y.key === form.elements.method.value);
        $$('[data-method]', el).forEach((l) => {
          const on = $('input', l).checked;
          l.classList.toggle('bg-charcoal', on);
          l.classList.toggle('text-white', on);
          l.classList.toggle('bg-canvas', !on);
        });
        $('[data-ref] span', el).textContent = x.reference;
        ref.required = x.needs_reference;
        ref.inputMode = x.needs_reference ? 'numeric' : 'text';
        ref.placeholder = x.needs_reference ? '1009 8765 4321 0' : '';
      };
      $$('input[name=method]', el).forEach((r) => r.onchange = sync);
      sync();
      $('input[name=amount]', el).focus();   // not the hidden radio, whose focus ring looks like a second choice
      if (claim) $('input[name=note]', el).maxLength = 200;
      UI.bindForm(form, async (f) => {
        if (claim) {
          await API.post('dues.php', { action: 'claim_payment', due_id: d.id, ...f });
          close();
          UI.toast('Sent to the Treasurer', { sub: 'You’re notified when they check it' });
          await load();
          return;
        }
        const res = await API.post('dues.php', { action: 'record_payment', due_id: d.id, user_id: m.id, ...f });
        close();
        UI.toast('Payment recorded', { sub: res.left ? `${res.left_label} left` : 'Paid in full' });
        open.add(d.id);
        await load();
      });
    },
  });
}
