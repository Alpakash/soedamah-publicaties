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

    // JavaScript werkt: toon de echte tekstverwerker en verberg het gewone
    // tekstveld dat zonder JavaScript als werkende terugval dient.
    area.innerHTML = hidden.value;
    toolbar.hidden = false;
    area.hidden = false;
    hidden.hidden = true;

    // Eén keer Enter maakt voortaan een nieuwe alinea (<p>, met witruimte ertussen)
    // in plaats van een enkele regelafbreking. Shift+Enter blijft een gewone
    // regelafbreking binnen dezelfde alinea.
    try {
      document.execCommand('defaultParagraphSeparator', false, 'p');
    } catch (e) {
      /* Oudere browser: valt terug op standaardgedrag. */
    }

    function syncHidden() {
      hidden.value = area.innerHTML;
    }

    function escapeText(text) {
      return text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    // Plakt tekst als nette alinea's: lege regels scheiden alinea's; staan er geen
    // lege regels, dan wordt elke regel een eigen alinea. Zo loopt geplakte tekst
    // netjes door met witruimte tussen de alinea's, zonder rommelige opmaak.
    area.addEventListener('paste', function (e) {
      var cd = e.clipboardData || window.clipboardData;
      if (!cd) {
        return;
      }
      e.preventDefault();
      var text = (cd.getData('text/plain') || '').replace(/\r\n?/g, '\n');
      var html;
      if (/\n[ \t]*\n/.test(text)) {
        html = text.split(/\n[ \t]*\n+/).map(function (block) {
          block = block.replace(/^\n+|\n+$/g, '');
          return block.trim() === '' ? '' : '<p>' + escapeText(block).replace(/\n/g, '<br>') + '</p>';
        }).join('');
      } else {
        html = text.split('\n').map(function (line) {
          return line.trim() === '' ? '' : '<p>' + escapeText(line) + '</p>';
        }).join('');
      }
      if (!html) {
        html = '<p>' + escapeText(text) + '</p>';
      }
      document.execCommand('insertHTML', false, html);
      syncHidden();
    });

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
