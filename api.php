<?php require 'vendor/autoload.php';
error_reporting(E_ALL); ini_set('display_errors', 1);
require 'config/settings.php'; // loads $settings;
$service = new \Sal\Service($settings);