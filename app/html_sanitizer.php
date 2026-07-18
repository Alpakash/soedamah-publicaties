<?php
declare(strict_types=1);

/**
 * Whitelist-sanitizer voor de HTML die de artikel-editor aanlevert.
 * Niet-toegestane tags worden "uitgepakt" (inhoud blijft staan, tag verdwijnt);
 * script/style/object/embed/form/input/button verdwijnen volledig, inclusief inhoud.
 * Alleen aangeroepen bij het opslaan vanuit het beheer — de uitvoer wordt daarna
 * als vertrouwde, kant-en-klare HTML getoond (niet nogmaals ge-escaped).
 */
function sanitize_article_html(string $html): string
{
    if (trim($html) === '') {
        return '';
    }

    $allowedTags = [
        'p' => [], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [],
        'ul' => [], 'ol' => [], 'li' => [],
        'h2' => [], 'h3' => [],
        'blockquote' => [],
        'a' => ['href', 'rel', 'target'],
        'img' => ['src', 'alt'],
        'figure' => [], 'figcaption' => [],
        'iframe' => ['src', 'width', 'height', 'allowfullscreen', 'frameborder', 'loading', 'title'],
    ];
    $stripEntirely = ['script', 'style', 'object', 'embed', 'form', 'input', 'button', 'link', 'meta'];

    $doc = new DOMDocument('1.0', 'UTF-8');
    libxml_use_internal_errors(true);
    $doc->loadHTML(
        '<?xml encoding="UTF-8"?><div>' . $html . '</div>',
        LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED
    );
    libxml_clear_errors();

    $root = $doc->getElementsByTagName('div')->item(0);
    if ($root === null) {
        return '';
    }

    sp_sanitize_children($doc, $root, $allowedTags, $stripEntirely);

    $result = '';
    foreach (iterator_to_array($root->childNodes) as $child) {
        $result .= $doc->saveHTML($child);
    }
    return trim($result);
}

function sp_sanitize_children(DOMDocument $doc, DOMNode $parent, array $allowedTags, array $stripEntirely): void
{
    foreach (iterator_to_array($parent->childNodes) as $node) {
        if ($node instanceof DOMText) {
            continue;
        }
        if (!($node instanceof DOMElement)) {
            // Comments en andere nodetypes die niets met opmaak te maken hebben.
            $parent->removeChild($node);
            continue;
        }

        $tag = strtolower($node->tagName);

        if (in_array($tag, $stripEntirely, true)) {
            $parent->removeChild($node);
            continue;
        }

        if (!array_key_exists($tag, $allowedTags)) {
            sp_sanitize_children($doc, $node, $allowedTags, $stripEntirely);
            while ($node->firstChild) {
                $parent->insertBefore($node->firstChild, $node);
            }
            $parent->removeChild($node);
            continue;
        }

        foreach (iterator_to_array($node->attributes ?? []) as $attr) {
            if (!in_array($attr->name, $allowedTags[$tag], true)) {
                $node->removeAttribute($attr->name);
            }
        }

        if ($tag === 'a') {
            $href = $node->getAttribute('href');
            if ($href === '' || !preg_match('#^(https?://|/)#i', $href)) {
                $node->removeAttribute('href');
            } else {
                $node->setAttribute('rel', 'noopener');
                $node->setAttribute('target', '_blank');
            }
        }

        if ($tag === 'img') {
            $src = $node->getAttribute('src');
            if ($src === '' || !preg_match('#^(https?://|/)#i', $src)) {
                $parent->removeChild($node);
                continue;
            }
        }

        if ($tag === 'iframe') {
            if (!sp_iframe_src_allowed($node)) {
                $parent->removeChild($node);
                continue;
            }
            $node->setAttribute('loading', 'lazy');
            $node->setAttribute('allowfullscreen', 'allowfullscreen');
            $node->setAttribute('frameborder', '0');
        }

        sp_sanitize_children($doc, $node, $allowedTags, $stripEntirely);
    }
}

/** Alleen YouTube- (privacyvriendelijke embed) en Vimeo-video's mogen als iframe blijven staan. */
function sp_iframe_src_allowed(DOMElement $node): bool
{
    $src = $node->getAttribute('src');
    return (bool) preg_match(
        '#^https://(www\.youtube-nocookie\.com/embed/[A-Za-z0-9_-]+|player\.vimeo\.com/video/[0-9]+)#i',
        $src
    );
}
