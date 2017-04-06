<?php

define('OOP', true);
define('USE_FILE_REQUESTS', true);

require_once 'Scheduler.php';

$return = RequestHandler::HandleRequest();

echo $return . '<br/>';