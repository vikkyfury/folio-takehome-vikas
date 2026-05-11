<?php

require __DIR__ . '/lib/bootstrap.php';

run_migrations(db());

echo "Migrations checked/applied.\n";
