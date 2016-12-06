<?php
header('Content-type: video/*');
readfile(filter_input(INPUT_GET, "href", FILTER_SANITIZE_STRING));
?>

