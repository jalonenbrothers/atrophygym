<?php
declare(strict_types=1);

/**
 * AtrophyGym — server-rendered SVG confidence curve (F4).
 * One point per rep, evenly spaced by index (X = rep sequence, NOT calendar),
 * Y = rating 1–5. Last point emphasized (r=5 vs r=3). No JS, no client math.
 */
function render_confidence_svg(array $ratings /* ints 1..5, chron order */): string
{
    $n = count($ratings);
    $W = 720; $H = 180;
    $padL = 40; $padR = 20; $padT = 15; $padB = 30;
    $innerW = $W - $padL - $padR;
    $innerH = $H - $padT - $padB;

    if ($n === 0) return '';
    if ($n === 1) {
        // single point centered; text handled by caller ("One rep — the curve starts with the second.")
        $x = $padL + $innerW / 2;
        $y5 = $padT; $y1 = $padT + $innerH;
        $y = $y5 + (($y1 - $y5) * (5 - $ratings[0]) / 4);
        $s  = svg_open($W, $H);
        $s .= gridlines($padL, $padT, $innerW, $innerH, true);
        $s .= '<circle cx="' . (string)(int)$x . '" cy="' . (string)(int)$y . '" r="5" fill="#2E5E4E"/>';
        $s .= '</svg>';
        return $s;
    }
    $s  = svg_open($W, $H);
    $s .= gridlines($padL, $padT, $innerW, $innerH, true);
    $y5 = $padT; $y1 = $padT + $innerH;
    $pts = [];
    for ($i = 0; $i < $n; $i++) {
        $x = $padL + ($n === 1 ? 0 : ($innerW * $i / ($n - 1)));
        $y = $y5 + (($y1 - $y5) * (5 - $ratings[$i]) / 4);
        $pts[] = [$x, $y];
    }
    $path = 'M';
    foreach ($pts as $k => $p) { $path .= ($k ? ' L' : ' ') . round($p[0], 1) . ',' . round($p[1], 1); }
    $s .= '<path d="' . $path . '" fill="none" stroke="#2E5E4E" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>';
    foreach ($pts as $k => $p) {
        $r = ($k === $n - 1) ? 5 : 3;
        $s .= '<circle cx="' . round($p[0], 1) . '" cy="' . round($p[1], 1) . '" r="' . $r . '" fill="#2E5E4E"/>';
    }
    $s .= '</svg>';
    return $s;
}

function svg_open(int $W, int $H): string
{
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $W . ' ' . $H
        . '" style="width:100%;height:180px" role="img" aria-label="Confidence over time">';
}

function gridlines(int $padL, int $padT, int $innerW, int $innerH, bool $labels): string
{
    $s = '';
    $y5 = $padT; $y1 = $padT + $innerH;
    for ($r = 1; $r <= 5; $r++) {
        $y = $y5 + (($y1 - $y5) * (5 - $r) / 4);
        $s .= '<line x1="' . $padL . '" y1="' . round($y, 1) . '" x2="' . ($padL + $innerW) . '" y2="' . round($y, 1)
            . '" stroke="#E8E6E0" stroke-width="1"/>';
        if ($labels) {
            $s .= '<text x="' . ($padL - 8) . '" y="' . (round($y, 1) + 4) . '" text-anchor="end" font-size="11" fill="#6B6B66">' . $r . '</text>';
        }
    }
    return $s;
}

/** "Nov 4" / "Nov 9, 2026" style dates. */
function fmt_short(string $ymd): string { return date('M j', strtotime($ymd)); }
function fmt_long(string $ymd): string  { return date('M j, Y', strtotime($ymd)); }
