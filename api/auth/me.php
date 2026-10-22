<?php
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';
require_once dirname(__DIR__, 2) . '/lib/auth.php';

$session = require_auth();
$user = refresh_shared_user($session['id']);
$_SESSION['user'] = $user;

json_ok(array('user' => $user));
