/* База знаний «Море фото»: просмотр скриншотов и чек-лист прогона (поля и отметки хранятся только в этом браузере). */

/* Скриншоты статьи открываются поверх страницы: листание, подпись, увеличение по нажатию,
   закрытие крестиком, Esc, кнопкой «Назад» и кликом по фону. */
(function () {
  var links = [].slice.call(document.querySelectorAll('.shot > a[href]'));
  if (!links.length || typeof HTMLDialogElement !== 'function') return;
  var box, img, cap, count, prev, next;
  var index = 0;
  var touchX = null;

  function button(cls, label, text) {
    var b = document.createElement('button');
    b.type = 'button';
    b.className = cls;
    b.setAttribute('aria-label', label);
    b.title = label;
    b.textContent = text;
    return b;
  }
  function caption(link) {
    var fig = link.closest('figure');
    var text = fig && fig.querySelector('figcaption');
    var pic = link.querySelector('img');
    return (text && text.textContent.trim()) || (pic && pic.alt) || '';
  }
  function build() {
    box = document.createElement('dialog');
    box.className = 'lb';
    box.setAttribute('aria-label', 'Скриншот');
    var bar = document.createElement('div');
    bar.className = 'lb-bar';
    count = document.createElement('span');
    count.className = 'lb-count';
    var close = button('lb-close', 'Закрыть', '×');
    bar.append(count, close);
    var stage = document.createElement('div');
    stage.className = 'lb-stage';
    img = document.createElement('img');
    img.className = 'lb-img';
    img.alt = '';
    stage.append(img);
    cap = document.createElement('p');
    cap.className = 'lb-cap';
    prev = button('lb-nav lb-prev', 'Предыдущий скриншот', '‹');
    next = button('lb-nav lb-next', 'Следующий скриншот', '›');
    box.append(bar, stage, cap, prev, next);
    document.body.append(box);

    close.addEventListener('click', function () {
      hide();
    });
    prev.addEventListener('click', function () {
      show(index - 1);
    });
    next.addEventListener('click', function () {
      show(index + 1);
    });
    // A click on the dark area around the picture closes the viewer.
    box.addEventListener('click', function (e) {
      if (e.target === box || e.target === stage || e.target === bar) hide();
    });
    // A tap on the picture shows it at full size; the enlarged picture scrolls inside the viewer.
    img.addEventListener('click', function () {
      box.classList.toggle('lb-zoomed');
    });
    box.addEventListener('cancel', function (e) {
      e.preventDefault();
      hide();
    });
    box.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowLeft') show(index - 1);
      else if (e.key === 'ArrowRight') show(index + 1);
    });
    stage.addEventListener(
      'touchstart',
      function (e) {
        touchX = e.touches.length === 1 ? e.touches[0].clientX : null;
      },
      { passive: true }
    );
    stage.addEventListener('touchend', function (e) {
      if (touchX === null || box.classList.contains('lb-zoomed')) return;
      var dx = e.changedTouches[0].clientX - touchX;
      touchX = null;
      if (Math.abs(dx) > 50) show(index + (dx < 0 ? 1 : -1));
    });
    window.addEventListener('popstate', function () {
      if (box.open) hide(true);
    });
  }
  function show(i) {
    if (i < 0 || i >= links.length) return;
    index = i;
    box.classList.remove('lb-zoomed');
    var link = links[i];
    var pic = link.querySelector('img');
    img.src = link.href;
    img.alt = (pic && pic.alt) || '';
    cap.textContent = caption(link);
    count.textContent = i + 1 + ' из ' + links.length;
    prev.disabled = i === 0;
    next.disabled = i === links.length - 1;
    [links[i - 1], links[i + 1]].forEach(function (near) {
      if (near) new Image().src = near.href;
    });
  }
  function open(i) {
    if (!box) build();
    show(i);
    if (box.open) return;
    box.showModal();
    document.documentElement.classList.add('lb-lock');
    // The phone's Back button closes the viewer instead of leaving the article.
    history.pushState({ guideLightbox: true }, '');
  }
  function hide(fromHistory) {
    if (!box.open) return;
    box.close();
    document.documentElement.classList.remove('lb-lock');
    links[index].focus({ preventScroll: true });
    if (!fromHistory && history.state && history.state.guideLightbox) history.back();
  }

  links.forEach(function (link, i) {
    link.addEventListener('click', function (e) {
      if (e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
      e.preventDefault();
      open(i);
    });
  });
})();

/* Чек-лист прогона. */
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
