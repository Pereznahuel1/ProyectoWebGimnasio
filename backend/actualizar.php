<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/schema.php';
try { migrate_modules(); echo "Módulos actualizados sin borrar información.\n"; }
catch (PDOException $e) { fwrite(STDERR, "No se pudo actualizar: " . $e->getMessage() . "\n"); exit(1); }
