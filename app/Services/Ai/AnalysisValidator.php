<?php

namespace App\Services\Ai;

use Illuminate\Support\Str;

/**
 * Never trust model output. Rebuilds the analysis from scratch, keeping only
 * known block types, plain-text strings, and numerically consistent data.
 * Numbers that can be derived (change %) are computed here, not by the model.
 */
class AnalysisValidator
{
    public static function clean(array $raw, array $manifest): array
    {
        $summary = self::s($raw['summary'] ?? '', 3000);
        if ($summary === '') {
            throw new \UnexpectedValueException('AI returned an empty summary.');
        }

        $blocks = [];
        foreach (array_slice($raw['blocks'] ?? [], 0, 12) as $b) {
            if (!is_array($b)) continue;
            $clean = match ($b['type'] ?? null) {
                'metric_comparison' => self::metrics($b),
                'chart'             => self::chart($b),
                'table'             => self::table($b),
                'activity_log'      => self::activity($b),
                'item_list'         => self::itemList($b),
                default             => null, // unknown types are dropped
            };
            if ($clean) $blocks[] = $clean;
        }

        return [
            'title'            => self::s($raw['title'] ?? '', 200),
            'summary'          => $summary,
            'blocks'           => $blocks,
            'files_analyzed'   => $manifest, // from our preprocessor, not from the model
            'confidence_notes' => self::s($raw['confidence_notes'] ?? '', 1500) ?: null,
        ];
    }

    private static function metrics(array $b): ?array
    {
        $items = [];
        foreach (array_slice($b['items'] ?? [], 0, 25) as $i) {
            $prev = is_numeric($i['previous'] ?? null) ? $i['previous'] + 0 : null;
            $cur  = is_numeric($i['current'] ?? null) ? $i['current'] + 0 : null;
            $label = self::s($i['label'] ?? '', 120);
            if ($label === '' || ($prev === null && $cur === null)) continue;
            $items[] = [
                'label'      => $label,
                'previous'   => $prev,
                'current'    => $cur,
                'unit'       => self::s($i['unit'] ?? '', 30) ?: null,
                'change_pct' => ($prev && $cur !== null) ? round(($cur - $prev) / abs($prev) * 100, 1) : null,
            ];
        }
        return $items ? self::base($b, 'metric_comparison') + ['items' => $items] : null;
    }

    private static function chart(array $b): ?array
    {
        $type = in_array($b['chart_type'] ?? null, ['bar', 'line', 'pie'], true) ? $b['chart_type'] : null;
        $labels = array_map(fn ($l) => self::s($l, 60), array_slice($b['labels'] ?? [], 0, 30));
        if (!$type || count($labels) < 2) return null;

        $series = [];
        foreach (array_slice($b['series'] ?? [], 0, 6) as $s) {
            $data = $s['data'] ?? [];
            // drop series that don't line up with the labels
            if (!is_array($data) || count($data) !== count($labels)) continue;
            if (collect($data)->contains(fn ($v) => !is_numeric($v))) continue;
            $series[] = ['name' => self::s($s['name'] ?? '', 60), 'data' => array_map(fn ($v) => $v + 0, $data)];
        }
        if ($type === 'pie') $series = array_slice($series, 0, 1);

        return $series ? self::base($b, 'chart') + ['chart_type' => $type, 'labels' => $labels, 'series' => $series] : null;
    }

    private static function table(array $b): ?array
    {
        $cols = array_map(fn ($c) => self::s($c, 60), array_slice($b['columns'] ?? [], 0, 8));
        if (!$cols) return null;

        $rows = [];
        foreach (array_slice($b['rows'] ?? [], 0, 50) as $r) {
            $cells = array_slice($r['cells'] ?? [], 0, count($cols));
            $cells = array_map(fn ($c) => self::s($c, 200), $cells);
            $rows[] = array_pad($cells, count($cols), '');
        }
        return $rows ? self::base($b, 'table') + ['columns' => $cols, 'rows' => $rows] : null;
    }

    private static function activity(array $b): ?array
    {
        $items = [];
        foreach (array_slice($b['items'] ?? [], 0, 20) as $i) {
            $label = self::s($i['label'] ?? '', 120);
            if ($label !== '' && is_numeric($i['count'] ?? null) && $i['count'] >= 0) {
                $items[] = ['label' => $label, 'count' => (int) $i['count']];
            }
        }
        return $items ? self::base($b, 'activity_log') + ['items' => $items] : null;
    }

    private static function itemList(array $b): ?array
    {
        $items = [];
        foreach (array_slice($b['items'] ?? [], 0, 20) as $i) {
            $row = [
                'heading' => self::s($i['heading'] ?? '', 150),
                'text'    => self::s($i['text'] ?? '', 500),
                'stats'   => self::s($i['stats'] ?? '', 200),
            ];
            if ($row['heading'] !== '' || $row['text'] !== '') $items[] = $row;
        }
        return $items ? self::base($b, 'item_list') + ['items' => $items] : null;
    }

    private static function base(array $b, string $type): array
    {
        return [
            'type'   => $type,
            'title'  => self::s($b['title'] ?? '', 150) ?: Str::headline($type),
            'source' => self::s($b['source'] ?? '', 200) ?: null,
            'note'   => self::s($b['note'] ?? '', 300) ?: null,
        ];
    }

    private static function s(mixed $v, int $max): string
    {
        return Str::limit(trim(strip_tags((string) $v)), $max, '');
    }
}