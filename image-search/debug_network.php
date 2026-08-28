<?php
header('Content-Type: application/json; charset=utf-8');

$network = "\\\\192.168.0.204\\Master Design";
$drive = "Z:\\\\";

$out = [];
$out['php_user'] = get_current_user();
$out['env_USERNAME'] = getenv('USERNAME');
$out['cwd'] = getcwd();
$out['disable_functions'] = ini_get('disable_functions');

$out['checks'] = [
    'is_dir_network' => is_dir($network),
    'is_readable_network' => is_readable($network),
    'is_dir_drive' => is_dir($drive),
    'is_readable_drive' => is_readable($drive),
];

$out['scandir_sample'] = [
    'network' => null,
    'drive' => null
];

// Try listing a few entries if accessible
if ($out['checks']['is_dir_network']) {
    $list = @scandir($network);
    if ($list !== false) {
        $out['scandir_sample']['network'] = array_slice(array_values(array_diff($list, ['.','..'])), 0, 20);
    } else {
        $out['scandir_sample']['network'] = 'scandir failed';
    }
}

if ($out['checks']['is_dir_drive']) {
    $list = @scandir($drive);
    if ($list !== false) {
        $out['scandir_sample']['drive'] = array_slice(array_values(array_diff($list, ['.','..'])), 0, 20);
    } else {
        $out['scandir_sample']['drive'] = 'scandir failed';
    }
}

// Try to run `net use` if possible
$out['net_use'] = null;
if (function_exists('shell_exec') && !stripos($out['disable_functions'], 'shell_exec')) {
    // Try simple command
    $cmd = 'net use 2>&1';
    $net = @shell_exec($cmd);
    $out['net_use_basic'] = $net === null ? 'shell_exec returned null or blocked' : $net;

    // Try calling net.exe by full paths to detect WOW64 redirection issues
    $pathsToTry = [
        'C:\\Windows\\System32\\net.exe use 2>&1',
        'C:\\Windows\\Sysnative\\net.exe use 2>&1'
    ];
    $out['net_use_fullpaths'] = [];
    foreach ($pathsToTry as $p) {
        $res = @shell_exec($p);
        $out['net_use_fullpaths'][$p] = $res === null ? 'null or blocked' : $res;
    }

    // Show PATH and whoami
    $out['whoami'] = @shell_exec('whoami 2>&1');
    $out['echo_path'] = @shell_exec('echo %PATH% 2>&1');

    // Try running full-path executables via COMSPEC to avoid redirection issues
    $windir = getenv('windir') ?: 'C:\\Windows';
    $comspec = getenv('COMSPEC') ?: 'C:\\Windows\\system32\\cmd.exe';
    $extraChecks = [];
    $cmds = [
        // net.exe full path
        "\"$windir\\System32\\net.exe\" use 2>&1",
        // try Sysnative (only available for 32-bit processes on 64-bit Windows)
        "\"$windir\\Sysnative\\net.exe\" use 2>&1",
        // whoami full path
        "\"$windir\\System32\\whoami.exe\" 2>&1",
        // attempt a directory listing of the UNC path
        "%COMSPEC% /c dir \\\\\\192.168.0.204\\\\Master Design 2>&1",
    ];
    foreach ($cmds as $c) {
        $res = @shell_exec($c);
        $extraChecks[$c] = $res === null ? 'null or blocked' : $res;
    }
    $out['extra_command_checks'] = $extraChecks;

    // Append a short debug log file for later inspection
    $log = [
        'time' => date('c'),
        'php_user' => $out['php_user'],
        'cwd' => $out['cwd'],
        'net_use_basic' => $out['net_use_basic'] ?? null,
        'net_use_fullpaths' => $out['net_use_fullpaths'] ?? null,
        'extra_command_checks' => $extraChecks
    ];
    @file_put_contents(__DIR__ . '/debug_network_exec.log', json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n", FILE_APPEND);
} else {
    $out['net_use'] = 'shell_exec not available or disabled';
}

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

?>
