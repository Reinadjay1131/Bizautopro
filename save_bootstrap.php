<?php
// save_bootstrap.php - Run this when you have internet
$bootstrap_css = file_get_contents('https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css');
$bootstrap_js = file_get_contents('https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js');
$bootstrap_icons = file_get_contents('https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css');

file_put_contents('assets/css/bootstrap.min.css', $bootstrap_css);
file_put_contents('assets/js/bootstrap.bundle.min.js', $bootstrap_js);
file_put_contents('assets/css/bootstrap-icons.css', $bootstrap_icons);

echo "Bootstrap downloaded locally!";
?>