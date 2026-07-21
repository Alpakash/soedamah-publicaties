<?php
require __DIR__ . '/../../app/bootstrap.php';
require_admin();

/**
 * Verwerkt één geüpload bestand voor een boek. Voegt fouten toe aan $errors.
 */
function admin_handle_upload(
    array $book,
    string $field,
    array $allowedExt,
    array $allowedMime,
    string $dir,
    string $column,
    array &$errors
): void {
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return;
    }
    $file = $_FILES[$field];
    $labels = ['cover' => 'omslag', 'pdf' => 'PDF', 'epub' => 'EPUB'];
    $label = $labels[$field] ?? $field;

    if ($file['error'] !== UPLOAD_ERR_OK) {
        if (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            $errors[] = 'Het bestand voor ' . $label . ' is groter dan de server toestaat. '
                . 'Verhoog upload_max_filesize en post_max_size in Plesk (zie docs/INSTALLATIE-PLESK.md).';
        } else {
            $errors[] = 'Upload van ' . $label . ' is mislukt (foutcode ' . (int) $file['error'] . '). Probeer het opnieuw.';
        }
        return;
    }

    $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        $errors[] = 'Het bestand voor ' . $label . ' heeft een verkeerde extensie (verwacht: .' . implode(', .', $allowedExt) . ').';
        return;
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowedMime, true)) {
        $errors[] = 'Het bestand voor ' . $label . ' lijkt geen geldig ' . $label . '-bestand te zijn (' . $mime . ').';
        return;
    }

    $name = $book['id'] . '-' . $book['slug'] . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = DATA_DIR . '/uploads/' . $dir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        $errors[] = 'Het bestand voor ' . $label . ' kon niet worden opgeslagen. Controleer of de map data/ schrijfbaar is.';
        return;
    }
    if ($book[$column] !== '') {
        @unlink(DATA_DIR . '/uploads/' . $dir . '/' . $book[$column]);
    }
    $stmt = db()->prepare("UPDATE books SET {$column} = ? WHERE id = ?");
    $stmt->execute([$name, $book['id']]);
}

function admin_remove_file(array $book, string $dir, string $column): void
{
    if ($book[$column] !== '') {
        @unlink(DATA_DIR . '/uploads/' . $dir . '/' . $book[$column]);
        $stmt = db()->prepare("UPDATE books SET {$column} = '' WHERE id = ?");
        $stmt->execute([$book['id']]);
    }
}

function admin_filesize(string $dir, string $file): string
{
    $path = DATA_DIR . '/uploads/' . $dir . '/' . $file;
    if (!is_file($path)) {
        return 'bestand ontbreekt!';
    }
    $bytes = (int) filesize($path);
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1, ',', '.') . ' MB';
    }
    return number_format($bytes / 1024, 0, ',', '.') . ' kB';
}

$id = (int) ($_GET['id'] ?? 0);
$book = $id > 0 ? book_find($id) : null;
if ($id > 0 && $book === null) {
    redirect(url('admin/'));
}

$errors = [];
$saved = isset($_GET['saved']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        // post_max_size overschreden: PHP gooit dan alle formulierdata weg.
        $errors[] = 'De upload is groter dan de server toestaat (post_max_size). '
            . 'Verhoog de limiet in Plesk (zie docs/INSTALLATIE-PLESK.md) en probeer het opnieuw.';
    } else {
        csrf_check();
        $title       = trim((string) ($_POST['title'] ?? ''));
        $slugInput   = trim((string) ($_POST['slug'] ?? ''));
        $subtitle    = trim((string) ($_POST['subtitle'] ?? ''));
        $description = sanitize_article_html((string) ($_POST['description'] ?? ''));
        $priceCents  = parse_price((string) ($_POST['price'] ?? '0'));
        $published   = !empty($_POST['published']) ? 1 : 0;
        $inStock     = !empty($_POST['in_stock']) ? 1 : 0;
        $isPhysical  = !empty($_POST['is_physical']) ? 1 : 0;
        $hideNewBadge = !empty($_POST['hide_new_badge']) ? 1 : 0;
        $sortOrder   = (int) ($_POST['sort_order'] ?? 0);

        if ($title === '') {
            $errors[] = 'Vul een titel in.';
        }
        if ($priceCents < 0) {
            $errors[] = 'De prijs is ongeldig. Gebruik bijvoorbeeld 12,50 (of 0 voor gratis).';
            $priceCents = 0;
        }

        // De slug (het leesbare deel van de URL) is bewerkbaar. Leeg gelaten =
        // automatisch uit de titel afleiden. De slug wordt altijd genormaliseerd
        // (kleine letters, koppeltekens) en uniek gemaakt t.o.v. andere boeken.
        $slugBase = $slugInput !== '' ? $slugInput : $title;
        $slug = book_unique_slug($slugBase, $book !== null ? (int) $book['id'] : 0);

        if (!$errors) {
            if ($book === null) {
                $stmt = db()->prepare(
                    'INSERT INTO books (slug, title, subtitle, description, price_cents, published, in_stock, is_physical, hide_new_badge, sort_order, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $slug,
                    $title, $subtitle, $description, $priceCents, $published, $inStock, $isPhysical, $hideNewBadge, $sortOrder, now(),
                ]);
                $book = book_find((int) db()->lastInsertId());
            } else {
                $stmt = db()->prepare(
                    'UPDATE books SET slug = ?, title = ?, subtitle = ?, description = ?, price_cents = ?, published = ?, in_stock = ?, is_physical = ?, hide_new_badge = ?, sort_order = ?
                     WHERE id = ?'
                );
                $stmt->execute([$slug, $title, $subtitle, $description, $priceCents, $published, $inStock, $isPhysical, $hideNewBadge, $sortOrder, $book['id']]);
                $book = book_find((int) $book['id']);
            }

            if (!empty($_POST['remove_cover'])) {
                admin_remove_file($book, 'covers', 'cover_file');
            }
            if (!empty($_POST['remove_pdf'])) {
                admin_remove_file($book, 'books', 'pdf_file');
            }
            if (!empty($_POST['remove_epub'])) {
                admin_remove_file($book, 'books', 'epub_file');
            }
            $book = book_find((int) $book['id']);

            admin_handle_upload($book, 'cover', ['jpg', 'jpeg', 'png', 'webp'],
                ['image/jpeg', 'image/png', 'image/webp'], 'covers', 'cover_file', $errors);
            admin_handle_upload($book, 'pdf', ['pdf'],
                ['application/pdf'], 'books', 'pdf_file', $errors);
            admin_handle_upload($book, 'epub', ['epub'],
                ['application/epub+zip', 'application/zip'], 'books', 'epub_file', $errors);

            if (!$errors) {
                redirect(url('admin/boek-bewerken.php?id=' . $book['id'] . '&saved=1'));
            }
            $book = book_find((int) $book['id']);
        }
    }
}

$isNew = ($book === null);
$formValue = static function (string $field, string $default = '') use ($book): string {
    if (isset($_POST[$field])) {
        return (string) $_POST[$field];
    }
    return $book !== null ? (string) $book[$field] : $default;
};
$descriptionValue = $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['description'])
    ? sanitize_article_html((string) $_POST['description'])
    : (string) ($book['description'] ?? '');
$priceValue = isset($_POST['price'])
    ? (string) $_POST['price']
    : ($book !== null ? number_format(((int) $book['price_cents']) / 100, 2, ',', '') : '0');
$publishedChecked = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? !empty($_POST['published'])
    : ($book !== null && (int) $book['published'] === 1);
$inStockChecked = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? !empty($_POST['in_stock'])
    : ($book === null || (int) $book['in_stock'] === 1);
$isPhysicalChecked = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? !empty($_POST['is_physical'])
    : ($book !== null && (int) $book['is_physical'] === 1);
$hideNewBadgeChecked = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? !empty($_POST['hide_new_badge'])
    : ($book !== null && (int) $book['hide_new_badge'] === 1);

$pageTitle = $isNew ? 'Nieuwe publicatie' : 'Bewerken: ' . $book['title'];
include APP_ROOT . '/app/templates/admin_header.php';
?>
<h1><?= $isNew ? 'Nieuwe publicatie' : 'Publicatie bewerken' ?></h1>

<?php if ($saved): ?>
  <p class="alert alert-success">Opgeslagen!
    <?php if ($book !== null && (int) $book['published'] && book_orderable($book)): ?>
      <a href="<?= e(book_url($book['slug'])) ?>">Bekijk de pagina in de shop.</a>
    <?php elseif ($book !== null && (int) $book['published'] && !book_has_deliverable($book)): ?>
      Let op: er is nog geen PDF of EPUB geüpload (en het is geen fysiek boek), bezoekers kunnen dit boek zien maar nog niet bestellen.
    <?php elseif ($book !== null && (int) $book['published'] && !(int) $book['in_stock']): ?>
      Let op: dit boek staat op "niet op voorraad", bezoekers zien het boek maar kunnen het niet bestellen.
    <?php endif; ?>
  </p>
<?php endif; ?>
<?php foreach ($errors as $error): ?>
  <p class="alert alert-error"><?= e($error) ?></p>
<?php endforeach; ?>

<form method="post" enctype="multipart/form-data" class="stacked-form form-wide"
      action="<?= e(url('admin/boek-bewerken.php' . ($book !== null ? '?id=' . $book['id'] : ''))) ?>">
  <?= csrf_field() ?>

  <label for="title">Titel *</label>
  <input type="text" id="title" name="title" required maxlength="200" value="<?= e($formValue('title')) ?>">

  <label for="subtitle">Ondertitel</label>
  <input type="text" id="subtitle" name="subtitle" maxlength="200" value="<?= e($formValue('subtitle')) ?>">

  <label for="slug">URL-naam (slug)</label>
  <input type="text" id="slug" name="slug" maxlength="200" value="<?= e($formValue('slug')) ?>"
         placeholder="wordt automatisch uit de titel gemaakt">
  <p class="field-hint">
    Het leesbare deel van het webadres, bijv. <code><?= e(base_url()) ?>/boek/<strong><?= e($formValue('slug') !== '' ? $formValue('slug') : 'titel-van-het-boek') ?></strong></code>.
    Leeg laten = automatisch uit de titel. Alleen kleine letters, cijfers en koppeltekens; het adres wordt uniek gemaakt.
    <?php if (!$isNew): ?><br><strong>Let op:</strong> als je dit wijzigt, verandert het webadres van deze publicatie en werken oude links naar dit boek niet meer.<?php endif; ?>
  </p>

  <label for="description-area">Beschrijving</label>
  <div class="rich-editor">
    <div class="editor-toolbar" hidden>
      <button type="button" data-cmd="bold" title="Vet"><strong>V</strong></button>
      <button type="button" data-cmd="italic" title="Cursief"><em>I</em></button>
      <span class="editor-sep"></span>
      <button type="button" data-cmd="insertUnorderedList" title="Opsomming">• Lijst</button>
      <button type="button" data-cmd="insertOrderedList" title="Genummerde lijst">1. Lijst</button>
      <span class="editor-sep"></span>
      <button type="button" data-cmd="createLink" title="Link invoegen">Link</button>
    </div>
    <div id="description-area" class="editor-area" contenteditable="true" hidden><?= $descriptionValue ?></div>
    <input type="file" class="editor-file-input" hidden>
    <textarea name="description" class="editor-hidden-field" rows="10"><?= e($descriptionValue) ?></textarea>
  </div>
  <p class="field-hint">Waar gaat het boek over? Gebruik de knoppen voor opmaak.
     (Werkt JavaScript niet? Typ dan gewoon in het tekstvak hierboven.)</p>

  <div class="form-row">
    <div>
      <label for="price">Prijs in euro's *</label>
      <input type="text" id="price" name="price" inputmode="decimal" value="<?= e($priceValue) ?>">
      <p class="field-hint">Bijvoorbeeld 12,50, vul 0 in voor een gratis publicatie.</p>
    </div>
    <div>
      <label for="sort_order">Volgorde</label>
      <input type="number" id="sort_order" name="sort_order" value="<?= e($formValue('sort_order', '0')) ?>">
      <p class="field-hint">Hoger getal = eerder in het overzicht.</p>
    </div>
  </div>

  <label class="checkbox-line">
    <input type="checkbox" name="is_physical" value="1" <?= $isPhysicalChecked ? 'checked' : '' ?>>
    Fysiek boek (verzending per post)
  </label>
  <p class="field-hint">Voor gedrukte uitgaven zonder PDF/EPUB. De koper vult bij het afrekenen
     een verzendadres in en ontvangt geen downloadlink, maar een bevestiging dat het boek per
     post volgt.</p>

  <fieldset>
    <legend>Bestanden</legend>

    <label for="cover">Omslagafbeelding (JPG, PNG of WebP)</label>
    <?php if ($book !== null && $book['cover_file'] !== ''): ?>
      <p class="current-file">Huidige omslag: <?= e(admin_filesize('covers', $book['cover_file'])) ?>
        <label class="checkbox-inline"><input type="checkbox" name="remove_cover" value="1"> verwijderen</label>
      </p>
    <?php endif; ?>
    <input type="file" id="cover" name="cover" accept=".jpg,.jpeg,.png,.webp">

    <label for="pdf">PDF-bestand</label>
    <?php if ($book !== null && $book['pdf_file'] !== ''): ?>
      <p class="current-file">Huidige PDF: <?= e(admin_filesize('books', $book['pdf_file'])) ?>
        <label class="checkbox-inline"><input type="checkbox" name="remove_pdf" value="1"> verwijderen</label>
      </p>
    <?php endif; ?>
    <input type="file" id="pdf" name="pdf" accept=".pdf">

    <label for="epub">EPUB-bestand (optioneel)</label>
    <?php if ($book !== null && $book['epub_file'] !== ''): ?>
      <p class="current-file">Huidige EPUB: <?= e(admin_filesize('books', $book['epub_file'])) ?>
        <label class="checkbox-inline"><input type="checkbox" name="remove_epub" value="1"> verwijderen</label>
      </p>
    <?php endif; ?>
    <input type="file" id="epub" name="epub" accept=".epub">

    <p class="field-hint">Nieuw bestand uploaden vervangt automatisch het huidige bestand.</p>
  </fieldset>

  <label class="checkbox-line">
    <input type="checkbox" name="published" value="1" <?= $publishedChecked ? 'checked' : '' ?>>
    Zichtbaar in de shop
  </label>

  <label class="checkbox-line">
    <input type="checkbox" name="in_stock" value="1" <?= $inStockChecked ? 'checked' : '' ?>>
    Op voorraad
  </label>
  <p class="field-hint">Zet dit uit om de publicatie zichtbaar te houden maar tijdelijk niet
     bestelbaar te maken (toont "Niet op voorraad" in plaats van de koopknop).</p>

  <label class="checkbox-line">
    <input type="checkbox" name="hide_new_badge" value="1" <?= $hideNewBadgeChecked ? 'checked' : '' ?>>
    Verberg de "Nieuw"-badge
  </label>
  <p class="field-hint">Zet dit aan voor een oudere titel die nu pas aan de shop is toegevoegd,
     zodat die niet als "Nieuw" wordt gepresenteerd.</p>

  <div class="form-actions">
    <button type="submit" class="btn btn-primary">Opslaan</button>
    <a class="btn btn-secondary" href="<?= e(url('admin/')) ?>">Terug naar overzicht</a>
    <?php if ($book !== null): ?>
      <a class="btn btn-danger-link" href="<?= e(url('admin/boek-verwijderen.php?id=' . $book['id'])) ?>">Verwijderen…</a>
    <?php endif; ?>
  </div>
</form>
<script src="<?= e(asset_url('assets/editor.js')) ?>"></script>
<?php include APP_ROOT . '/app/templates/admin_footer.php'; ?>
