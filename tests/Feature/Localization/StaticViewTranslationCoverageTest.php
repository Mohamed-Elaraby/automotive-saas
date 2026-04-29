<?php

namespace Tests\Feature\Localization;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class StaticViewTranslationCoverageTest extends TestCase
{
    public function test_active_view_roots_do_not_have_uncovered_static_ui_labels(): void
    {
        $roots = [
            resource_path('views/admin'),
            resource_path('views/automotive/admin'),
            resource_path('views/automotive/portal'),
            resource_path('views/auth'),
            resource_path('views/shared'),
        ];

        $exactTranslations = require lang_path('ar/autoview.php');
        $wordTranslations = (require lang_path('ar/autowords.php'))['words'];
        $uncovered = [];

        foreach ($roots as $root) {
            if (! is_dir($root)) {
                continue;
            }

            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

            foreach ($files as $file) {
                if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                    continue;
                }

                if (str_ends_with($file->getPathname(), 'components/modal-popup.blade.php')) {
                    continue;
                }

                preg_match_all('/>\s*([^<>]*[A-Za-z][^<>]*)\s*</u', file_get_contents($file->getPathname()), $matches);

                foreach ($matches[1] as $text) {
                    $normalized = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

                    if ($normalized === '' || $this->shouldIgnore($normalized)) {
                        continue;
                    }

                    if (! $this->isCovered($normalized, $exactTranslations, $wordTranslations)) {
                        $uncovered[$normalized] = $file->getPathname();
                    }
                }
            }
        }

        $this->assertSame([], array_slice($uncovered, 0, 30, true));
    }

    /**
     * @param  array<string, string>  $exactTranslations
     * @param  array<string, string>  $wordTranslations
     */
    private function isCovered(string $text, array $exactTranslations, array $wordTranslations): bool
    {
        if (isset($exactTranslations[$text])) {
            return true;
        }

        if (mb_strlen($text) > 80 || preg_match('/[{}<>=$]/', $text)) {
            return true;
        }

        preg_match_all('/\b[A-Z][A-Za-z]+\b/', $text, $matches);

        if (($matches[0] ?? []) === []) {
            return true;
        }

        foreach ($matches[0] as $word) {
            if (! isset($wordTranslations[$word])) {
                return false;
            }
        }

        return true;
    }

    private function shouldIgnore(string $text): bool
    {
        if (str_contains($text, '{{') || str_contains($text, '@')) {
            return true;
        }

        if (preg_match('/[a-zA-Z_]+\s*\(|\)\s*}}|=>|::|->|\$[A-Za-z_]/', $text)) {
            return true;
        }

        if (preg_match('/^(INV|INC|PR|QU|PO|ABC)[A-Z0-9#-]+$/', $text) || preg_match('/^(PAYIN|PAYOUT)\s+-?\d+$/', $text)) {
            return true;
        }

        if (preg_match('/^\d{1,2}\s+[A-Za-z]{3,9}\s+\d{2,4}(,\s+\d{1,2}:\d{2}\s+[AP]M)?$/', $text)) {
            return true;
        }

        if (preg_match('/^[A-Z]{2,4}$/', $text)) {
            return true;
        }

        if (preg_match('/^[A-Za-z]+(?:\s+[A-Za-z]+){0,3}$/', $text) && $this->looksLikeDemoName($text)) {
            return true;
        }

        return false;
    }

    private function looksLikeDemoName(string $text): bool
    {
        $nameParts = [
            'Sophia', 'Emily', 'John', 'Michael', 'Olivia', 'David', 'Daniel', 'Charlotte',
            'William', 'Emma', 'Mia', 'Amelia', 'Walter', 'Robert', 'Ethan', 'Liam',
            'Isabella', 'Sophie', 'Cameron', 'Doris', 'Rufana', 'Anthony', 'Noah',
            'Andrew', 'Kathleen', 'Gifford', 'Adrian', 'Ted', 'Grace', 'Rose',
            'Faith', 'Marie', 'Mitchel', 'Johnson', 'James',
        ];

        foreach (explode(' ', $text) as $part) {
            if (! in_array(trim($part, '.-'), $nameParts, true)) {
                return false;
            }
        }

        return true;
    }
}
