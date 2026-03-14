<?php
$lines = file('deposit.php');
foreach ($lines as $i => $line) {
    if (stripos($line, '<script') !== false || stripos($line, '</script') !== false) {
        echo ($i+1) . ': ' . rtrim($line) . "\n";
    }
}
