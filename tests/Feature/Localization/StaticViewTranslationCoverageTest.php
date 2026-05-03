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

        $uncovered = $this->findUncoveredStaticLabels($roots, true);

        $this->assertSame([], array_slice($uncovered, 0, 30, true));
    }

    public function test_all_view_files_do_not_have_uncovered_static_ui_labels(): void
    {
        $uncovered = $this->findUncoveredStaticLabels([resource_path('views')]);

        $this->assertSame([], array_slice($uncovered, 0, 30, true));
    }

    /**
     * @param  array<int, string>  $roots
     * @return array<string, string>
     */
    private function findUncoveredStaticLabels(array $roots, bool $skipLegacyModalFixtures = false): array
    {
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

                if ($skipLegacyModalFixtures && str_ends_with($file->getPathname(), 'components/modal-popup.blade.php')) {
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

        return $uncovered;
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

        if (preg_match('/^[a-z_]+\)+$/', $text)) {
            return true;
        }

        if (preg_match('/^[a-z]+(?:-[a-z]+)+$/', $text)) {
            return true;
        }

        if (preg_match('/^[a-zA-Z_]+\)\.\'\'$/', $text)) {
            return true;
        }

        if (str_contains($text, ").''") || str_starts_with($text, 'name).')) {
            return true;
        }

        if (preg_match('/^[A-Za-z0-9_]+:\s*".*"$/', $text)) {
            return true;
        }

        if (preg_match('/^No of Invoices\s*:\s*\d+$/', $text)) {
            return true;
        }

        if (preg_match('/^Total Order\s*:\s*\d+$/', $text)) {
            return true;
        }

        if (preg_match('/^\d+\s+Users$/', $text)) {
            return true;
        }

        if (preg_match('/^Version\s*:\s*[A-Za-z0-9.]+$/', $text) || preg_match('/^\d+K$/', $text) || preg_match('/^[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/', $text)) {
            return true;
        }

        if (preg_match('/^(INV|INC|PR|QU|PO|ABC|REF)\s*[A-Z0-9#-]+$/', $text) || preg_match('/^(PAYIN|PAYOUT)\s+-?\d+$/', $text)) {
            return true;
        }

        if (preg_match('/^(PRO|TXN|PUR|REF|DN|CN|PAY|QUO|EXP)\s+-$/', $text)) {
            return true;
        }

        if (preg_match('/^[A-Z]{3}\s+-\s+\d{8,}$/', $text)) {
            return true;
        }

        if (preg_match('/^(A|B|AB|O)[+-]$/', $text)) {
            return true;
        }

        if (preg_match('/^(IGST|CGST|SGST)\s+\d+(\.\d+)?%$/', $text)) {
            return true;
        }

        if (preg_match('/\d+.*\b(Street|Road|Drive|City|USA|Ltd|Pvt)\b/i', $text)) {
            return true;
        }

        if (preg_match('/\b[A-Z]{2}\d{2}\s+\d[A-Z]{2}\b|GSTIN\s*:\s*[A-Z0-9]+/', $text)) {
            return true;
        }

        if (preg_match('/\b\d{8,}\b.*\b(Bank|Finance|Union)\b/', $text)) {
            return true;
        }

        if (preg_match('/\bPvt\.?\s*Ltd\.?\b/', $text)) {
            return true;
        }

        if (str_contains($text, 'domainname.com') || preg_match('/#SHP\d+/', $text)) {
            return true;
        }

        if (preg_match('/\b\d+GB\b|\b\d+mm\b|OPPO\s+A\d+/i', $text)) {
            return true;
        }

        if (preg_match('/^[A-Z]{2,}\s+Bank$/', $text)) {
            return true;
        }

        if (preg_match('/\b(lorem|ipsum|amet|consectetur|adipiscing|facilisis|phasellus|sodales|ultricies|nulla|dapibus|porta|molestie|pretium|aliquet|volutpat|aliquam|vestibulum|laoreet|porttitor|sem|euismod|aenean|posuere|tortor|cursus|feugiat|blandit|nunc|cras|justo|odio|nemo|enim|ipsam|voluptatem|aspernatur|neque|porro|quisquam|dolorem|autem|iure|harum|quidem|rerum|temporibus|quibusdam|officiis)\b/i', $text)) {
            return true;
        }

        if (str_starts_with($text, 'Address :') && preg_match('/\d+/', $text)) {
            return true;
        }

        if (str_starts_with($text, 'http://') || str_starts_with($text, 'https://')) {
            return true;
        }

        if (preg_match('/^\d+\s+Km$/', $text) || preg_match('/^[A-Z]{2}-\d+$/', $text)) {
            return true;
        }

        if (preg_match('/^[A-Za-z]+\s+[A-Z]{2,4}\s+\d{1,2}:\d{2}[AP]M$/', $text) || preg_match('/^[A-Za-z]+\s+-\s+[A-Z]{1,4}\s+\d{1,2}[-:]\d{2}/', $text) || preg_match('/^[A-Za-z]+\s+-\s+[A-Z]\s+\d{2}-\d{2}-\d{2}$/', $text)) {
            return true;
        }

        if (preg_match('/^Air\s+[A-Za-z]+\s+[A-Z0-9-]+\s+[A-Za-z]+-\s+[A-Za-z]+\s+[A-Za-z]+$/', $text)) {
            return true;
        }

        if (preg_match('/^\(?[+-]?\d{1,2}:\d{2}\)?\s+GMT|^\(GMT\s+[+-]\d{1,2}:\d{2}\)/', $text)) {
            return true;
        }

        if (preg_match('/^\d+\s+.*\b(High|United|HP\d+)/', $text)) {
            return true;
        }

        if (preg_match('/^Date:\s+\d{1,2}\s+[A-Za-z]{3,9}\s+\d{4}/', $text)) {
            return true;
        }

        if (preg_match('/^\d{1,2}\s+[A-Za-z]{3,9}\s+\d{4}\s+at\s+\d/', $text)) {
            return true;
        }

        if (preg_match('/^\d{4}\s+(Spring|Summer|Autumn|Winter)$/', $text) || preg_match('/^\d{2}[A-Za-z]{3}\s+\d{4}\s+To\s+\d{1,2}\s+[A-Za-z]{3}\s+\d{4}$/', $text)) {
            return true;
        }

        if (preg_match('/^\d{1,2}\s+[A-Za-z]{3,9}\s+\d{2,5}(,\s+\d{1,2}:\d{2}\s+[AP]M)?$/', $text)) {
            return true;
        }

        if (preg_match('/^\d{4}\s+[A-Za-z]{3,9}\s+\d{1,2}$/', $text)) {
            return true;
        }

        if (preg_match('/^[A-Za-z]+,\s+\d{1,2}\s+[A-Za-z]{3,9}\s+\d{4}$/', $text) || preg_match('/^\d{1,2}:\d{2}(:\d{2})?\s+[AP]M$/', $text)) {
            return true;
        }

        if (preg_match('/^\d{1,2}\s+[A-Za-z]{3,9}$/', $text)) {
            return true;
        }

        if (preg_match('/^[A-Za-z]+(?:&?[A-Z][A-Za-z]+)+$/', $text)) {
            return true;
        }

        if (preg_match('/^\d{1,2}\s+[A-Za-z]{3,9}\s+\d{2,4}\s+-\s+\d{1,2}\s+[A-Za-z]{3,9}\s+\d{2,4}$/', $text)) {
            return true;
        }

        if (preg_match('/^[A-Z]{2,4}$/', $text)) {
            return true;
        }

        if (preg_match('/^[A-Za-z]+(?:\s+[A-Za-z]+){0,3}$/', $text) && $this->looksLikeDemoName($text)) {
            return true;
        }

        if (preg_match('/^[A-Z][a-z]+\s+[A-Z]\.\s+[A-Z][a-z]+$/', $text)) {
            return true;
        }

        if (preg_match('/^[A-Z][A-Za-z]+\s+[A-Z][A-Za-z]+,\s+[A-Z][A-Za-z]+\s+[A-Z][A-Za-z]+$/', $text)) {
            return true;
        }

        if (preg_match('/^[A-Za-z]+\s+[A-Za-z]+,\s+[A-Z]{2}$/', $text) || preg_match('/^[A-Za-z]+,\s+\d+,$/', $text)) {
            return true;
        }

        if ($text === 'United States of America') {
            return true;
        }

        if (preg_match('/^(Yesterday|Today)\s+\d{1,2}:\d{2}\s+[AP]M$/', $text)) {
            return true;
        }

        if (preg_match('/^[A-Za-z]{3}\s+\d{1,2}\s+\d{4}$/', $text)) {
            return true;
        }

        if (preg_match('/^[A-Za-z]{3}\s+\d{4}$/', $text)) {
            return true;
        }

        if (preg_match('/^(January|February|March|April|May|June|July|August|September|October|November|December)$/', $text)) {
            return true;
        }

        if (preg_match('/^[A-Za-z]{3}\s+\d{1,2},\s+\d{4}$/', $text)) {
            return true;
        }

        if (preg_match('/^[A-Za-z]{3}\s+\d{1,2},\d{4}$/', $text)) {
            return true;
        }

        if (preg_match('/^\d{1,2}\s+[A-Za-z]{3},\d{4}\s+to\s+\d{1,2}\s+[A-Za-z]{3},\d{4}$/', $text) || preg_match('/^\d{1,2}:\d{2}\s+[AP]M\s+to\s+\d{1,2}:\d{2}\s+[AP]M$/', $text)) {
            return true;
        }

        if (preg_match('/^Last Accessed on\s+\d{1,2}\s+[A-Za-z]{3}\s+\d{4}/', $text)) {
            return true;
        }

        if (preg_match('/^(Visa|Mastercard)\s+[\*•]+\s*\d+$/u', $text) || preg_match('/^\d+(\.\d+)?\s+MB$/', $text) || preg_match('/^[A-Za-z0-9]+?\.(png|jpg|zip|pdf)$/i', $text)) {
            return true;
        }

        if (preg_match('/^\d{1,2}-[A-Za-z]{3,9}-\d{4}$/', $text)) {
            return true;
        }

        if (preg_match('/^\d+\s+Years\s+Old\s+-\s+\d{1,2}\s+[A-Za-z]{3,9}\s+\d{4}$/', $text)) {
            return true;
        }

        if (preg_match('/^[A-Za-z]{2}-\d+$/', $text)) {
            return true;
        }

        if (preg_match('/^[A-Z]+(?:-[A-Z0-9]+)+$/', $text)) {
            return true;
        }

        if (str_starts_with($text, '#') || preg_match('/^\d+K\s+Likes$/', $text)) {
            return true;
        }

        if ($text === 'T3 Tech') {
            return true;
        }

        if (preg_match('/^About\s+\d+\s+(hr|hrs|min|mins)\s+ago$/', $text)) {
            return true;
        }

        if (preg_match('/^[A-Za-z]+(?:\s+[A-Za-z]+){0,3}$/', $text) && $this->looksLikeDemoPlace($text)) {
            return true;
        }

        if (preg_match('/^[A-Za-z]+(?:\s+[A-Za-z]+){0,3}$/', $text) && $this->looksLikeDemoBrand($text)) {
            return true;
        }

        if (preg_match('/\b(XPS|T-shirt|AirPods|Ultraboost|Dyson|Samsung|Nike|Levi|Spectre|CeraVe|Giro|Synthe|MIPS|OnePlus|Dreamstechnologies)/', $text)) {
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
            'Faith', 'Marie', 'Mitchel', 'Johnson', 'James', 'Oliver', 'Scott',
            'Ava', 'Louise', 'Anne', 'Benjamin', 'Thomas', 'Jane', 'Elizabeth',
            'Clark', 'White', 'Carter', 'Harris', 'Anderson', 'Lewis', 'Martinez',
            'Brown', 'Parker', 'Thompson', 'Robinson', 'Smith', 'Jafna', 'Cremson',
            'Roberson', 'Tiger', 'Nixon', 'Garrett', 'Winters', 'Ashton', 'Cox',
            'Cedric', 'Kelly',
            'Airi', 'Satou', 'Brielle', 'Williamson', 'Herrod', 'Chandler',
            'Rhona', 'Davidson', 'Colleen', 'Hurst', 'Sonya', 'Frost', 'Jena',
            'Gaines', 'Quinn', 'Flynn', 'Charde', 'Marshall', 'Haley',
            'Kennedy', 'Tatyana', 'Fitzpatrick', 'Silva', 'Paul', 'Byrd',
            'Gloria', 'Little', 'Bradley', 'Greer', 'Dai', 'Rios', 'Jenette',
            'Caldwell', 'Yuri', 'Berry', 'Caesar', 'Vance', 'Wilder',
            'Sidney', 'Angelica', 'Ramos', 'Gavin', 'Joyce', 'Jennifer',
            'Chang', 'Brenden', 'Wagner', 'Fiona', 'Green', 'Shou', 'Itou',
            'Richards', 'Mark', 'Salween', 'Chen',
            'Forest', 'Kroch', 'Townsend', 'Seary',
            'Margaretta', 'Worvell', 'Braun', 'Tucker', 'Joanne', 'Conner',
            'Lowell', 'H', 'Dominguez',
            'Doe', 'Brian', 'Villalobos', 'Harvey', 'Doglas', 'Martini',
            'Linda', 'Ray', 'Elliot', 'Murray', 'Rebecca', 'Smtih', 'Connie',
            'Waters',
            'Lori', 'Broaddus', 'Williams', 'Johan', 'Stephan', 'Peralt',
            'Gertrude', 'Bowie', 'Hong',
            'Sean', 'Hill', 'Drake', 'Angela', 'Arman', 'Janes', 'Justin',
            'Lapointe',
            'Simons', 'Steiger', 'Darin', 'Mabry', 'Neiman', 'Samuel',
            'Donatte', 'Alberto', 'Alleo', 'Ernesto', 'Janetts',
            'George',
            'Sarah', 'Michelle', 'Patrick', 'Lauren', 'Kelton', 'Jessica',
            'Renee', 'Ryan', 'Christopher', 'Abigail', 'Harper', 'Madison',
            'Brooke', 'Victoria', 'Celeste', 'Nathaniel', 'Blake', 'Natalie',
            'Paige', 'Claire',
            'Richard', 'Jason', 'Heier', 'Headrick', 'Frank', 'Hoffman',
            'Butler',
            'Mary', 'Moe', 'Dooley', 'Defaultson', 'Refs', 'Activeson',
            'Anna', 'Pitt', 'Tabitha', 'Burrell', 'Meridian', 'Alice', 'Ernst',
            'Debbie', 'Evans', 'Borger', 'Wallin', 'Francis', 'Zavala', 'Fonda',
            'Frazee', 'Harshrath', 'Zozo', 'Hadid', 'Martiana', 'Alex', 'Carey',
            'Thornton', 'Larry', 'Bird', 'Erica', 'Zelensky', 'Kim', 'Jong',
            'Obana', 'Karizma', 'Joanna', 'Kara', 'Kova', 'Donald', 'Trimb',
            'Gaethje', 'Cruise', 'Charanjeep', 'Samantha', 'Julie', 'Simon',
            'Cohen', 'Mayor', 'Garfield', 'Cowel', 'Mirinda', 'Hers', 'Otto',
            'Jacob',
            'Donoghue', 'Susan', 'Fox',
            'Mercy', 'Druman', 'Jackson', 'Peterson', 'Raymond', 'Rowley',
            'Carl', 'Minerva', 'Rameriz', 'Lamon', 'Patricia',
            'Joslyn', 'Marsha', 'Betts', 'Jude', 'Bates', 'Fralick', 'Robison',
            'Elwis', 'Mathew',
            'Olsen', 'Lesley', 'Grauer', 'Eugene', 'Sikora', 'Fassett',
            'Fletcher', 'Tyron', 'Derby', 'Davis', 'Denton', 'Cruz',
            'Micle', 'Lapoint', 'Joe', 'Kevin', 'Alley', 'Zimmer', 'Emly',
            'Reachel',
            'Jordan', 'Dine', 'Jack', 'Rias',
            'Crowley',
            'Law',
        ];

        foreach (explode(' ', $text) as $part) {
            if (! in_array(trim($part, '.-'), $nameParts, true)) {
                return false;
            }
        }

        return true;
    }

    private function looksLikeDemoPlace(string $text): bool
    {
        $placeParts = [
            'Toronto', 'Munich', 'Paris', 'Mumbai', 'Lombardy', 'Milan', 'Sydney',
            'Guangdong', 'Guangzhou', 'Sao', 'Paulo', 'Istanbul', 'Russia',
            'Moscow', 'Oblast', 'New', 'York', 'London', 'Texas', 'Florida',
            'Fresno', 'Francisco', 'United', 'States', 'Kingdom', 'Spain',
            'Edinburgh', 'Tokyo', 'San', 'Singapore', 'King', 'Chicago',
            'Alaska', 'Mexico', 'Tasmania', 'Anchorage', 'Tijuana', 'Hobart',
            'Ukrain', 'Isreal', 'Belgium',
            'Seattle', 'Knoxville', 'Herndon', 'Toledo', 'Eagan',
            'Vegas', 'America',
            'US',
            'Hawaii', 'Nevada', 'Oregon', 'Washington', 'Arizona', 'Colorado',
            'Idaho', 'Montana', 'Nebraska', 'North', 'Dakota', 'Utah', 'Wyoming',
            'Alabama', 'Arkansas', 'Illinois', 'Iowa', 'Kansas', 'Kentucky',
            'Louisiana', 'Minnesota', 'Mississippi', 'Missouri', 'Oklahoma',
            'South', 'Tennessee', 'Wisconsin', 'Connecticut',
            'Delaware', 'Georgia', 'Indiana', 'Maine', 'Maryland',
            'Massachusetts', 'Michigan', 'Hampshire', 'Jersey', 'Carolina',
            'Ohio', 'Pennsylvania', 'Rhode', 'Island', 'Vermont', 'Virginia',
            'West', 'Manchester', 'Liverpool', 'Lyon', 'Marseille', 'Hamburg',
            'Berlin', 'Madrid', 'Barcelona', 'Malaga',
            'Montreal', 'Vancouver',
            'Phoenix', 'Dallas', 'Moracco',
        ];

        foreach (explode(' ', $text) as $part) {
            if (! in_array(trim($part, '.-'), $placeParts, true)) {
                return false;
            }
        }

        return true;
    }

    private function looksLikeDemoBrand(string $text): bool
    {
        $brandParts = [
            'Dell', 'XPS', 'Nike', 'Tech', 'Bazaar', 'Quick', 'Cart', 'Harvest',
            'Basket', 'Elite', 'Mart', 'Prime', 'Trend', 'Hive', 'Crafters', 'Fresh',
            'Nest', 'Gizmo', 'Dream', 'Space', 'Mega', 'Decor', 'Ease', 'Electro',
            'World', 'Urban', 'Home', 'Doccure', 'Dreams', 'Tour', 'Gigs',
            'Rent', 'Sports', 'Estate', 'LMS', 'Truelysell', 'POS', 'Bus',
            'Line', 'Cineplex', 'Trello', 'Dropbox', 'Discord', 'Asana', 'YES',
            'RS', 'Puram', 'BofA', 'TrueAI', 'Technologies',
            'Dribble', 'T3', 'Tech', 'Fstoppers', 'Evernote',
            'Instrument', 'Sans', 'Nunito', 'Poppins',
        ];

        foreach (explode(' ', $text) as $part) {
            if (! in_array(trim($part, '.-'), $brandParts, true)) {
                return false;
            }
        }

        return true;
    }
}
