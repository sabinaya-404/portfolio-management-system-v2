<?php
require_once __DIR__ . '/auth.php';

function require_admin(): void
{
    require_role('admin');
}
