<?php
$lines = file('deposit.php');
for ($i = 480; $i <= 520; $i++) {
    $line = isset($lines[$i]) ? $lines[$i] : '';
    echo ($i + 1) . ': ' . $line;
}
