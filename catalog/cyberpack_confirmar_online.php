<?php
require('includes/application_top.php');
require(DIR_WS_MODULES . 'payment/cyberpack.php');


$cyberpack=new cyberpack();


$cyberpack->respuesta();
