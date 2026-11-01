(function () {
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };

  // Mobile sidebar
  $$('[data-open-side]').forEach(function (b) { b.addEventListener('click', function () { $('#side').classList.add('open'); }); });
  $$('[data-close-side]').forEach(function (b) { b.addEventListener('click', function () { $('#side').classList.remove('open'); }); });

  // Select all + row highlight
  var all = $('[data-check-all]');
  var boxes = $$('input[name="uids[]"][type=checkbox]');
  function paint() { boxes.forEach(function (b) { b.closest('.msg').classList.toggle('selected', b.checked); }); }
  if (all) all.addEventListener('change', function () { boxes.forEach(function (b) { b.checked = all.checked; }); paint(); });
  boxes.forEach(function (b) { b.addEventListener('change', paint); });

  // Move to folder
  var mv = $('[data-move]');
  if (mv) mv.addEventListener('change', function () { if (mv.value) mv.form.submit(); });

  // Confirm dangerous actions
  $$('[data-confirm]').forEach(function (b) { b.addEventListener('click', function (e) { if (!confirm(b.getAttribute('data-confirm'))) e.preventDefault(); }); });

  // Auto-size the message iframe
  $$('iframe[data-autoheight]').forEach(function (f) {
    function fit() {
      try {
        var d = f.contentDocument; if (!d || !d.body) return;
        f.style.height = Math.max(120, d.documentElement.scrollHeight, d.body.scrollHeight) + 16 + 'px';
      } catch (e) { f.style.height = '70vh'; }
    }
    f.addEventListener('load', function () { fit(); setTimeout(fit, 300); setTimeout(fit, 1200); });
    window.addEventListener('resize', fit);
  });

  // Show remote images
  $$('[data-show-images]').forEach(function (b) {
    b.addEventListener('click', function () {
      var f = $('iframe.body-frame'); if (f) f.src = b.getAttribute('data-show-images');
      b.closest('.notice').remove();
    });
  });

  // Cc/Bcc toggle
  $$('[data-toggle]').forEach(function (b) {
    b.addEventListener('click', function () { var t = $(b.getAttribute('data-toggle')); t.classList.toggle('hidden'); var i = $('input', t); if (i && !t.classList.contains('hidden')) i.focus(); });
  });

  // Attachment list
  var files = $('[data-files]'), list = $('[data-file-list]');
  if (files) files.addEventListener('change', function () {
    list.innerHTML = '';
    Array.prototype.forEach.call(files.files, function (f) {
      var c = document.createElement('span'); c.className = 'chip';
      c.textContent = f.name + ' · ' + (f.size > 1048576 ? (f.size / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(f.size / 1024)) + ' KB');
      list.appendChild(c);
    });
  });

  // Quick templates
  $$('.snip').forEach(function (b) {
    b.addEventListener('click', function () {
      var body = $('#body'), subj = $('#subject');
      if (body.value.trim() && !confirm('Replace what you have written with this template?')) return;
      body.value = b.getAttribute('data-snip-body');
      if (subj && !subj.value.trim()) subj.value = b.getAttribute('data-snip-subject');
      body.focus();
    });
  });

  // Preview
  var pv = $('[data-preview]');
  if (pv) pv.addEventListener('click', function () {
    var form = $('#composeForm'), fd = new FormData();
    ['csrf', 'body', 'quote'].forEach(function (n) { var el = form.elements[n]; if (el) fd.append(n, el.value); });
    if (form.elements.tpl.checked) fd.append('tpl', '1');
    if (form.elements.sig.checked) fd.append('sig', '1');
    fd.append('a', 'preview');
    fetch($('#previewUrl').value, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.text(); })
      .then(function (html) { $('#previewFrame').srcdoc = html; $('#previewModal').classList.remove('hidden'); });
  });
  $$('[data-close-modal]').forEach(function (b) { b.addEventListener('click', function () { $('#previewModal').classList.add('hidden'); }); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { var m = $('#previewModal'); if (m) m.classList.add('hidden'); } });

  // Warn before leaving an unsent message
  var cf = $('#composeForm');
  if (cf) {
    var dirty = false, sending = false;
    cf.addEventListener('input', function () { dirty = true; });
    cf.addEventListener('submit', function () { sending = true; });
    window.addEventListener('beforeunload', function (e) { if (dirty && !sending) { e.preventDefault(); e.returnValue = ''; } });
  }
})();
