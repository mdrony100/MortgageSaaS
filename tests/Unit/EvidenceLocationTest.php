<?php

namespace Tests\Unit;

use App\Bank\EvidenceLocation;
use PHPUnit\Framework\TestCase;

class EvidenceLocationTest extends TestCase
{
    public function test_unique_word_run_returns_one_box_and_repeats_do_not(): void
    {
        $words = [
            ['text' => 'Page', 'region' => [0.10, 0.05, 0.08, 0.02]],
            ['text' => '2', 'region' => [0.19, 0.05, 0.03, 0.02]],
            ['text' => 'Merchant', 'region' => [0.10, 0.40, 0.12, 0.02]],
            ['text' => 'sales', 'region' => [0.23, 0.40, 0.08, 0.02]],
            ['text' => 'Merchant', 'region' => [0.10, 0.70, 0.12, 0.02]],
            ['text' => 'sales', 'region' => [0.23, 0.70, 0.08, 0.02]],
        ];
        $page = EvidenceLocation::matchWords($words, 'Page 2');
        $this->assertNotNull($page);
        $this->assertEqualsWithDelta(0.10, $page[0], 0.0001);
        $this->assertEqualsWithDelta(0.05, $page[1], 0.0001);
        $this->assertEqualsWithDelta(0.12, $page[2], 0.0001);
        $this->assertEqualsWithDelta(0.02, $page[3], 0.0001);
        $this->assertNull(EvidenceLocation::matchWords($words, 'Merchant sales'));
        $this->assertNull(EvidenceLocation::matchWords($words, ''));
    }

    public function test_columns_keep_a_verified_box_and_drop_an_unverified_one(): void
    {
        $verified = EvidenceLocation::columns([
            'page' => 3,
            'region' => [0.2, 0.3, 0.4, 0.1],
            'region_verified' => true,
        ]);
        $this->assertSame(3, $verified['source_page']);
        $this->assertTrue($verified['bbox_verified']);
        $this->assertSame(['x' => 0.2, 'y' => 0.3, 'width' => 0.4, 'height' => 0.1], json_decode($verified['source_bbox'], true));

        $pageOnly = EvidenceLocation::columns([
            'evidence' => ['page' => 4, 'region' => [0.2, 0.3, 0.4, 0.1], 'region_verified' => false],
        ]);
        $this->assertSame(4, $pageOnly['source_page']);
        $this->assertFalse($pageOnly['bbox_verified']);
        $this->assertNull($pageOnly['source_bbox']);
    }

    public function test_pin_writes_a_box_only_when_the_words_match_once(): void
    {
        $pages = ['9:2' => [['text' => 'NSF', 'region' => [0.4, 0.5, 0.05, 0.02]]]];
        $hit = EvidenceLocation::pin(['document_version_id' => 9, 'page' => 2, 'locate' => 'NSF'], $pages);
        $this->assertTrue($hit['region_verified']);
        $this->assertEqualsWithDelta(0.4, $hit['region'][0], 0.0001);
        $this->assertEqualsWithDelta(0.5, $hit['region'][1], 0.0001);
        $this->assertEqualsWithDelta(0.05, $hit['region'][2], 0.0001);
        $this->assertEqualsWithDelta(0.02, $hit['region'][3], 0.0001);
        $this->assertArrayNotHasKey('locate', $hit);
        $miss = EvidenceLocation::pin(['document_version_id' => 9, 'page' => 2, 'locate' => 'not on the page'], $pages);
        $this->assertArrayNotHasKey('region_verified', $miss);
    }
}
