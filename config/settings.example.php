<?php $settings = (object) array(
	"email" => (object) array(
		"host" => "smtp.office365.com",
		"port" => "587",
		"auth" => true,
		"username" => "username",
		"password" => "password",
		"localhost" => "domain.com"
	),
	"mysql" => (object) array(
		"hostname" => "localhost",
		"username" => "watcher",
		"password" => "",
		"database" => "watcher"
	),
	"client" => (object) array(
		"api" => "http://server.com/watcher/",
		"email_cookie" => "watcher_email",
		"token_cookie" => "watcher_token"
	),
  "checks" => (object) [
    ['http://domain.com:1234/'                                   ,'<html>'         ],
  ]
);
