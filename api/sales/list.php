<?php
require_once dirname(__DIR__, 2) . '/lib/bootstrap.php';

require_auth();
json_ok(array('items' => array()));
