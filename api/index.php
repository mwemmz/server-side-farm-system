<?php
// Vercel entry point — delegates to the actual public front-controller.
// __DIR__ here is /var/task/api, so we go up one level to reach the project root.
chdir(__DIR__ . '/../');
require __DIR__ . '/../public/index.php';
