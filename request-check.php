<?php
try {
    echo file_get_contents('http://127.0.0.1');
} catch (Throwable $e) {
    echo 'EXC: ' . get_class($e) . ' - ' . $e->getMessage();
}
