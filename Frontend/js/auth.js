// Login, sign-up (with live HashSet e-mail check) and forgot-password flow.

const afterLogin = (me) => { location.href = me.section ? 'home.html' : 'section.html'; };

/* ---------------------------------------------------------------- login */
const loginForm = $('#login');
if (loginForm) {
  // Already logged in? Skip the form.
  API.get('auth.php', null, { redirect: false }).then(afterLogin).catch(() => {});

  UI.bindForm(loginForm, async (data) => afterLogin(await API.post('auth.php', { action: 'login', ...data }, { redirect: false })));
  $('#forgot').onclick = openForgot;
}

function openForgot() {
  const steps = (n) => `<div class="mb-5 flex gap-1.5">${[0, 1, 2].map((i) => `<span class="h-1.5 rounded-full ${i === n ? 'w-6 bg-charcoal' : 'w-1.5 bg-line'}"></span>`).join('')}</div>`;
  const badge = (name, bg = 'bg-lavender-tint') => `<span class="mb-4 grid h-14 w-14 place-items-center rounded-full ${bg}">${icon(name, 24)}</span>`;
  let email = '';

  const { el, close } = UI.sheet('<div data-body></div>', { label: 'Forgot password' });
  const body = $('[data-body]', el);

  function stepEmail() {
    body.innerHTML = `${steps(0)}${badge('key')}
      <form>
        <h2 class="text-2xl font-bold">Forgot password?</h2>
        <p class="mt-2 text-sm text-muted">Enter your @lspu.edu.ph email and we’ll send a 6-digit code to reset it.</p>
        <div class="mt-4">${UI.field({ name: 'email', label: 'University email', type: 'email', iconName: 'mail', placeholder: '0000-0000@lspu.edu.ph', value: email, required: true })}</div>
        <p data-error class="mt-3 rounded-2xl bg-danger-tint px-4 py-3 text-sm" hidden></p>
        <button type="submit" class="btn btn-dark mt-5 w-full">Send reset code</button>
        <button type="button" data-close class="mt-3 w-full py-2 text-sm font-semibold">Back to login</button>
      </form>`;
    UI.bindForm($('form', body), async (d) => {
      const res = await API.post('auth.php', { action: 'forgot', email: d.email }, { redirect: false });
      email = d.email;
      stepCode(res.dev_code);
    });
  }

  function stepCode(devCode) {
    body.innerHTML = `${steps(1)}${badge('mail')}
      <form>
        <h2 class="text-2xl font-bold">Check your inbox</h2>
        <p class="mt-2 text-sm text-muted">We sent a 6-digit code to <b class="text-charcoal">${esc(email)}</b>.</p>
        ${devCode ? `<p class="mt-3 rounded-2xl bg-sun-tint px-4 py-3 text-xs">Local demo (no e-mail server): your code is <b class="text-sm tracking-widest">${esc(devCode)}</b></p>` : ''}
        <div class="mt-4 space-y-4">
          ${UI.field({ name: 'code', label: '6-digit code', iconName: 'hash', placeholder: '123456', required: true })}
          ${UI.field({ name: 'password', label: 'New password', type: 'password', iconName: 'lock', placeholder: 'At least 8 characters', required: true, autocomplete: 'new-password' })}
        </div>
        <p data-error class="mt-3 rounded-2xl bg-danger-tint px-4 py-3 text-sm" hidden></p>
        <button type="submit" class="btn btn-dark mt-5 w-full">Reset password</button>
        <button type="button" data-back class="mt-3 w-full py-2 text-sm font-semibold">Use a different email</button>
      </form>`;
    $('[data-back]', body).onclick = stepEmail;
    UI.bindForm($('form', body), async (d) => {
      await API.post('auth.php', { action: 'reset', email, ...d }, { redirect: false });
      stepDone();
    });
  }

  function stepDone() {
    body.innerHTML = `${steps(2)}${badge('check', 'bg-mint-tint')}
      <h2 class="text-2xl font-bold">Password updated</h2>
      <p class="mt-2 text-sm text-muted">You can now log in with your new password.</p>
      <button type="button" data-close class="btn btn-dark mt-6 w-full">Back to login</button>`;
    $('input[name=login]').value = email;
  }

  stepEmail();
  return close;
}

/* ---------------------------------------------------------------- sign up */
const signupForm = $('#signup');
if (signupForm) {
  const emailInput = $('input[name=email]', signupForm);
  const field = $('#email-field');
  const msg = $('#email-msg');
  let timer;

  const HINT = 'Your Student ID is the number before “@”.';

  // Typing only the student number? Complete the address on blur.
  emailInput.addEventListener('blur', () => {
    if (/^\d{4}-\d{4}$/.test(emailInput.value.trim())) {
      emailInput.value = `${emailInput.value.trim()}@lspu.edu.ph`;
      emailInput.dispatchEvent(new Event('input'));
    }
  });

  // Live duplicate check — backed by the HashSet of e-mails on the server.
  emailInput.addEventListener('input', () => {
    clearTimeout(timer);
    field.classList.remove('ok', 'bad');
    $('#email-ok').hidden = true;
    msg.textContent = HINT;
    msg.className = 'mt-1.5 block text-xs text-muted';
    const value = emailInput.value.trim();
    if (!value.includes('@')) return;
    timer = setTimeout(async () => {
      try {
        const res = await API.post('auth.php', { action: 'check_email', email: value }, { redirect: false });
        if (emailInput.value.trim() !== value) return;
        field.classList.add(res.available ? 'ok' : 'bad');
        $('#email-ok').hidden = !res.available;
        msg.textContent = res.reason;
        msg.className = `mt-1.5 block text-xs ${res.available ? 'text-success' : 'text-danger'}`;
      } catch { /* ignore while typing */ }
    }, 350);
  });

  UI.bindForm(signupForm, async (data) => {
    await API.post('auth.php', { action: 'register', ...data }, { redirect: false });
    location.href = 'section.html';
  });
}
