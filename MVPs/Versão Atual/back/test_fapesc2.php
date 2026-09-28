<?php
$text = file_get_contents('fapesc_text.txt');
$sentences = preg_split('/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
foreach ($sentences as $s) {
    if (preg_match('/R\$\s*[0-9.,]+/i', $s)) {
        echo "VALOR: " . $s . "\n";
    }
    if (preg_match('/[0-9]{2}\/[0-9]{2}\/[0-9]{4}/i', $s)) {
        echo "DATA: " . $s . "\n";
    }
}
