/* Diritto di Recesso 54-bis - by Artwork - interazioni front-end */
(function () {
  'use strict';

  document.addEventListener('change', function (e) {
    var t = e.target;
    if (!t) { return; }

    // Abilita "Conferma recesso" solo dopo la spunta della dichiarazione.
    if (t.id === 'ddr-ack') {
      var btn = document.getElementById('ddr-confirm-btn');
      if (btn) { btn.disabled = !t.checked; }
      return;
    }

    // Selezione prodotti: abilita il campo quantità quando la riga è spuntata.
    if (t.classList && t.classList.contains('ddr-item-toggle')) {
      var row = t.closest('.ddr-item-row');
      if (!row) { return; }
      var qty = row.querySelector('.ddr-item-qty');
      if (qty) {
        qty.disabled = !t.checked;
        if (t.checked && (!qty.value || qty.value === '0')) {
          qty.value = qty.getAttribute('max') || '1';
        }
      }
    }
  });

  // Pulsante stampa della ricevuta.
  document.addEventListener('click', function (e) {
    if (e.target && e.target.id === 'ddr-print') {
      window.print();
    }
  });
})();
