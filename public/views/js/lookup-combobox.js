/*
 * FFMS smart combobox (Phase 1).
 * Turns a text input + <datalist> + hidden value input into a "pick or type"
 * control. Open lists (data-allow-custom="1") accept values that are not in the
 * list; closed lists clear the value so the server rejects it.
 * No framework. Degrades to the <noscript> <select> when JS is unavailable.
 */
(function () {
  function buildMap(list) {
    var m = {};
    if (!list) return m;
    [].forEach.call(list.options, function (o) {
      var v = o.getAttribute('data-value');
      if (v === null || v === '') v = o.value;
      m[o.value.toLowerCase()] = v;
    });
    return m;
  }

  function initCombo(el) {
    if (el.dataset.ffInit) return;
    el.dataset.ffInit = '1';

    var display = el.querySelector('.ff-combo-input');
    var hidden  = el.querySelector('input[type="hidden"]');
    var list    = el.querySelector('datalist');
    if (!display || !hidden) return;
    var allow = el.dataset.allowCustom === '1';
    var map = buildMap(list);

    function sync() {
      var v = display.value.trim();
      var mv = map[v.toLowerCase()];
      if (mv !== undefined) {
        hidden.value = mv;
        display.classList.remove('ff-combo-invalid');
      } else if (allow) {
        hidden.value = v;
        display.classList.remove('ff-combo-invalid');
      } else {
        hidden.value = '';
        if (v !== '') display.classList.add('ff-combo-invalid');
        else display.classList.remove('ff-combo-invalid');
      }
      hidden.dispatchEvent(new Event('ff-combo-change'));
    }

    display.addEventListener('input', sync);
    display.addEventListener('change', sync);
    sync();

    // Dependent options: reload the list when the parent combo changes.
    var dep = el.dataset.dependsOn;
    var cat = el.dataset.category;
    if (dep && cat && list) {
      var parentHidden = document.getElementById(dep) || document.querySelector('[name="' + dep + '"]');
      if (parentHidden) {
        var reload = function () {
          var pv = parentHidden.value || '';
          display.value = '';
          hidden.value = '';
          var url = 'index.php?module=Lookup&action=options&category=' + encodeURIComponent(cat)
                  + '&parent=' + encodeURIComponent(pv);
          fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (j) {
              if (!j || !j.options) return;
              list.innerHTML = '';
              map = {};
              j.options.forEach(function (o) {
                var opt = document.createElement('option');
                opt.value = o.label;
                opt.setAttribute('data-value', o.value);
                list.appendChild(opt);
                map[o.label.toLowerCase()] = o.value;
              });
            })
            .catch(function () {});
        };
        parentHidden.addEventListener('ff-combo-change', reload);
        parentHidden.addEventListener('change', reload);
        if (parentHidden.value) reload();
      }
    }
  }

  function boot() {
    [].forEach.call(document.querySelectorAll('.ff-combo'), initCombo);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
