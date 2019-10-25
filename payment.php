<?php

include('handler.php');

$payment = new PaykeeperHandler;
$payment = $payment->index();
