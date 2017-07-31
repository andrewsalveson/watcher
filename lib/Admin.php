<?php namespace Sal;
define("ADSEP","_");
class Admin{
	var $mysql;
	var $limit;
	var $token = false;
	var $email = false;
	var $authenticated = false;
	var $uid = false;
	var $permissions = array('permissions_received'=>array(),'permissions_given'=>array());
function __construct($settings){
	$this->settings = $settings;
	$this->mysql = mysqli_connect(
		$settings->mysql->hostname,
		$settings->mysql->username,
		$settings->mysql->password,
		$settings->mysql->database
	);
	$this->debug = false;
	// $this->debug = true;
	$this->limit = 75;
	if(isset($_COOKIE[$this->settings->client->token_cookie]))
		$this->token = $_COOKIE[$this->settings->client->token_cookie];
	if(isset($_COOKIE[$this->settings->client->email_cookie]))
		$this->email = $_COOKIE[$this->settings->client->email_cookie];
	if($this->token && $this->email)
		$this->authenticated = $this->check_token($this->email,$this->token);
	if($this->authenticated){
		$this->uid = $this->get_uid($this->email);
		$this->get_permissions();
	}
	if(!$this->check_db()){
		echo $this->build_db();
	}
}

function close(){
	$this->mysql->close();
}
function check_db(){
	$results = $this->mysql->query("SHOW TABLES");
	if($results->num_rows == 0){
		return false;
	}
	return true;
}
function build_db(){
	echo "BUILDING THE DATABASE\n";
	$sql = file_get_contents('lib/database.sql');
	$results = $this->mysql->multi_query($sql);
	return $results;
}

// password/admin related
function send_email($email,$subject,$message){
	error_reporting(E_ALL & ~E_STRICT);
	include 'Mail.php';
	$headers = [
		'From'=>$this->settings->email->username,
		'To'=>$email,
		'Subject'=>$subject,
		'Reply-To'=>$this->settings->email->username,
		'Content-Type'=>'text/html; charset=ISO-8859-1',
		'Return-path'=>'andrew.salveson@perkinswill.com'
	];
	$params = array(
		"host"=>$this->settings->email->host,
		"port"=>$this->settings->email->port,
		"auth"=>$this->settings->email->auth,
		"username"=>$this->settings->email->username,
		"password"=>$this->settings->email->password,
		"localhost"=>$this->settings->email->localhost
	);
	$smtp =& \Mail::factory('smtp',$params);
	$success = $smtp->send($email,$headers,$message);
	if(\PEAR::isError($smtp)){print($mail->getMessage());}
	return $success;
}
function check_token($email,$token){
	$results = $this->mysql->query("SELECT `sphash` FROM `_users` WHERE `email` = '$email' LIMIT 1");
	if(!$results || $results->num_rows == 0){
		return false;
	}
	$compare = $results->fetch_object()->sphash;
	return ($token == $compare);
}

// client
function add($table,$args){
	$table_name = $this->table_name($table);
	$fields = [];
	$values = [];
	foreach($args as $key=>$value){
		// sanitize for MySQL
		// values passed through requests are sanitized in header.php,
		// however values that are passed through JSON objects may 
		// not have been . . .
		$fields[] = $this->mysql->escape_string($key);
		$values[] = $this->mysql->escape_string($value);
	}

	$parameters = "INSERT INTO `{$table_name}` (`".implode($fields,'`,`')."`) VALUES ('".implode($values,"','")."')";
	// echo $parameters;
	$results = $this->mysql->query($parameters);
	if(!$results){
		return false;
		echo mysql_error();
	}
	if($table == 'nodes')
			return 'n'.$this->uid.'_'.$this->mysql->insert_id;
	return $this->mysql->insert_id;
}
function give_permission($id,$to){
	
}
function edit($table,$id,$args){
	$id=$this->format_id($id);
	$split = explode(ADSEP,$id);
	if(count($split)<2){
		echo "could not split $id\n";
		return false;
	}
	$user = $split[0];
	$userid = $split[1];
	if(!$this->check_permission($user,$this->uid,'edit')){
		if($this->debug) echo "user {$this->uid} does not have edit permission for $user\n";
		return false;
	}
	// edit a table row
	// $table = the name of the table
	// $id = ID column of the table. TODO: this might be different for different tables; include a WHERE clause?
	// $args = array of key=>value pairs
	$set = Array();
	foreach($args as $key=>$val)
		array_push($set,"`$key` = '$val'");
	$table_prefix = substr($table,0,1);
	$set_statement = implode(',',$set);
	
	$parameters = "UPDATE `u{$user}{$table_prefix}` SET $set_statement WHERE `id` = '$userid' LIMIT 1";
	// echo "$parameters\n";
	$this->mysql->query($parameters);
	if($this->mysql->affected_rows == 0){
		// echo $parameters."\n";
		// echo $this->mysql->error;
		return false;
	}
	return true;
}
function delete($table,$id){
	$id = $this->format_id($id);
	$split = explode(ADSEP,$id);
	if(count($split)<2){
		echo "could not split $id\n";
		return false;
	}
	$user = $split[0];
	$userid = $split[1];
	// may only delete from your own table
	$table_prefix = substr($table,0,1);
	$parameters = "DELETE FROM `u{$this->uid}{$table_prefix}` WHERE `id` = '$userid' LIMIT 1";
	echo "$parameters\n";
	return $this->mysql->query($parameters);
}

// user related
function add_user($email,$password,$name){
	// $email = strtolower($email);
	$success = false;
	$password = $email.$password;
	// $email = hash('sha512',$email); // already hashed in JS
	$salt = $this->mysql->query("SELECT `value` FROM `_settings` WHERE `parameter` = 'salt' LIMIT 1")->fetch_object()->value;
	// echo "salt: $salt<br>\n";
	$spass = $password.$salt;
	// echo "spass: $spass<br>\n";
	$phash = hash('sha512',$password);
	// echo "phash: $phash<br>\n";
	$sphash = hash('sha512',$spass);
	// echo "sphash: $sphash<br>\n";
	$id = $this->add('_users',array('name'=>$name,'phash'=>$phash,'sphash'=>$sphash,'email'=>$email));
	if($id==false || $id==0){
		throw new \Exception("couldn't add ID $name $phash $sphash $email".mysql_error());
	}
	// echo "id: $id<br>\n";
	// $id = '';
	$query = "
CREATE TABLE IF NOT EXISTS u{$id}n (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` text NOT NULL,
  `type` varchar(64) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1;

CREATE TABLE IF NOT EXISTS `u{$id}r` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` text NOT NULL,
  `type` text NOT NULL,
  `source` tinytext NOT NULL,
  `destination` tinytext NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1;

CREATE TABLE IF NOT EXISTS `u{$id}p` (
  `id` mediumint(9) NOT NULL AUTO_INCREMENT,
  `parent` tinytext NOT NULL,
  `value` varchar(256) NOT NULL,
  `type` varchar(16) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1;

CREATE TABLE IF NOT EXISTS `u{$id}v` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` int(11) NOT NULL,
  `name` varchar(64) NOT NULL,
  `cache` longtext NOT NULL,
  PRIMARY KEY (`id`),
  KEY `id` (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1;

";
	// echo "<pre>$query</pre>";
	if(!$this->mysql->multi_query($query)){
		throw new \Exception($this->mysql->error);
	}else
		$success = true;
	return $success;
}
function all_users(){
	$all_users = $this->mysql->query("SELECT `id`,`name` FROM `_users`");
	$output = Array();
	while($row = $all_users->fetch_object()){
		$output[$row->id] = $row->name;
	}
	return $output;
}

// misc
function format_id($id){
	// strips a leading 'n' from $id
	if($this->debug) echo "formatting '$id'\n";
	if(substr($id,0,1)=='n'||substr($id,0,1)=='v'||substr($id,0,1)=='p' || substr($id,0,1)=='r'){
		if($this->debug) echo "stripping 'n'\n";
		return substr($id,1);
	}
	return $id;
}
function table_name($table){
	/*
		add a row to a table -
		$table = the name of the table
		$args = array of key=>value pairs
		
		system tables:
			_settings
			_permissions
			_types
			_users
		
		user tables (that get converted down below):
			nodes
			parameters
			relationships
			views
	*/
	$table_name = '';
	if(substr($table,0,1)=='_'){
		// table is a system table
		// all system tables start with an underscore, i.e. `_settings`
		$table_name = $table;
	}else{
		 // table is a user table
		 // of the form `u12r` for instance for user 12 relationships
		$table_name = 'u'.$this->uid.substr($table,0,1);
	}
	return $table_name;
}

// permissions
function grant_permission($email,$type){
	if(!$this->authenticated)
		return false;
	$token = hash('sha512',$email);
	$toid = $this->get_uid($token);
	echo "granting '$type' to $email ($toid) with token \n$token\n";
	return $this->add('_permissions',array('from'=>$this->uid,'to'=>$toid,'type'=>$type));
}
function delete_permission($id){
	if(!$this->authenticated)
		return false;
	return $this->mysql->query("DELETE FROM `_permissions` WHERE `from` = '{$this->uid}' AND `id` = '$id' LIMIT 1");
}
function get_permissions(){
	if(!$this->authenticated)
		return false;
	// echo $this->uid."\n";
	$fparams = "SELECT `id`,`from`,`type` FROM `_permissions` WHERE `to` = '{$this->uid}' ORDER BY `from` ASC";
	// echo "$fparams\n";
	$from_results = $this->mysql->query($fparams);
	// echo "{$from_results->num_rows}\n";
	if($from_results && $from_results->num_rows != 0){
		while($ob = $from_results->fetch_object()){
			$email = $this->get_userToken($ob->from);
			$name = $this->get_username($email);
			$this->permissions['permissions_received'][] = array('email'=>$email,'from'=>$ob->from,'type'=>$ob->type,'name'=>$name);
		}
	}
	$tparams = "SELECT `id`,`to`,`type` FROM `_permissions` WHERE `from` = '{$this->uid}' ORDER BY `to` ASC";
	// echo "$tparams\n";
	$to_results = $this->mysql->query($tparams);
	// echo "{$to_results->num_rows}\n";
	if($to_results && $to_results->num_rows != 0){
		while($ob = $to_results->fetch_object()){
			$email = $this->get_userToken($ob->to);
			$name = $this->get_username($email);
			$this->permissions['permissions_given'][] = array('permission_id'=>$ob->id,'id'=>$ob->to,'email'=>$email,'type'=>$ob->type,'name'=>$name);
		}	
	}
}
function permissions(){
	if(!$this->authenticated)
		return false;
	return $this->permissions;
}
function check_permission($from,$to,$type){
	if($from==$to)return true;
	$params = "SELECT `type` FROM `_permissions` WHERE `from` = '$from' AND `to` = '$to' AND `type` = '$type'";
	$results = $this->mysql->query($params);
	if($results && $results->num_rows > 0){
		if($this->debug) echo "$to does not have $type permission on $from\n";
		return true;
	}
	if($this->debug) echo "$to has $type permission on $from\n";
	return false;
}

// user-specific
function reset($email,$token,$password){
	$email = strtolower($email);
	$email = hash('sha512',$email);
	$password = hash('sha512',$password);
	$result = $this->mysql->query("SELECT `id` FROM `_users` WHERE `email` = '$email' LIMIT 1");
	if($result->num_rows == 0){
		// echo "$email not found in _users";
		return false;
	}
	$id = $result->fetch_object()->id;
	$req_result = $this->mysql->query("SELECT `id` FROM `_requests` WHERE `from` = '$id' AND `type` = 'password_reset' AND `value` = '$token' LIMIT 1");
	if($req_result->num_rows == 0){
		// echo "password_reset not found in _requests for $id with token $token";
		return false;
	}
	$req_id = $req_result->fetch_object()->id;
	$password = $email.$password;
	$salt = $this->mysql->query("SELECT `value` FROM `_settings` WHERE `parameter` = 'salt' LIMIT 1")->fetch_object()->value;
	$spass = $password.$salt;
	$sphash = hash('sha512',$spass);
	$upd_params = "UPDATE `_users` SET `sphash`='$sphash' WHERE `email`='$email' LIMIT 1";
	$upd_result = $this->mysql->query($upd_params);
	// echo "$upd_params<br>";
	if(!$upd_result){
		// echo "problem with $upd_params : ".mysql_error();
	}
	return $upd_result;
}
function forgot($email){
	$email = strtolower($email);
	$original = $email;
	$email = hash('sha512',$email);
	$success = false;
	$message = "request processed:\n\nif '$original' was found in the database, a reset password email was sent";
	$result = $this->mysql->query("SELECT `id` FROM `_users` WHERE `email` = '$email' LIMIT 1");
	if($result->num_rows == 0){
		return $message;
	}
	$from = $result->fetch_object()->id;
	$num = rand();
	$link = $this->settings->client->api."cathode/reset.php?email=$original&token=$num";
	$reset_message = <<<MESSAGE
<p>A password reset has been requested for this email address.</p>
<p>To reset your password, please use the following link (copy/paste it in a browser if your email client does not support rich text):<br>
<a href="$link" target="_blank">$link</a>
</p>
<p>If a password reset was NOT requested, please contact the administrator: <a href="mailto:andrew.salveson@perkinswill.com">andrew.salveson@perkinswill.com</a>
</p>
MESSAGE;
	$request_add = "INSERT INTO `_requests` (`from`,`type`,`value`) VALUES ('$from','password_reset','$num')";
	if($this->mysql->query($request_add)){
		$this->send_email($original,"anode password reset",$reset_message);
	}else{
		return "there was a problem processing this request";
	}
	return $message;
}
function get_token($email,$password){
	$success = false;
	$password = $email.$password;
	// $email = hash('sha512',$email); // already hashed in JS
	$salt = $this->mysql->query("SELECT `value` FROM `_settings` WHERE `parameter` = 'salt' LIMIT 1")->fetch_object()->value;
	// echo "  salt: $salt\n";
	$spass = $password.$salt;
	// echo " spass: $spass\n";
	$sphash = hash('sha512',$spass);
	// echo "sphash: $sphash\n";
	$results = $this->mysql->query("SELECT `sphash` FROM `_users` WHERE `email` = '$email' LIMIT 1");
	if(!$results || $results->num_rows == 0){
		// echo "could not find email $email in users\n";
		return $success;
	}
	$compare = $results->fetch_object()->sphash;
	// echo $sphash."<br>\n".$compare;
	if($sphash == $compare){
		// echo "token: $sphash\n";
		return $sphash;
	}else{
		// echo "sphash does not match database\n";
	}
	return $success;
}
function get_userToken($id){
	$params = "SELECT `email` FROM `_users` WHERE `id` = '$id' LIMIT 1";
	$results = $this->mysql->query($params);
	if(!$results || $results->num_rows == 0)
		return false;
	return $results->fetch_object()->email;
}
function get_uid($email){
	$result = $this->mysql->query("SELECT `id` FROM `_users` WHERE `email` = '$email' LIMIT 1");
	if(!$result || $result->num_rows == 0)
		return false;
	return $result->fetch_object()->id;
}
function change_username($newUsername){
	if(!$this->authenticated)
		return false;
	return $this->mysql->query("UPDATE `_users` SET `name` = '$newUsername' WHERE `email` = '{$this->email}' LIMIT 1");
}
function get_username($email){
	$params = "SELECT `name` FROM `_users` WHERE `email` = '$email' LIMIT 1";
	$results = $this->mysql->query($params);
	if(!$results || $results->num_rows == 0)
		return false;
	return $results->fetch_object()->name;	
}
function set_home($id){
	$query = "UPDATE `_users` SET `root` = '$id' WHERE `id` = '{$this->uid}' LIMIT 1";
	$results = $this->mysql->query($query);
	if(!$results){
		return false;
	}
	return true;
}
function get_root(){
	$params = "SELECT `root` FROM `_users` WHERE `id` = '{$this->uid}' LIMIT 1";
	$results = $this->mysql->query($params);
	if(!$results || $results->num_rows == 0)
		return false;
	return $results->fetch_object()->root;
}
function views(){
	if(!$this->authenticated){
		// echo "not authenticated\n";
		return ["not authenticated"];
	}
	$parameter_array = array("(SELECT CONCAT('v{$this->uid}_',`id`) as `id`,`name` FROM `u{$this->uid}v`)");
	if($this->debug) echo "{$this->uid}\n";
	foreach($this->permissions['permissions_received'] as $received){
		$uid = $received['from'];
		$parameter_array[] = "(SELECT CONCAT('v{$uid}_',`id`) as `id`,`name` FROM `u{$uid}v`)";
	}
	$parameters = implode(" UNION ",$parameter_array);
	if($this->debug) echo "$parameters\n";
	$result = $this->mysql->query($parameters);
	
	if(!$result){
		return [mysql_error()];
	}
	if($result->num_rows == 0){
		return [false];
	}
	$output = Array();
	while($row = $result->fetch_row()){
		$output[$row[0]] = $row[1];
	}
	return $output;
}
}