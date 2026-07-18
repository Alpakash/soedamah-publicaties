/**
 * Lichte tekstverwerker voor artikelen: contenteditable + document.execCommand.
 * Geen externe library nodig, werkt dus binnen de strikte Content-Security-Policy.
 */
(function () {
  'use strict';

  function buildVideoEmbed(url) {
    var yt = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([A-Za-z0-9_-]{6,})/);
    if (yt) {
      return '<iframe src="https://www.youtube-nocookie.com/embed/' + yt[1]
        + '" width="640" height="360" allowfullscreen loading="lazy" frameborder="0"></iframe>';
    }
    var vimeo = url.match(/vimeo\.com\/(?:video\/)?(\d+)/);
    if (vimeo) {
      return '<iframe src="https://player.vimeo.com/video/' + vimeo[1]
        + '" width="640" height="360" allowfullscreen loading="lazy" frameborder="0"></iframe>';
    }
    return null;
  }

  function initEditor(root) {
    var toolbar = root.querySelector('.editor-toolbar');
    var area = root.querySelector('.editor-area');
    var hidden = root.querySelector('.editor-hidden-field');
    var fileInput = root.querySelector('.editor-file-input');
    var uploadUrl = root.getAttribute('data-upload-url');
    var csrfToken = root.getAttribute('data-csrf');

    function syncHidden() {
      hidden.value = area.innerHTML;
    }

    function exec(command, value) {
      area.focus();
      document.execCommand(command, false, value || undefined);
      syncHidden();
    }

    toolbar.addEventListener('click', function (e) {
      var btn = e.target.closest('button[data-cmd]');
      if (!btn) {
        return;
      }
      e.preventDefault();
      var cmd = btn.getAttribute('data-cmd');

      if (cmd === 'createLink') {
        var url = window.prompt('Naar welke link moet dit verwijzen?', 'https://');
        if (url) {
          exec('createLink', url);
        }
        return;
      }
      if (cmd === 'insertImage') {
        fileInput.click();
        return;
      }
      if (cmd === 'insertVideo') {
        var videoUrl = window.prompt('Plak de YouTube- of Vimeo-link:');
        if (!videoUrl) {
          return;
        }
        var embed = buildVideoEmbed(videoUrl.trim());
        if (!embed) {
          window.alert('Dit lijkt geen geldige YouTube- of Vimeo-link.');
          return;
        }
        exec('insertHTML', embed);
        return;
      }
      if (cmd === 'formatBlock') {
        exec('formatBlock', '<' + btn.getAttribute('data-value') + '>');
        return;
      }
      exec(cmd);
    });

    fileInput.addEventListener('change', function () {
      var file = fileInput.files[0];
      if (!file) {
        return;
      }
      var data = new FormData();
      data.append('afbeelding', file);
      data.append('csrf', csrfToken);
      fetch(uploadUrl, { method: 'POST', body: data })
        .then(function (r) { return r.json(); })
        .then(function (json) {
          if (json.url) {
            exec('insertHTML', '<img src="' + json.url + '" alt="">');
          } else {
            window.alert(json.error || 'Uploaden is mislukt.');
          }
        })
        .catch(function () {
          window.alert('Uploaden is mislukt. Controleer je internetverbinding.');
        });
      fileInput.value = '';
    });

    area.addEventListener('input', syncHidden);
    area.addEventListener('blur', syncHidden);

    var form = root.closest('form');
    if (form) {
      form.addEventListener('submit', syncHidden);
    }
  }

  document.querySelectorAll('.rich-editor').forEach(initEditor);
})();
