<?php
/*
Plugin Name: AMEX Gateway for Paid Memberships Pro
Description: American Express Gateway for Paid Memberships Pro
Author: Jimish Soni
Version: 1.0
*/

define("PMPRO_EXAMPLEGATEWAY_DIR", dirname(__FILE__));

//load payment gateway class
require_once(PMPRO_EXAMPLEGATEWAY_DIR . "/classes/class.pmprogateway_example.php");