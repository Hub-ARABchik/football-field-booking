(() => {
  'use strict';

  const qs = (selector, root = document) => root.querySelector(selector);
  const qsa = (selector, root = document) => Array.from(root.querySelectorAll(selector));
  const text = (tag, value, className = '') => {
    const node = document.createElement(tag);
    if (className) node.className = className;
    node.textContent = value == null ? '' : String(value);
    return node;
  };

  function debounce(callback, delay = 300) {
    let timer = 0;
    return (...args) => {
      window.clearTimeout(timer);
      timer = window.setTimeout(() => callback(...args), delay);
    };
  }

  function makeUrl(path, params = {}) {
    const url = new URL(path, window.location.href);
    Object.entries(params).forEach(([key, value]) => {
      if (value !== undefined && value !== null && String(value).trim() !== '') {
        url.searchParams.set(key, String(value));
      }
    });
    return url;
  }

  function renderFieldCard(field) {
    const card = document.createElement('a');
    card.className = 'ffbs-field-card';
    card.href = field.url || '#';

    const imageWrap = text('div', '', 'ffbs-field-card__image');
    if (field.photo_url) {
      const image = document.createElement('img');
      image.src = field.photo_url;
      image.alt = `Фотография: ${field.name || 'футбольная площадка'}`;
      image.loading = 'lazy';
      imageWrap.append(image);
    } else {
      imageWrap.append(text('span', '⚽', 'ffbs-field-card__image-mark'));
    }

    const body = text('div', '', 'ffbs-field-card__body');
    body.append(text('h3', field.name, 'ffbs-field-card__title'));
    body.append(text('p', `${field.open_time || '—'}–${field.close_time || '—'}`, 'ffbs-field-card__meta'));

    const tags = text('div', '', 'ffbs-field-card__tags');
    [field.size, field.surface].filter(Boolean).forEach((value) => tags.append(text('span', value, 'ffbs-tag')));
    body.append(tags);

    if (field.description) {
      body.append(text('p', field.description, 'ffbs-field-card__meta'));
    }

    const footer = text('div', '', 'ffbs-field-card__footer');
    footer.append(text('span', `от ${field.price_label || field.price_from || 0} / час`, 'ffbs-price'));
    footer.append(text('span', 'Выбрать время →', 'ffbs-button'));
    body.append(footer);
    card.append(imageWrap, body);
    return card;
  }

  function initFieldSearch(form) {
    const basePath = document.documentElement.dataset.ffbsBase || '';
    const endpoint = form.dataset.apiUrl || `${basePath}/public/api/fields/`;
    const results = qs(form.dataset.results || '[data-ffbs-results]');
    const status = qs(form.dataset.status || '[data-ffbs-search-status]');
    if (!results) return;

    let controller = null;
    async function search() {
      controller?.abort();
      controller = new AbortController();
      const params = Object.fromEntries(new FormData(form).entries());
      const url = makeUrl(endpoint, params);
      if (status) status.textContent = 'Ҷустуҷӯ идома дорад…';
      results.setAttribute('aria-busy', 'true');

      try {
        const response = await fetch(url, {
          method: 'GET',
          headers: { Accept: 'application/json' },
          credentials: 'same-origin',
          signal: controller.signal,
        });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        const payload = await response.json();
        if (!payload || payload.ok !== true || !Array.isArray(payload.data)) {
          throw new Error('Invalid API response');
        }

        const fragment = document.createDocumentFragment();
        payload.data.forEach((field) => fragment.append(renderFieldCard(field)));
        if (payload.data.length === 0) {
          fragment.append(text('p', 'Майдонча ёфт нашуд. Шартҳои ҷустуҷӯро тағйир диҳед.', 'ffbs-empty'));
        }
        results.replaceChildren(fragment);
        if (status) {
          const total = Number(payload.meta?.total ?? payload.data.length);
          status.textContent = `Ёфт шуд: ${total}`;
        }
      } catch (error) {
        if (error.name === 'AbortError') return;
        console.error('FFBS field search failed:', error);
        if (status) status.textContent = 'Ҷустуҷӯ иҷро нашуд. Пайвастро санҷед ва аз нав кӯшиш кунед.';
      } finally {
        results.removeAttribute('aria-busy');
      }
    }

    const delayedSearch = debounce(search, 300);
    form.addEventListener('submit', (event) => {
      event.preventDefault();
      search();
    });
    form.addEventListener('input', delayedSearch);
    form.addEventListener('change', search);
  }

  function renderAvailability(container, payload) {
    const fragment = document.createDocumentFragment();
    payload.days.forEach((day) => {
      const section = text('section', '', 'ffbs-slots__day');
      const heading = text('h3', `${day.day_name} · ${day.date}`, 'ffbs-slots__title');
      const items = text('div', '', 'ffbs-slots__items');

      day.slots.forEach((slot) => {
        const button = text('button', `${slot.start_time} · ${slot.price_label}`, `ffbs-slot ffbs-slot--${slot.status}`);
        button.type = 'button';
        button.disabled = slot.status !== 'available';
        button.setAttribute('aria-pressed', 'false');
        button.dataset.date = day.date;
        button.dataset.hour = String(slot.hour);
        button.dataset.price = String(slot.price);
        button.dataset.priceLabel = slot.price_label;
        button.setAttribute('aria-label', `${slot.start_time}, ${slot.status === 'available' ? 'Холӣ' : slot.status === 'occupied' ? 'Банд' : 'Гузашта'}, ${slot.price_label}`);
        items.append(button);
      });

      section.append(heading, items);
      fragment.append(section);
    });
    container.replaceChildren(fragment);
  }

  async function loadAvailability(container) {
    const basePath = document.documentElement.dataset.ffbsBase || '';
    const endpoint = container.dataset.apiUrl || `${basePath}/public/api/availability/`;
    const fieldId = container.dataset.fieldId;
    const week = container.dataset.week || '0';
    const status = qs(container.dataset.status || '[data-ffbs-availability-status]');
    if (!fieldId) {
      if (status) status.textContent = 'Майдонча интихоб нашудааст.';
      return;
    }

    container.setAttribute('aria-busy', 'true');
    if (status) status.textContent = 'Ҷадвал бор мешавад…';

    try {
      const response = await fetch(makeUrl(endpoint, { field_id: fieldId, week }), {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      });
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      const payload = await response.json();
      if (!payload || payload.ok !== true || !Array.isArray(payload.days)) throw new Error('Invalid API response');
      renderAvailability(container, payload);
      if (status) status.textContent = `${payload.field.name}: ${payload.week.from} — ${payload.week.to}`;
    } catch (error) {
      console.error('FFBS availability request failed:', error);
      if (status) status.textContent = 'Ҷадвал бор карда нашуд. Баъдтар аз нав кӯшиш кунед.';
      container.replaceChildren(text('p', 'Дастрасии вақтро ҳоло нишон додан имкон надорад.', 'ffbs-empty'));
    } finally {
      container.removeAttribute('aria-busy');
    }
  }

  function initAvailability(container) {
    loadAvailability(container);
    container.addEventListener('click', (event) => {
      const button = event.target.closest('button.ffbs-slot');
      if (!button || button.disabled) return;

      qsa('button.ffbs-slot[aria-pressed="true"]', container).forEach((selected) => {
        selected.setAttribute('aria-pressed', 'false');
      });
      button.setAttribute('aria-pressed', 'true');

      const formSelector = container.dataset.bookingForm || '[data-ffbs-booking-form]';
      const form = qs(formSelector);
      if (!form) return;
      const dateInput = qs('[name="date"]', form);
      const hourInput = qs('[name="hour"]', form);
      const priceOutput = qs('[data-ffbs-selected-price]', form);
      const slotOutput = qs('[data-ffbs-selected-slot]', form);
      if (dateInput) dateInput.value = button.dataset.date || '';
      if (hourInput) hourInput.value = button.dataset.hour || '';
      if (priceOutput) priceOutput.textContent = button.dataset.priceLabel || '';
      if (slotOutput) slotOutput.textContent = `${button.dataset.date} · ${button.textContent}`;
    });
  }

  function initValidation(form) {
    const fields = () => qsa('input, select, textarea', form);
    const validateField = (field) => {
      const error = qs(`[data-ffbs-error-for="${CSS.escape(field.name)}"]`, form);
      const valid = field.checkValidity();
      field.setAttribute('aria-invalid', valid ? 'false' : 'true');
      if (error) error.textContent = valid ? '' : (field.validationMessage || 'Қимати майдонро санҷед.');
      return valid;
    };

    form.addEventListener('focusout', (event) => {
      if (event.target.matches('input, select, textarea')) validateField(event.target);
    });
    form.addEventListener('input', (event) => {
      if (event.target.matches('input, select, textarea') && event.target.getAttribute('aria-invalid') === 'true') {
        validateField(event.target);
      }
    });
    form.addEventListener('submit', (event) => {
      let firstInvalid = null;
      fields().forEach((field) => {
        if (!validateField(field) && firstInvalid === null) firstInvalid = field;
      });
      if (firstInvalid) {
        event.preventDefault();
        firstInvalid.focus();
      }
    });
  }

  function initConfirmations() {
    document.addEventListener('submit', (event) => {
      const form = event.target.closest('form[data-ffbs-confirm]');
      if (!form) return;
      const message = form.dataset.confirmMessage || 'Ин амалро тасдиқ мекунед?';
      if (!window.confirm(message)) event.preventDefault();
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    qsa('form[data-ffbs-search]').forEach(initFieldSearch);
    qsa('[data-ffbs-availability]').forEach(initAvailability);
    qsa('form[data-ffbs-validate]').forEach(initValidation);
    initConfirmations();
  });
})();
