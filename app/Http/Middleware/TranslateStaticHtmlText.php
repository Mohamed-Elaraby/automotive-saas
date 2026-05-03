<?php

namespace App\Http\Middleware;

use Closure;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TranslateStaticHtmlText
{
    /**
     * Translate remaining hardcoded UI text for localized HTML responses.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->shouldTranslate($response)) {
            return $response;
        }

        $translations = trans('autoview');
        $wordTranslations = trans('autowords.words');

        if (! is_array($translations) || $translations === []) {
            return $response;
        }

        if (! is_array($wordTranslations)) {
            $wordTranslations = [];
        }

        $content = (string) $response->getContent();

        if ($content === '') {
            return $response;
        }

        $translated = $this->translateHtml($content, $translations, $wordTranslations);

        if ($translated !== null) {
            $response->setContent($translated);
        }

        return $response;
    }

    private function shouldTranslate(Response $response): bool
    {
        if (app()->getLocale() !== 'ar') {
            return false;
        }

        if (! $response->isSuccessful()) {
            return false;
        }

        $contentType = strtolower((string) $response->headers->get('Content-Type'));

        return str_contains($contentType, 'text/html') || $contentType === '';
    }

    /**
     * @param  array<string, string>  $translations
     * @param  array<string, string>  $wordTranslations
     */
    private function translateHtml(string $html, array $translations, array $wordTranslations): ?string
    {
        [$html, $rawBlocks] = $this->extractRawBlocks($html);

        $document = new DOMDocument('1.0', 'UTF-8');

        $previousErrors = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML(
            mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'),
            LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);

        if (! $loaded) {
            return null;
        }

        $xpath = new DOMXPath($document);

        foreach ($xpath->query('//text()[normalize-space(.) != ""]') ?: [] as $textNode) {
            if (! $this->canTranslateTextNode($textNode)) {
                continue;
            }

            $textNode->nodeValue = $this->translateTextPreservingWhitespace($textNode->nodeValue ?? '', $translations, $wordTranslations);
        }

        foreach ($xpath->query('//*[@placeholder or @title or @aria-label or @data-bs-title or @alt or @value or @onclick or @onsubmit]') ?: [] as $element) {
            if (! $element instanceof DOMElement || $this->hasSkippedAncestor($element)) {
                continue;
            }

            foreach (['placeholder', 'title', 'aria-label', 'data-bs-title', 'alt'] as $attribute) {
                if ($element->hasAttribute($attribute)) {
                    $element->setAttribute($attribute, $this->translate($element->getAttribute($attribute), $translations, $wordTranslations));
                }
            }

            if (
                $element->hasAttribute('value')
                && in_array(strtolower($element->tagName), ['input', 'button'], true)
                    && in_array(strtolower($element->getAttribute('type')), ['button', 'submit', 'reset'], true)
            ) {
                $element->setAttribute('value', $this->translate($element->getAttribute('value'), $translations, $wordTranslations));
            }

            foreach (['onclick', 'onsubmit'] as $attribute) {
                if ($element->hasAttribute($attribute)) {
                    $element->setAttribute($attribute, $this->translateConfirmCalls($element->getAttribute($attribute), $translations, $wordTranslations));
                }
            }
        }

        $translated = $document->saveHTML();

        if (! is_string($translated)) {
            return null;
        }

        $translated = html_entity_decode($translated, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return $this->restoreRawBlocks($translated, $rawBlocks);
    }

    /**
     * @param  array<string, string>  $translations
     * @param  array<string, string>  $wordTranslations
     */
    private function translateTextPreservingWhitespace(string $text, array $translations, array $wordTranslations): string
    {
        $leading = '';
        $trailing = '';

        if (preg_match('/^\s+/u', $text, $match)) {
            $leading = $match[0];
        }

        if (preg_match('/\s+$/u', $text, $match)) {
            $trailing = $match[0];
        }

        $trimmed = trim($text);

        return $leading.$this->translate($trimmed, $translations, $wordTranslations).$trailing;
    }

    /**
     * @param  array<string, string>  $translations
     * @param  array<string, string>  $wordTranslations
     */
    private function translate(string $text, array $translations, array $wordTranslations): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($text));

        if (! is_string($normalized) || $normalized === '') {
            return $text;
        }

        if (isset($translations[$normalized])) {
            return $translations[$normalized];
        }

        return $this->translateKnownPhrases($normalized, $translations) ?? $text;
    }

    /**
     * @param  array<string, string>  $translations
     * @param  array<string, string>  $wordTranslations
     */
    private function translateConfirmCalls(string $attribute, array $translations, array $wordTranslations): string
    {
        $translated = preg_replace_callback('/confirm\((["\'])(.*?)\1\)/u', function (array $matches) use ($translations, $wordTranslations) {
            return 'confirm('.$matches[1].$this->translate($matches[2], $translations, $wordTranslations).$matches[1].')';
        }, $attribute);

        return is_string($translated) ? $translated : $attribute;
    }

    /**
     * @param  array<string, string>  $wordTranslations
     */
    private function translateWords(string $text, array $wordTranslations): ?string
    {
        if ($wordTranslations === [] || mb_strlen($text) > 220 || preg_match('/[{}<>=$]/', $text)) {
            return null;
        }

        $translatedAny = false;
        $translated = preg_replace_callback('/\b[A-Za-z][A-Za-z]+\b/', function (array $matches) use ($wordTranslations, &$translatedAny) {
            $word = $matches[0];
            $lookup = $word;

            if (! isset($wordTranslations[$lookup])) {
                $lookup = ucfirst(strtolower($word));
            }

            if (! isset($wordTranslations[$lookup])) {
                return $word;
            }

            $translatedAny = true;

            return $wordTranslations[$lookup];
        }, $text);

        if (! $translatedAny || ! is_string($translated)) {
            return null;
        }

        return $translated;
    }

    /**
     * Replace known full phrases inside mixed static/dynamic UI text, without
     * falling back to word-by-word translation.
     *
     * @param  array<string, string>  $translations
     */
    private function translateKnownPhrases(string $text, array $translations): ?string
    {
        if (! preg_match('/[A-Za-z]/', $text)) {
            return null;
        }

        $phraseTranslations = array_filter(
            $translations,
            fn (string $translation, string $source): bool => str_contains($source, ' ') && mb_strlen($source) >= 6,
            ARRAY_FILTER_USE_BOTH
        );

        if ($phraseTranslations === []) {
            return null;
        }

        uksort($phraseTranslations, fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        $translated = strtr($text, $phraseTranslations);

        return $translated !== $text ? $translated : null;
    }

    /**
     * DOMDocument's HTML parser can misread JavaScript template literals that
     * contain HTML. Keep raw blocks out of the parser so scripts never leak as
     * visible text after translation.
     *
     * @return array{0: string, 1: array<string, string>}
     */
    private function extractRawBlocks(string $html): array
    {
        $blocks = [];

        $protected = preg_replace_callback(
            '~<(script|style|pre|code|textarea|svg|canvas)\b[^>]*>.*?</\1>~is',
            function (array $matches) use (&$blocks): string {
                $placeholder = '___auto_translate_raw_block_'.count($blocks).'___';
                $blocks[$placeholder] = $matches[0];

                return $placeholder;
            },
            $html
        );

        return [is_string($protected) ? $protected : $html, $blocks];
    }

    /**
     * @param  array<string, string>  $blocks
     */
    private function restoreRawBlocks(string $html, array $blocks): string
    {
        if ($blocks === []) {
            return $html;
        }

        return strtr($html, $blocks);
    }

    private function canTranslateTextNode(DOMNode $node): bool
    {
        return ! $this->hasSkippedAncestor($node);
    }

    private function hasSkippedAncestor(DOMNode $node): bool
    {
        $skipTags = ['script', 'style', 'code', 'pre', 'textarea', 'svg', 'canvas'];
        $current = $node instanceof DOMElement ? $node : $node->parentNode;

        while ($current instanceof DOMElement) {
            if (in_array(strtolower($current->tagName), $skipTags, true)) {
                return true;
            }

            if ($current->hasAttribute('data-no-auto-translate')) {
                return true;
            }

            $current = $current->parentNode;
        }

        return false;
    }
}
