<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/bootstrap.php';
sessao_iniciar();
$_SESSION = [];
session_destroy();
header('Location: login.php');
