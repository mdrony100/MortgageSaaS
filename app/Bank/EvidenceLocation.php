<?php

namespace App\Bank;

/** Page and bounding-box locations for findings. Coordinates are normalized 0–1. */
class EvidenceLocation
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{source_page: ?int, source_bbox: ?string, bbox_verified: bool}
     */
    public static function columns(array $data): array
    {
        $source = is_array($data['evidence'] ?? null) ? $data['evidence'] : $data;
        $page = $source['page'] ?? $data['page'] ?? null;
        $verified = (bool) ($source['region_verified'] ?? $data['region_verified'] ?? false);
        $bbox = $verified ? self::normalize($source['region'] ?? $data['region'] ?? null) : null;
        if ($bbox === null) {
            $verified = false;
        }

        return [
            'source_page' => is_numeric($page) ? max(1, (int) $page) : null,
            'source_bbox' => $bbox === null ? null : json_encode($bbox, JSON_THROW_ON_ERROR),
            'bbox_verified' => $verified,
        ];
    }

    /**
     * Attach a verified box when the needle is a unique run of words on the named page.
     *
     * @param  array<string, mixed>  $evidence
     * @param  array<string, array<int, array<string, mixed>>>  $pageWords
     * @return array<string, mixed>
     */
    public static function pin(array $evidence, array $pageWords): array
    {
        $needle = $evidence['locate'] ?? null;
        unset($evidence['locate']);
        if (! is_string($needle) || $needle === '' || ! empty($evidence['region_verified'])) {
            return $evidence;
        }
        $key = ($evidence['document_version_id'] ?? '').':'.($evidence['page'] ?? '');
        $box = self::matchWords($pageWords[$key] ?? [], $needle);
        if ($box) {
            $evidence['region'] = $box;
            $evidence['region_verified'] = true;
        }

        return $evidence;
    }

    /**
     * @param  array<int, array<string, mixed>>  $words
     * @return array{0: float, 1: float, 2: float, 3: float}|null
     */
    public static function matchWords(array $words, string $needle): ?array
    {
        $needle = trim(preg_replace('/\s+/', ' ', $needle) ?? '');
        if ($needle === '' || $words === []) {
            return null;
        }
        $hits = [];
        $limit = count($words);
        for ($i = 0; $i < $limit; $i++) {
            $text = '';
            $boxes = [];
            for ($j = $i; $j < $limit && $j < $i + 48; $j++) {
                $token = trim((string) ($words[$j]['text'] ?? ''));
                if ($token === '') {
                    continue;
                }
                $text = $text === '' ? $token : $text.' '.$token;
                $box = self::normalize($words[$j]['region'] ?? null);
                if ($box) {
                    $boxes[] = [$box['x'], $box['y'], $box['width'], $box['height']];
                }
                if (mb_strlen($text) > mb_strlen($needle) + 12) {
                    break;
                }
                if ($text === $needle && $boxes !== []) {
                    $hits[] = self::union($boxes);
                }
            }
        }

        return count($hits) === 1 ? $hits[0] : null;
    }

    public static function normalize(mixed $region): ?array
    {
        if (! is_array($region)) {
            return null;
        }
        if (array_is_list($region)) {
            if (count($region) !== 4) {
                return null;
            }
            $region = ['x' => $region[0], 'y' => $region[1], 'width' => $region[2], 'height' => $region[3]];
        }
        foreach (['x', 'y', 'width', 'height'] as $key) {
            if (! isset($region[$key]) || ! is_numeric($region[$key])) {
                return null;
            }
        }
        $x = (float) $region['x'];
        $y = (float) $region['y'];
        $w = (float) $region['width'];
        $h = (float) $region['height'];
        if (! is_finite($x) || ! is_finite($y) || ! is_finite($w) || ! is_finite($h)) {
            return null;
        }
        if ($w <= 0 || $h <= 0 || $x < 0 || $y < 0 || $x + $w > 1.001 || $y + $h > 1.001) {
            return null;
        }

        return ['x' => $x, 'y' => $y, 'width' => $w, 'height' => $h];
    }

    /**
     * @param  array<int, array{0: float, 1: float, 2: float, 3: float}>  $boxes
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    private static function union(array $boxes): array
    {
        $x = min(array_column($boxes, 0));
        $y = min(array_column($boxes, 1));
        $right = max(array_map(fn ($b) => $b[0] + $b[2], $boxes));
        $bottom = max(array_map(fn ($b) => $b[1] + $b[3], $boxes));

        return [$x, $y, $right - $x, $bottom - $y];
    }
}
