<?php

namespace App\Bank;

/** Page and normalized box for a finding. Point-space boxes stay in JSON and are not stored here. */
class FindingLocation
{
    public static function columns(array $data): array
    {
        $evidence = is_array($data['evidence'] ?? null) ? $data['evidence'] : [];
        $page = $data['page'] ?? ($evidence['page'] ?? null);
        $version = $data['document_version_id'] ?? ($evidence['document_version_id'] ?? null);
        $region = null;
        if (($data['region_verified'] ?? null) !== false && ($evidence['region_verified'] ?? null) !== false) {
            $region = $data['region'] ?? ($evidence['region'] ?? null);
        }
        $box = self::normalized($region);

        return [
            'source_page' => is_numeric($page) ? (int) $page : null,
            'source_document_version_id' => is_numeric($version) ? (int) $version : null,
            'bbox_x' => $box[0] ?? null,
            'bbox_y' => $box[1] ?? null,
            'bbox_w' => $box[2] ?? null,
            'bbox_h' => $box[3] ?? null,
        ];
    }

    public static function present(array $row): array
    {
        $data = is_array($row['data'] ?? null) ? $row['data'] : [];
        $fromData = self::columns($data);
        $page = is_numeric($row['source_page'] ?? null) ? (int) $row['source_page'] : $fromData['source_page'];
        $version = is_numeric($row['source_document_version_id'] ?? null) ? (int) $row['source_document_version_id'] : $fromData['source_document_version_id'];
        $stored = self::normalized([
            $row['bbox_x'] ?? null,
            $row['bbox_y'] ?? null,
            $row['bbox_w'] ?? null,
            $row['bbox_h'] ?? null,
        ]);
        $box = $stored ?? self::normalized($data['region'] ?? ($data['evidence']['region'] ?? ($data['source']['region'] ?? null)));

        return [
            'page' => $page,
            'document_version_id' => $version,
            'bbox' => $box ? ['x' => $box[0], 'y' => $box[1], 'width' => $box[2], 'height' => $box[3], 'normalized' => true] : null,
        ];
    }

    /** Unique line or word run for $needle, as [x, y, width, height] in 0–1 page space. */
    public static function locate(array $page, string $needle): ?array
    {
        $needle = self::norm($needle);
        if (mb_strlen($needle) < 4) {
            return null;
        }
        $lines = [];
        foreach ($page['lines'] ?? [] as $line) {
            $text = self::norm((string) ($line['text'] ?? ''));
            if ($text !== '' && str_contains($text, $needle)) {
                $lines[] = $line['region'] ?? null;
            }
        }
        if (count($lines) === 1) {
            return self::normalized($lines[0]);
        }
        if (count($lines) > 1) {
            return null;
        }
        $words = array_values($page['words'] ?? []);
        $target = preg_split('/\s+/', $needle) ?: [];
        $target = array_values(array_filter($target, fn ($w) => $w !== ''));
        $n = count($target);
        if ($n === 0 || $words === []) {
            return null;
        }
        $norm = array_map(fn ($w) => self::norm((string) ($w['text'] ?? '')), $words);
        $hits = [];
        for ($i = 0; $i + $n <= count($norm); $i++) {
            $ok = true;
            for ($j = 0; $j < $n; $j++) {
                if ($norm[$i + $j] !== $target[$j]) {
                    $ok = false;
                    break;
                }
            }
            if ($ok) {
                $hits[] = $i;
            }
        }
        if (count($hits) !== 1) {
            return null;
        }

        return self::union(array_slice($words, $hits[0], $n));
    }

    private static function union(array $words): ?array
    {
        $boxes = array_values(array_filter(array_map(fn ($w) => self::normalized($w['region'] ?? null), $words)));
        if ($boxes === []) {
            return null;
        }
        $x = min(array_column($boxes, 0));
        $y = min(array_column($boxes, 1));
        $right = max(array_map(fn ($b) => $b[0] + $b[2], $boxes));
        $bottom = max(array_map(fn ($b) => $b[1] + $b[3], $boxes));

        return self::normalized([$x, $y, $right - $x, $bottom - $y]);
    }

    private static function normalized(mixed $region): ?array
    {
        $tuple = self::tuple($region);
        if (! $tuple) {
            return null;
        }
        [$x, $y, $w, $h] = $tuple;
        if ($x < 0 || $y < 0 || $w <= 0 || $h <= 0 || $x > 1 || $y > 1 || $w > 1 || $h > 1 || $x + $w > 1.001 || $y + $h > 1.001) {
            return null;
        }

        return [round($x, 6), round($y, 6), round($w, 6), round($h, 6)];
    }

    private static function tuple(mixed $region): ?array
    {
        if (is_object($region)) {
            $region = (array) $region;
        }
        if (! is_array($region)) {
            return null;
        }
        if (array_is_list($region)) {
            if (count($region) < 4) {
                return null;
            }
            $values = array_slice(array_values($region), 0, 4);
        } else {
            $width = $region['width'] ?? $region['w'] ?? null;
            $height = $region['height'] ?? $region['h'] ?? null;
            $values = [$region['x'] ?? null, $region['y'] ?? null, $width, $height];
        }
        $numbers = [];
        foreach ($values as $value) {
            if (! is_numeric($value)) {
                return null;
            }
            $numbers[] = (float) $value;
        }

        return $numbers;
    }

    private static function norm(string $s): string
    {
        $s = mb_strtolower(trim($s));

        return preg_replace('/\s+/', ' ', $s) ?? '';
    }
}
