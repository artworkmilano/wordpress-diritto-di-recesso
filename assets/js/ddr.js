/* Diritto di Recesso 54-bis - by Artwork - interazioni front-end */
(function () {
  'use strict';

  function setRowEnabled(row, on) {
    var qty = row.querySelector('.ddr-item-qty');
    var btns = row.querySelectorAll('.ddr-qty-btn');
    if (qty) {
      qty.disabled = !on;
      if (on && (!qty.value || qty.value === '0')) {
        qty.value = qty.getAttribute('max') || '1';
      }
    }
    for (var i = 0; i < btns.length; i++) { btns[i].disabled = !on; }
  }

  function step(row, delta) {
    var qty = row.querySelector('.ddr-item-qty');
    if (!qty || qty.disabled) { return; }
    var min = parseInt(qty.getAttribute('min') || '1', 10);
    var max = parseInt(qty.getAttribute('max') || '1', 10);
    var val = parseInt(qty.value || min, 10) + delta;
    if (isNaN(val)) { val = min; }
    if (val < min) { val = min; }
    if (val > max) { val = max; }
    qty.value = val;
  }

  document.addEventListener('change', function (e) {
    var t = e.target;
    if (!t) { return; }

    // Abilita "Conferma recesso" solo dopo la spunta della dichiarazione.
    if (t.id === 'ddr-ack') {
      var btn = document.getElementById('ddr-confirm-btn');
      if (btn) { btn.disabled = !t.checked; }
      return;
    }

    // Selezione prodotti: abilita quantità + stepper quando la riga è spuntata.
    if (t.classList && t.classList.contains('ddr-item-toggle')) {
      var row = t.closest('.ddr-item-row');
      if (row) { setRowEnabled(row, t.checked); }
    }
  });

  document.addEventListener('click', function (e) {
    var t = e.target;
    if (!t) { return; }

    // Stampa ricevuta.
    if (t.id === 'ddr-print') { window.print(); return; }

    // Stepper quantità −/+.
    if (t.classList && t.classList.contains('ddr-qty-btn')) {
      e.preventDefault();
      var row = t.closest('.ddr-item-row');
      if (row) { step(row, t.classList.contains('ddr-qty-plus') ? 1 : -1); }
    }
  });
})();
