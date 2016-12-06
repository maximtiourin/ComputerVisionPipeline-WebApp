<?php
header('Content-type: image/png');
readfile(filter_input(INPUT_GET, "href", FILTER_SANITIZE_STRING));
?>

