<?php

namespace Tests\Unit\Automotive\Maintenance;

use App\Services\Automotive\Maintenance\VinOcrService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class VinOcrServiceTest extends TestCase
{
    public function test_ocr_parsing_extracts_standard_seventeen_character_vin(): void
    {
        $analysis = $this->service()->analyzeText('VIN: JTDKB20U777777777');

        $this->assertSame('detected', $analysis['ocr_status']);
        $this->assertSame('JTDKB20U777777777', $analysis['extracted_vin']);
        $this->assertSame('JTDKB20U777777777', $analysis['normalized_vin']);
        $this->assertContains('JTDKB20U777777777', $analysis['candidates']);
        $this->assertGreaterThanOrEqual(85, $analysis['confidence_score']);
    }

    public function test_ocr_parsing_supports_chassis_like_values(): void
    {
        $analysis = $this->service()->analyzeText('Chassis AAJ3030150S100354');

        $this->assertSame('detected', $analysis['ocr_status']);
        $this->assertSame('AAJ3030150S100354', $analysis['extracted_vin']);
        $this->assertSame('AAJ3030150S100354', $analysis['normalized_vin']);
        $this->assertContains('AAJ3030150S100354', $analysis['candidates']);
    }

    public function test_ocr_parsing_rejects_label_contamination_candidates(): void
    {
        $vin = $this->service()->analyzeText('VIN JTDKB20U777777777');
        $chassis = $this->service()->analyzeText('Chassis AAJ3030150S100354');

        $this->assertSame('JTDKB20U777777777', $vin['extracted_vin']);
        $this->assertNotContains('NJTDKB20U77777777', $vin['candidates']);
        $this->assertSame('AAJ3030150S100354', $chassis['extracted_vin']);
        $this->assertNotContains('SAAJ3030150S10035', $chassis['candidates']);
    }

    public function test_ocr_parsing_ignores_spaces_and_dashes(): void
    {
        $spaced = $this->service()->analyzeText('AAJ 3030150 S100354');
        $dashed = $this->service()->analyzeText('AAJ-3030150-S100354');

        $this->assertSame('AAJ3030150S100354', $spaced['extracted_vin']);
        $this->assertSame('AAJ3030150S100354', $dashed['extracted_vin']);
    }

    public function test_ocr_parsing_does_not_rewrite_valid_letters_in_primary_output(): void
    {
        $analysis = $this->service()->analyzeText('AAJ3030150S100354 BSI');

        $this->assertSame('AAJ3030150S100354', $analysis['extracted_vin']);
        $this->assertStringContainsString('S', $analysis['extracted_vin']);
        $this->assertStringNotContainsString('AAJ30301505100354', $analysis['extracted_vin']);
    }

    public function test_ocr_unavailable_returns_manual_fallback_status(): void
    {
        $analysis = $this->service()->analyzeText(null);

        $this->assertFalse($analysis['ocr_available']);
        $this->assertSame('unavailable', $analysis['ocr_status']);
        $this->assertNull($analysis['extracted_vin']);
        $this->assertSame([], $analysis['candidates']);
    }

    public function test_ocr_not_detected_does_not_block_manual_flow(): void
    {
        $analysis = $this->service()->analyzeText('unclear dirty glass glare');

        $this->assertTrue($analysis['ocr_available']);
        $this->assertSame('not_detected', $analysis['ocr_status']);
        $this->assertNull($analysis['extracted_vin']);
        $this->assertSame([], $analysis['candidates']);
    }

    public function test_low_quality_ocr_noise_is_not_auto_suggested(): void
    {
        foreach (['DASH GLASS 123', 'WIND SCREEN REFLECTION', 'ABC DEF GHI 123'] as $text) {
            $analysis = $this->service()->analyzeText($text);

            $this->assertContains($analysis['ocr_status'], ['not_detected', 'low_confidence']);
            $this->assertNull($analysis['extracted_vin']);
            $this->assertSame([], $analysis['candidates']);
        }
    }

    protected function service(): VinOcrService
    {
        return (new ReflectionClass(VinOcrService::class))->newInstanceWithoutConstructor();
    }
}
