/* База знаний «Море фото»: поля и отметки чек-листа сохраняются только в этом браузере. */
(function () {
  var page = document.querySelector('[data-store]');
  if (!page) return;
  var KEY = 'morefoto-guide-' + page.getAttribute('data-store') + '-v2';
  var fields = [].slice.call(document.querySelectorAll('[data-f]'));
  var boxes = [].slice.call(document.querySelectorAll('.check-group input[type=checkbox]'));
  var saveEl = document.getElementById('save');
  var timer = null;

  function fmt(iso) {
    try {
      return new Date(iso).toLocaleString('ru-RU', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
    } catch {
      return '';
    }
  }
  function status(text) {
    if (saveEl) saveEl.textContent = text;
  }
  function collect() {
    var f = {},
      c = {};
    fields.forEach(function (el) {
      f[el.id] = el.value;
    });
    boxes.forEach(function (b) {
      c[b.id] = b.checked;
    });
    return { fields: f, checks: c, updatedAt: new Date().toISOString() };
  }
  function apply(data) {
    var f = (data && data.fields) || {},
      c = (data && data.checks) || {};
    fields.forEach(function (el) {
      if (typeof f[el.id] === 'string') el.value = f[el.id];
    });
    boxes.forEach(function (b) {
      b.checked = !!c[b.id];
    });
  }
  function linkState(input) {
    var prefix = input.getAttribute('data-link');
    var open = document.querySelector('[data-open="' + input.id + '"]');
    var hint = document.querySelector('[data-hint="' + input.id + '"]');
    var v = input.value.trim();
    var ok = /^https:\/\/app\.morefoto36\.ru\//.test(v) && v.indexOf(prefix) > -1;
    if (open) {
      open.setAttribute('aria-disabled', ok ? 'false' : 'true');
      open.href = ok ? v : '#';
    }
    if (!hint) return;
    if (!hint.dataset.empty) hint.dataset.empty = hint.textContent;
    if (!v) {
      hint.textContent = hint.dataset.empty;
      hint.classList.remove('bad');
    } else if (!ok) {
      hint.textContent = 'Ожидается адрес вида https://app.morefoto36.ru' + prefix + '…';
      hint.classList.add('bad');
    } else {
      hint.textContent = 'Ссылка похожа на правильную.';
      hint.classList.remove('bad');
    }
  }
  function derive() {
    var done = boxes.filter(function (b) {
      return b.checked;
    }).length;
    var count = document.getElementById('count');
    var bar = document.getElementById('bar');
    if (count) count.textContent = done + ' из ' + boxes.length;
    if (bar) bar.style.width = (boxes.length ? (done / boxes.length) * 100 : 0) + '%';
    [].forEach.call(document.querySelectorAll('.check-group'), function (g) {
      var bs = g.querySelectorAll('input[type=checkbox]'),
        n = 0;
      [].forEach.call(bs, function (b) {
        if (b.checked) n++;
      });
      var d = g.querySelector('.done');
      if (d) d.textContent = n === bs.length ? 'Готово' : n + ' / ' + bs.length;
      g.dataset.complete = n === bs.length ? '1' : '0';
    });
    [].forEach.call(document.querySelectorAll('[data-link]'), linkState);
  }
  function save() {
    var state = collect();
    try {
      localStorage.setItem(KEY, JSON.stringify(state));
      status('Сохранено в этом браузере · ' + fmt(state.updatedAt));
    } catch {
      status('Браузер не даёт сохранить — запишите данные отдельно');
    }
  }
  function changed(delay) {
    derive();
    clearTimeout(timer);
    timer = setTimeout(save, delay);
  }

  fields.forEach(function (el) {
    el.addEventListener('input', function () {
      changed(600);
    });
    el.addEventListener('change', function () {
      changed(100);
    });
  });
  boxes.forEach(function (b) {
    b.addEventListener('change', function () {
      changed(100);
    });
  });
  [].forEach.call(document.querySelectorAll('[data-copy]'), function (btn) {
    btn.addEventListener('click', function () {
      var input = document.getElementById(btn.getAttribute('data-copy'));
      var v = input.value.trim();
      if (!v) return;
      var label = btn.textContent;
      var reset = function () {
        setTimeout(function () {
          btn.textContent = label;
        }, 1800);
      };
      var fallback = function () {
        input.focus();
        input.select();
        btn.textContent = 'Выделено — Ctrl+C';
        reset();
      };
      try {
        navigator.clipboard.writeText(v).then(function () {
          btn.textContent = 'Скопировано';
          reset();
        }, fallback);
      } catch {
        fallback();
      }
    });
  });
  var reset = document.getElementById('reset');
  if (reset) {
    reset.addEventListener('click', function () {
      if (reset.dataset.armed !== '1') {
        reset.dataset.armed = '1';
        reset.textContent = 'Точно очистить?';
        return;
      }
      try {
        localStorage.removeItem(KEY);
      } catch {
        // Storage may be blocked; the page keeps working without it.
      }
      fields.forEach(function (el) {
        el.value = el.defaultValue;
      });
      boxes.forEach(function (b) {
        b.checked = false;
      });
      reset.dataset.armed = '';
      reset.textContent = 'Очистить всё';
      status('Поля сохраняются в этом браузере');
      derive();
    });
  }

  var saved = null;
  try {
    // The first version of the page kept the same field ids under data-legacy.
    saved = JSON.parse(localStorage.getItem(KEY) || localStorage.getItem(page.getAttribute('data-legacy') || KEY) || 'null');
  } catch {
    // Storage may be blocked; the page keeps working without it.
  }
  if (saved) {
    apply(saved);
    status('Сохранено в этом браузере · ' + fmt(saved.updatedAt));
  } else status('Поля сохраняются в этом браузере');
  derive();
})();
