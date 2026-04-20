<?php
session_start();
session_unset();
session_destroy();
header('Location: ../jiaowu/login.php');
exit;
