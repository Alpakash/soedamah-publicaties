<?php
require __DIR__ . '/../../app/bootstrap.php';
require APP_ROOT . '/app/auth.php';
require_admin();

const ARTICLE_UPLOAD_DIR = 'uploads/articles';

function admin_article_cover_upload(array $article, array &$errors): string
{
    if (empty($_FILES['cover']) || $_FILES['cover']['error'] === UPLOAD_ERR_NO_FILE) {
        return $article['cover_file'];
    }
    $file = $_FILES['cover'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        if (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            $errors[] = 'De omslagfoto is groter dan de server toestaat.';
        } else {
            $errors[] = 'Upload van de omslagfoto is mislukt (foutcode ' . (int) $file['error'] . '). Probeer het opnieuw.';
        }
        return $article['cover_file'];
    }

    $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($ext, $allowedExt, true)) {
        $errors[] = 'De omslagfoto moet een JPG, PNG of WebP zijn.';
        return $article['cover_file'];
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($file['tmp_name']);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        $errors[] = 'Het bestand voor de omslagfoto lijkt geen geldige afbeelding te zijn.';
        return $article['cover_file'];
    }

    $dir = APP_ROOT . '/public/' . ARTICLE_UPLOAD_DIR;
    ensure_dir($dir);
    $name = ($article['id'] ?? 'nieuw') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        $errors[] = 'De omslagfoto kon niet worden opgeslagen.';
        return $article['cover_file'];
    }
    if ($article['cover_file'] !== '') {
        @unlink($dir . '/' . $article['cover_file']);
    }
    return $name;
}

$id = (int) ($_GET['id'] ?? 0);
$article = $id > 0 ? article_find($id) : null;
if ($id > 0 && $article === null) {
    redirect(url('admin/artikelen.php'));
}

$errors = [];
$saved = isset($_GET['saved']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $errors[] = 'De upload is groter dan de server toestaat. Verhoog post_max_size in Plesk.';
    } else {
        csrf_check();
        $title = trim((string) ($_POST['title'] ?? ''));
        $articleDate = trim((string) ($_POST['article_date'] ?? ''));
        $excerpt = trim((string) ($_POST['excerpt'] ?? ''));
        $bodyHtml = sanitize_article_html((string) ($_POST['body_html'] ?? ''));
        $published = !empty($_POST['published']) ? 1 : 0;

        if ($title === '') {
            $errors[] = 'Vul een titel in.';
        }
        if ($articleDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $articleDate)) {
            $errors[] = 'Vul een geldige datum in.';
        }
        if ($bodyHtml === '') {
            $errors[] = 'Vul de tekst van het artikel in.';
        }
        if ($excerpt === '' && $bodyHtml !== '') {
            $excerpt = article_excerpt_from_body($bodyHtml);
        }

        if (!$errors) {
            $current = $article ?? ['id' => null, 'cover_file' => ''];
            $coverFile = admin_article_cover_upload($current, $errors);
        }

        if (!$errors) {
            if ($article === null) {
                $stmt = db()->prepare(
                    'INSERT INTO articles (slug, title, excerpt, body_html, cover_file, published, article_date, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    article_unique_slug($title), $title, $excerpt, $bodyHtml, $coverFile,
                    $published, $articleDate, now(), now(),
                ]);
                $newId = (int) db()->lastInsertId();
            } else {
                $stmt = db()->prepare(
                    'UPDATE articles SET title = ?, excerpt = ?, body_html = ?, cover_file = ?,
                        published = ?, article_date = ?, updated_at = ?
                     WHERE id = ?'
                );
                $stmt->execute([
                    $title, $excerpt, $bodyHtml, $coverFile, $published, $articleDate, now(), $article['id'],
                ]);
                $newId = (int) $article['id'];
            }
            redirect(url('admin/artikel-bewerken.php?id=' . $newId . '&saved=1'));
        }
        $article = $article !== null ? article_find((int) $article['id']) : null;
    }
}

$isNew = ($article === null);
$formValue = static function (string $field, string $default = '') use ($article) {
    if (isset($_POST[$field])) {
        return (string) $_POST[$field];
    }
    return $article !== null ? (string) $article[$field] : $default;
};
$bodyHtmlValue = $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['body_html'])
    ? sanitize_article_html((string) $_POST['body_html'])
    : (string) ($article['body_html'] ?? '');
$publishedChecked = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? !empty($_POST['published'])
    : ($article !== null && (int) $article['published'] === 1);
$dateValue = $formValue('article_date', gmdate('Y-m-d'));

$pageTitle = $isNew ? 'Nieuw artikel' : 'Bewerken: ' . $article['title'];
include APP_ROOT . '/app/templates/admin_header.php';
?>
<h1><?= $isNew ? 'Nieuw artikel' : 'Artikel bewerken' ?></h1>

<?php if ($saved): ?>
  <p class="alert alert-success">Opgeslagen!
    <?php if ($article !== null && (int) $article['published']): ?>
      <a href="<?= e(url('artikel.php?a=' . $article['slug'])) ?>">Bekijk het artikel.</a>
    <?php endif; ?>
  </p>
<?php endif; ?>
<?php foreach ($errors as $error): ?>
  <p class="alert alert-error"><?= e($error) ?></p>
<?php endforeach; ?>

<form method="post" enctype="multipart/form-data" class="stacked-form form-wide"
      action="<?= e(url('admin/artikel-bewerken.php' . ($article !== null ? '?id=' . $article['id'] : ''))) ?>">
  <?= csrf_field() ?>

  <label for="title">Titel *</label>
  <input type="text" id="title" name="title" required maxlength="200" value="<?= e($formValue('title')) ?>">

  <div class="form-row">
    <div>
      <label for="article_date">Datum *</label>
      <input type="date" id="article_date" name="article_date" required value="<?= e($dateValue) ?>">
    </div>
    <div>
      <label for="cover">Omslagfoto (JPG, PNG of WebP)</label>
      <?php if ($article !== null && $article['cover_file'] !== ''): ?>
        <p class="current-file">Er staat al een omslagfoto.</p>
      <?php endif; ?>
      <input type="file" id="cover" name="cover" accept=".jpg,.jpeg,.png,.webp">
    </div>
  </div>

  <label for="excerpt">Korte samenvatting (optioneel)</label>
  <textarea id="excerpt" name="excerpt" rows="2"
            placeholder="Leeg laten om automatisch een samenvatting van de tekst te gebruiken."><?= e($formValue('excerpt')) ?></textarea>

  <label for="editor-area">Tekst *</label>
  <div class="rich-editor" data-upload-url="<?= e(url('admin/artikel-upload.php')) ?>" data-csrf="<?= e(csrf_token()) ?>">
    <div class="editor-toolbar">
      <button type="button" data-cmd="bold" title="Vet"><strong>V</strong></button>
      <button type="button" data-cmd="italic" title="Cursief"><em>I</em></button>
      <span class="editor-sep"></span>
      <button type="button" data-cmd="formatBlock" data-value="h2" title="Kop">Kop</button>
      <button type="button" data-cmd="formatBlock" data-value="h3" title="Subkop">Subkop</button>
      <button type="button" data-cmd="formatBlock" data-value="p" title="Normale tekst">Tekst</button>
      <button type="button" data-cmd="formatBlock" data-value="blockquote" title="Citaat">Citaat</button>
      <span class="editor-sep"></span>
      <button type="button" data-cmd="insertUnorderedList" title="Opsomming">• Lijst</button>
      <button type="button" data-cmd="insertOrderedList" title="Genummerde lijst">1. Lijst</button>
      <span class="editor-sep"></span>
      <button type="button" data-cmd="createLink" title="Link invoegen">Link</button>
      <button type="button" data-cmd="insertImage" title="Afbeelding invoegen">Afbeelding</button>
      <button type="button" data-cmd="insertVideo" title="YouTube/Vimeo-video invoegen">Video</button>
    </div>
    <div id="editor-area" class="editor-area" contenteditable="true"><?= $bodyHtmlValue ?></div>
    <input type="file" class="editor-file-input" accept="image/jpeg,image/png,image/webp" hidden>
    <textarea name="body_html" class="editor-hidden-field" hidden><?= e($bodyHtmlValue) ?></textarea>
  </div>
  <p class="field-hint">Afbeeldingen en video's die je hier invoegt, komen bij de tekst zelf te staan.</p>

  <label class="checkbox-line">
    <input type="checkbox" name="published" value="1" <?= $publishedChecked ? 'checked' : '' ?>>
    Zichtbaar op de site
  </label>

  <div class="form-actions">
    <button type="submit" class="btn btn-primary">Opslaan</button>
    <a class="btn btn-secondary" href="<?= e(url('admin/artikelen.php')) ?>">Terug naar overzicht</a>
    <?php if ($article !== null): ?>
      <a class="btn btn-danger-link" href="<?= e(url('admin/artikel-verwijderen.php?id=' . $article['id'])) ?>">Verwijderen…</a>
    <?php endif; ?>
  </div>
</form>
<script src="<?= e(asset_url('assets/editor.js')) ?>"></script>
<?php include APP_ROOT . '/app/templates/admin_footer.php'; ?>
