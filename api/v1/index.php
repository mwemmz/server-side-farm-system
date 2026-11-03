<?php
// Vercel entry point for /api/v1 — delegates to the actual API front-controller.
chdir(__DIR__ . '/../../');
require __DIR__ . '/../../public/api/v1/index.php';
