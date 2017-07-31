<?php $settings = (object) array(
	"email" => (object) array(
		"host" => "mail.example.com",
		"port" => "587",
		"auth" => true,
		"username" => "admin@example.com",
		"password" => "supersecret",
		"localhost" => "example.com"
	),
	"mysql" => (object) array(
		"hostname" => "localhost",
		"username" => "anode",
		"password" => "megasecret",
		"database" => "anode"
	),
	"neo4j" => (object) array(
		"username" => "user",
		"password" => "pass"
	),
	"client" => (object) array(
		"api" => "http://example.com/anode/",
		"email_cookie" => "anode_email",
		"token_cookie" => "anode_token"
	)
);