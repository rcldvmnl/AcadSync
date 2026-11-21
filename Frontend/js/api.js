// Thin fetch wrapper around the PHP endpoints in /api.
const API_BASE = '../api/';

class ApiError extends Error {
  constructor(message, status, data) {
    super(message);
    this.status = status;
    this.data = data;
  }
}

const API = {
  async request(path, { method = 'GET', params, body, redirect = true } = {}) {
    const url = new URL(API_BASE + path, location.href);
    if (params) {
      Object.entries(params).forEach(([k, v]) => v !== undefined && v !== null && v !== '' && url.searchParams.set(k, v));
    }
    let res;
    try {
      res = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: body ? { 'Content-Type': 'application/json' } : {},
        body: body ? JSON.stringify(body) : undefined,
      });
    } catch {
      throw new ApiError('Can’t reach the server. Is `php -S localhost:8000` running?', 0, {});
    }
    let data = {};
    try {
      data = await res.json();
    } catch {
      throw new ApiError('The server sent an unexpected response.', res.status, {});
    }
    if (!res.ok || data.ok === false) {
      if (redirect && res.status === 401) {
        location.href = 'index.html';
      } else if (redirect && res.status === 409 && data.needs_section) {
        location.href = 'section.html';
      }
      throw new ApiError(data.error || 'Something went wrong.', res.status, data);
    }
    // Every saved change is announced, so the motion layer (Motion in ui.js) can acknowledge it on any page
    if (method !== 'GET') {
      document.dispatchEvent(new CustomEvent('acadsync:action', { detail: { path, body: body || {}, data } }));
    }
    return data;
  },
  get(path, params, opts) {
    return this.request(path, { params, ...opts });
  },
  post(path, body, opts) {
    return this.request(path, { method: 'POST', body, ...opts });
  },
};
