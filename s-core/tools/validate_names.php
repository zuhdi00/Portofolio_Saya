<?php
function normalize_name($name) {
    if ($name === null) return '';
    $name = trim((string)$name);
    if ($name === '') return '';
    $name = mb_strtoupper($name, 'UTF-8');
    $name = preg_replace('/[.\\,\-_\/]/u', ' ', $name);
    $name = preg_replace('/\s+/', ' ', $name);
    $name = str_replace([' ACH ', ' MOCH ', ' MUH ', ' MUHAMMAD ', ' H ', ' KH '], ' ', $name);
    $name = preg_replace('/\b(ACH|MOCH|MUH|MUHAMMAD|H|KH)\b/u', '', $name);
    $name = preg_replace('/\s+/', ' ', trim($name));
    return $name;
}

function names_match($left, $right) {
    $left = normalize_name($left);
    $right = normalize_name($right);

    if ($left === '' && $right === '') return true;
    if ($left === '' || $right === '') return false;
    if (strtolower($left) === strtolower($right)) return true;
    if (strpos($left, $right) !== false || strpos($right, $left) !== false) return true;
    similar_text($left, $right, $percent);
    if ($percent >= 85) return true;

    $left_tokens = preg_split('/\s+/', $left);
    $right_tokens = preg_split('/\s+/', $right);
    if (count($left_tokens) > 0 && count($right_tokens) > 0) {
        $shared = array_intersect($left_tokens, $right_tokens);
        $left_common = count($shared) / max(count(array_unique($left_tokens)), 1);
        $right_common = count($shared) / max(count(array_unique($right_tokens)), 1);
        if ($left_common >= 0.6 || $right_common >= 0.6) return true;
    }

    return false;
}

$cases = [
    ['Zuhdi Abdillah Hidayat', 'ZUHDI ABDILLAH HIDAYAT'],
    ['Prio Suwahyo', 'PRIO'],
    ['Christian Marcello', 'CHRISTIAN MARCELLO DWISUSANTO'],
    ['Zuhdi Abdillah Hidayat', 'Zuhdi Abdillah Hidayat'],
    ['Prio Suwahyo', 'PAUL ALEXANDER'],
];

foreach ($cases as [$a, $b]) {
    echo $a . ' | ' . $b . ' => ' . (names_match($a, $b) ? 'MATCH' : 'DIFFER') . PHP_EOL;
}
