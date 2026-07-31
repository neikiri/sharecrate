import './app.css';
import Alpine from 'alpinejs';

/* ---------------------------------------------------------------------
 * Small helpers
 * ------------------------------------------------------------------- */

const i18n = (() => {
  const el = document.getElementById('js-i18n');
  let data = {};
  if (el) {
    try {
      data = JSON.parse(el.textContent || '{}');
    } catch {
      data = {};
    }
  }
  return (key, fallback = '') => data[key] ?? fallback ?? key;
})();

function formatBytes(bytes) {
  if (bytes === null || bytes === undefined || Number.isNaN(bytes)) return '';
  const units = ['B', 'kB', 'MB', 'GB', 'TB'];
  let value = Number(bytes);
  let unit = 0;
  while (value >= 1024 && unit < units.length - 1) {
    value /= 1024;
    unit += 1;
  }
  const decimals = value < 10 && unit > 0 ? 1 : 0;
  return `${value.toFixed(decimals)} ${units[unit]}`;
}

function slugify(value) {
  return String(value)
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9._-]+/g, '-')
    .replace(/-{2,}/g, '-')
    .replace(/^[-.]+|[-.]+$/g, '')
    .slice(0, 120);
}

async function copyText(text) {
  try {
    if (navigator.clipboard && window.isSecureContext) {
      await navigator.clipboard.writeText(text);
      return true;
    }
  } catch {
    /* falls through to the legacy path */
  }
  try {
    const area = document.createElement('textarea');
    area.value = text;
    area.setAttribute('readonly', '');
    area.style.position = 'fixed';
    area.style.opacity = '0';
    document.body.appendChild(area);
    area.select();
    const ok = document.execCommand('copy');
    document.body.removeChild(area);
    return ok;
  } catch {
    return false;
  }
}

/* ---------------------------------------------------------------------
 * Toasts
 * ------------------------------------------------------------------- */

Alpine.store('toasts', {
  items: [],
  seq: 0,
  push(message, type = 'success', timeout = 3200) {
    const id = ++this.seq;
    this.items.push({ id, message, type });
    setTimeout(() => this.dismiss(id), timeout);
  },
  dismiss(id) {
    this.items = this.items.filter((item) => item.id !== id);
  },
});

window.toast = (message, type = 'success') => Alpine.store('toasts').push(message, type);

/* ---------------------------------------------------------------------
 * Copy to clipboard
 * ------------------------------------------------------------------- */

Alpine.data('copyable', (value = '') => ({
  copied: false,
  value,
  async copy(text) {
    const payload = text ?? this.value ?? this.$refs.source?.value ?? this.$refs.source?.textContent ?? '';
    const ok = await copyText(String(payload).trim());
    if (!ok) return;
    this.copied = true;
    window.toast(i18n('copied', 'Copied'), 'success');
    setTimeout(() => {
      this.copied = false;
    }, 1800);
  },
}));

/* ---------------------------------------------------------------------
 * Reveal / hide a password input
 * ------------------------------------------------------------------- */

Alpine.data('revealable', () => ({
  shown: false,
  toggle() {
    this.shown = !this.shown;
    const input = this.$refs.input;
    if (input) input.type = this.shown ? 'text' : 'password';
  },
}));

/* ---------------------------------------------------------------------
 * Alias field: live slug preview
 * ------------------------------------------------------------------- */

Alpine.data('aliasField', (initial = '', base = '') => ({
  alias: initial,
  base,
  normalise() {
    this.alias = slugify(this.alias);
  },
  get preview() {
    return `${this.base}/${this.alias || '…'}`;
  },
}));

/* ---------------------------------------------------------------------
 * Bulk row selection for admin tables
 * ------------------------------------------------------------------- */

Alpine.data('bulkSelect', () => ({
  selected: [],
  get count() {
    return this.selected.length;
  },
  isSelected(id) {
    return this.selected.includes(String(id));
  },
  toggleAll(event) {
    const boxes = Array.from(this.$root.querySelectorAll('input[data-row-checkbox]'));
    if (event.target.checked) {
      this.selected = boxes.map((box) => String(box.value));
      boxes.forEach((box) => {
        box.checked = true;
      });
    } else {
      this.selected = [];
      boxes.forEach((box) => {
        box.checked = false;
      });
    }
  },
  clear() {
    this.selected = [];
    this.$root.querySelectorAll('input[data-row-checkbox]').forEach((box) => {
      box.checked = false;
    });
  },
}));

/* ---------------------------------------------------------------------
 * Confirm dialog for destructive forms / links
 * ------------------------------------------------------------------- */

Alpine.data('confirmable', () => ({
  open: false,
  message: '',
  detail: '',
  submitLabel: '',
  target: null,
  init() {
    this.$root.addEventListener('confirm:request', (event) => {
      this.message = event.detail.message;
      this.detail = event.detail.detail || '';
      this.submitLabel = event.detail.submitLabel || i18n('confirm', 'Confirm');
      this.target = event.detail.target;
      this.open = true;
    });
  },
  accept() {
    this.open = false;
    const target = this.target;
    this.target = null;
    if (!target) return;
    target.dataset.confirmed = '1';
    if (target.tagName === 'FORM') {
      target.requestSubmit ? target.requestSubmit() : target.submit();
    } else {
      target.click();
    }
  },
  cancel() {
    this.open = false;
    this.target = null;
  },
}));

function wireConfirmations() {
  const dialog = document.getElementById('confirm-dialog');

  const request = (target, event) => {
    if (target.dataset.confirmed === '1') return true;
    event.preventDefault();
    const detail = {
      message: target.dataset.confirm || i18n('are_you_sure', 'Are you sure?'),
      detail: target.dataset.confirmDetail || '',
      submitLabel: target.dataset.confirmLabel || '',
      target,
    };
    if (dialog) {
      dialog.dispatchEvent(new CustomEvent('confirm:request', { detail }));
    } else if (window.confirm(detail.message)) {
      target.dataset.confirmed = '1';
      target.tagName === 'FORM' ? target.submit() : target.click();
    }
    return false;
  };

  document.addEventListener('submit', (event) => {
    const form = event.target;
    if (form instanceof HTMLFormElement && form.dataset.confirm) request(form, event);
  });

  document.addEventListener('click', (event) => {
    const link = event.target.closest('a[data-confirm], button[data-confirm]');
    if (!link || link.closest('form')?.dataset.confirm) return;
    request(link, event);
  });
}

/* ---------------------------------------------------------------------
 * Uploader: drag & drop with per file progress
 * ------------------------------------------------------------------- */

Alpine.data('uploader', (config = {}) => ({
  endpoint: config.endpoint || '',
  csrf: config.csrf || '',
  maxBytes: Number(config.maxBytes || 0),
  baseUrl: config.baseUrl || '',
  dragging: false,
  busy: false,
  queue: [],
  done: [],
  options: {
    alias: '',
    password: '',
    expires_at: '',
    max_downloads: '',
    description: '',
  },

  pick() {
    this.$refs.input?.click();
  },

  onDrop(event) {
    this.dragging = false;
    this.add(event.dataTransfer?.files);
  },

  onChange(event) {
    this.add(event.target.files);
    event.target.value = '';
  },

  add(fileList) {
    if (!fileList) return;
    for (const file of Array.from(fileList)) {
      if (this.maxBytes > 0 && file.size > this.maxBytes) {
        window.toast(`${file.name}: ${i18n('file_too_large', 'File is too large')}`, 'error');
        continue;
      }
      this.queue.push({
        id: `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
        file,
        name: file.name,
        size: file.size,
        sizeLabel: formatBytes(file.size),
        progress: 0,
        status: 'pending',
        error: '',
      });
    }
  },

  remove(id) {
    this.queue = this.queue.filter((item) => item.id !== id || item.status === 'uploading');
  },

  reset() {
    this.queue = [];
    this.done = [];
    this.options.alias = '';
    this.options.password = '';
    this.options.expires_at = '';
    this.options.max_downloads = '';
    this.options.description = '';
  },

  get pending() {
    return this.queue.filter((item) => item.status === 'pending');
  },

  get totalProgress() {
    if (!this.queue.length) return 0;
    const total = this.queue.reduce((sum, item) => sum + item.size, 0);
    if (!total) return 0;
    const uploaded = this.queue.reduce((sum, item) => sum + (item.size * item.progress) / 100, 0);
    return Math.round((uploaded / total) * 100);
  },

  async start() {
    if (this.busy || !this.pending.length) return;
    this.busy = true;
    for (const item of this.queue) {
      if (item.status !== 'pending') continue;
      try {
        const result = await this.upload(item);
        item.status = 'done';
        item.progress = 100;
        this.done.unshift(result);
      } catch (error) {
        item.status = 'error';
        item.error = error?.message || i18n('upload_failed', 'Upload failed');
      }
    }
    this.busy = false;
    const failed = this.queue.filter((item) => item.status === 'error').length;
    if (!failed) {
      window.toast(i18n('upload_done', 'Upload finished'), 'success');
      this.queue = this.queue.filter((item) => item.status !== 'done');
    }
  },

  upload(item) {
    return new Promise((resolve, reject) => {
      const body = new FormData();
      body.append('file', item.file);
      body.append('_token', this.csrf);
      body.append('description', this.options.description);
      body.append('password', this.options.password);
      body.append('expires_at', this.options.expires_at);
      body.append('max_downloads', this.options.max_downloads);
      if (this.queue.length === 1) body.append('alias', this.options.alias);

      const xhr = new XMLHttpRequest();
      xhr.open('POST', this.endpoint, true);
      xhr.setRequestHeader('Accept', 'application/json');
      xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

      xhr.upload.addEventListener('progress', (event) => {
        if (event.lengthComputable) {
          item.progress = Math.round((event.loaded / event.total) * 100);
        }
      });

      xhr.addEventListener('load', () => {
        let payload = {};
        try {
          payload = JSON.parse(xhr.responseText || '{}');
        } catch {
          payload = {};
        }
        if (xhr.status >= 200 && xhr.status < 300 && payload.ok) {
          resolve(payload.file);
        } else {
          reject(new Error(payload.message || `HTTP ${xhr.status}`));
        }
      });

      xhr.addEventListener('error', () => reject(new Error(i18n('network_error', 'Network error'))));
      xhr.addEventListener('abort', () => reject(new Error(i18n('upload_cancelled', 'Upload cancelled'))));

      item.status = 'uploading';
      xhr.send(body);
    });
  },

  async copyLink(url) {
    if (await copyText(url)) window.toast(i18n('copied', 'Copied'), 'success');
  },
}));

/* ---------------------------------------------------------------------
 * Import screen: select FTP dropped files
 * ------------------------------------------------------------------- */

Alpine.data('importList', () => ({
  selected: [],
  toggleAll(event) {
    const boxes = Array.from(this.$root.querySelectorAll('input[data-import-checkbox]'));
    this.selected = event.target.checked ? boxes.map((box) => box.value) : [];
    boxes.forEach((box) => {
      box.checked = event.target.checked;
    });
  },
  get count() {
    return this.selected.length;
  },
}));

/* ---------------------------------------------------------------------
 * Boot
 * ------------------------------------------------------------------- */

window.Alpine = Alpine;
window.dlFormatBytes = formatBytes;
window.dlSlugify = slugify;

document.addEventListener('DOMContentLoaded', () => {
  wireConfirmations();

  // Auto dismiss server rendered flash messages
  document.querySelectorAll('[data-autohide]').forEach((el) => {
    const delay = Number(el.dataset.autohide) || 6000;
    setTimeout(() => {
      el.style.transition = 'opacity .4s ease, transform .4s ease';
      el.style.opacity = '0';
      el.style.transform = 'translateY(-6px)';
      setTimeout(() => el.remove(), 420);
    }, delay);
  });
});

Alpine.start();
