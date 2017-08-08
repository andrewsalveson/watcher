<?php namespace Sal;
class Service{
	var $output = [
		"messages" => [],
		"results" => []
	];
  var $settings;
	var $api;
	var $graph;
	var $debug = false;
	
	function __construct($settings){
    $this->settings = $settings;
		$this->admin = new \Sal\Admin($settings);
		$this->graph = new \Sal\Graph($settings);
		$this->api = new \Slim\Slim();

		$this->sanitize_requests();
		$token = false;
		$email = false;
		$this->set_public_routes();
		
		$this->output["messages"][] = "adding public routes";
		if($this->admin->authenticated){
			$this->set_table_routes();
			$this->set_graph_routes();
		}

		$this->api->run();
		$this->serve();
		$this->admin->close();
	}
	function load_output($output){
		foreach($output['messages'] as $msg)
			$this->output["messages"][] = $msg;
		$this->output["results"] = $output["results"];
	}
	function set_public_routes(){
		$this->api->post('/token',function(){
			$e = $_REQUEST['email'];
			$p = $_REQUEST['password'];
			$this->output["results"] = $this->admin->get_token($e,$p);
		});
		$this->api->post('/user',function(){
			$password = $_REQUEST['password'];
			$email = $_REQUEST['email'];
			$display = $_REQUEST['display'];
			$this->output["messages"][] = "attempting to add email address $email";
			try{
				$this->output["results"] = $this->admin->add_user($email,$password,$display);
			}catch(\Exception $e){
				$this->output["messages"][] = $e->getMessage();
			}
		});
		$this->api->post('/user/forgot_password',function(){
			$email = $_REQUEST['email'];
			$this->output["results"] = $this->admin->forgot($email);
		});
		$this->api->post('/user/reset_password',function(){
			$t = $_REQUEST['token'];
			$p = $_REQUEST['password'];
			$e = $_REQUEST['email'];
			$this->output["results"] = $this->admin->reset($e,$t,$p);	
		});
    $this->api->get('/items',function(){
      $this->output["results"] = array_keys((array)$this->settings->checks);
    });
    $this->api->get('/items/:id',function($id){
      $checks = $this->settings->checks;
      if(!isset($checks->$id)){
        throw new Exception("ID $id not found");
      }
      $this->output["results"][] = $this->perform_check($checks->$id);
    });
    $this->api->get('/status',function(){
      $this->output["results"] = [];
      $checks = $this->settings->checks;
      foreach($checks as $check){
        $this->output["results"][] = $this->perform_check($check);
      }
    });
	}
  function perform_check($check){
    $url = $check->URL;
    $chk = preg_quote($check->RESPONSE);
    $config = (object)[
      'url' => $url,
      'timeout' => $this->settings->watcher->timeout
    ];
    $result = \Sal\CurlClient::get($config);
    $res = $result->RESPONSE;
    $status = "ERR";
    $pattern = "#^$chk#";
    if(preg_match($pattern,$res)){
      $status = "OK";
    }
    return (object)[
      "ID"=>"{$check->ID}",
      "URL"=>$url,
      "HTTP_CODE"=>$result->HTTP_CODE,
      "STATUS"=>$status
    ];
  }
	function set_table_routes(){
		$this->api->get('/user/name',function(){
			$name = $_COOKIE['anode_email'];
			$this->output["results"] = $this->admin->get_username($name);
		});
		$this->api->get('/user/root',function(){
			$this->output["results"] = $this->admin->get_root();
		});
		$this->api->post('/user/root/:id',function($id){
			$this->output["messages"][] = "setting home to $id";
			$this->output["results"] = $this->admin->set_home($id);
		});
		$this->api->get('/user/permissions',function(){
			$this->output["results"] = $this->admin->permissions();
		});
		$this->api->get('/user/views',function(){
			$this->output["results"] = $this->admin->views();
		});

		$this->api->post('/user/name',function(){
			$this->output["results"] = $this->admin->change_username($_REQUEST['newUsername']);
		});

		$this->api->get('/type/:name',function($name){
			$output = Array($name,'');
			if($color_query = $this->admin->mysql->query("SELECT `color` FROM `_types` WHERE `name` = '$name' LIMIT 1")){
				$color = $color_query->fetch_row()[0];
				if($color == ''){
					$output[1] = '#888';
				}else{
					$output[1] = $color;
				}
			}else
				$this->output["messages"][] = mysql_error();
			$this->output["results"] = $output;
		});
		
		$this->api->post('/type/:name/:color',function($name,$color){
			$exists = $this->admin->mysql->query("SELECT `id` FROM `_types` WHERE `name` = '$name'");
			$success = false;
			if($exists->num_rows == 0){
				$parameters = "INSERT INTO `_types` (`name`,`color`) VALUES ('$name','$color')";
				if(!$color_query = $anode->mysql->query($parameters)){
					$this->output["messages"][] = mysql_error();
				}else{
					$success = true;
				}
			}else{
				$parameters = "UPDATE `_types` SET `color`='$color' WHERE `name` = '$name' LIMIT 1";
				if(!$color_query = $anode->mysql->query($parameters)){
					$this->output["messages"][] = mysql_error();
				}else{
					$success = true;
				}
			}
			$this->output["results"] = $success;
		});
		
		$this->api->delete('/permission',function(){
			$e = $_REQUEST['email'];
			$t = $_REQUEST['type'];
			$this->output["results"] = $this->admin->revoke_permission($e,$t);
		});
		$this->api->delete('/permission/:id',function($id){
			$this->output["results"] = $this->admin->delete_permission($id);
		});
	}
	function set_graph_routes(){
		$this->api->get('/node/:id',function($id){
			$this->output["results"] = $this->graph->node_by_id($id);
		});
		$this->api->get('/nodes/by/:par/:val/:degree',function($par,$val,$degree){
			$this->output["results"] = $this->graph->nodes_by_parameter_strict($par,$val,$degree);
		});		
		$this->api->get('/nodes/find/:par/:term/:degree',function($par,$term,$degree){
			$this->output["results"] = $this->graph->nodes_by_parameter_search($par,$term,$degree);
		});
		$this->api->get('/query/:term/:degree',function($term,$degree){
			$this->output["messages"][] = "processing query '$term'";
			if(preg_match('/^[0-9a-zA-Z _]*~.*/',$term)){
				// match contains
				// term like 'office~york'
				// or 'person~andrew'
				$split = explode('~',$term);
				$type = $split[0]; // sanitize
				$name = $split[1]; // sanitize
				$this->output["messages"][] = "$type LIKE %$name%";
				$this->output["results"] = $this->nodes_by_two_param_search('type',$type,'name',$name,$degree);
			}elseif(preg_match('/^[0-9a-zA-Z _]*=.*/',$term)){
				// exact (case-insensitive) match
				// term like 'office=new york'
				// or 'person=Andrew Salveson'
				$split = explode('=',$term);
				$type = $split[0]; // sanitize
				$name = $split[1]; // sanitize
				$this->output["messages"][] = "$type EXACTLY $name";
				if($type == 'type'){
					$this->output["results"] = $this->graph->nodes_by_type_strict($name);
				}else{
					$this->output["results"] = $this->grpah->nodes_by_parameter_strict($type,$name,$degree);
				}
			}elseif(preg_match('/^[0-9a-zA-Z _]*$/',$term)){
				$this->output["messages"][] = "type OR name LIKE %$term%";
				$this->output["results"] = $this->graph->nodes_by_two_param_search('type',$term,'name',$term,$degree);
			}elseif(preg_match('/^[0-9a-zA-Z _]*:.*/',$term)){
				// search parameters
				// echo "is a parameter search\n";
				$split = explode(':',$term);
				$type = $split[0]; // sanitize
				$name = $split[1]; // sanitize
				$this->output["messages"][] = "type EXACTLY $type and name LIKE %$name%";
				$this->output["results"] = $this->graph->nodes_by_two_param_strict('type',$type,'name',$name,$degree);
			}
		});
		$this->api->get('/path/:from/to/:to',function($from_id,$to_id){
			$this->output["results"] = $this->graph->paths_between($from_id,$to_id);
		});
		$this->api->get('/all_paths/:from/to/:to',function($from_id,$to_id){
			$this->output["results"] = $this->graph->paths_between($from_id,$to_id);
		});
		$this->api->get('/combine/:a/:b',function($a,$b){
			$this->output["results"] = $this->graph->combine_nodes($a,$b);
		});
		$this->api->post('/collapse_link',function(){
			$post = $this->api->request()->post();
			$from_id = $post['from_id'];
			$to_id = $post['to_id'];
			$this->output["results"] = $this->graph->collapse_link($from_id,$to_id);
		});
		$this->api->post('/combine',function(){
			$post = $this->api->request()->post();
			$a = $post['a'];
			$b = $post['b'];
			$this->output["results"] = $this->graph->combine_nodes($a,$b);
		});
		$this->api->post('/node',function(){
			// make a new node
			$post = $this->api->request()->post();
			$name = $post['name'];
			$type = $post['type'];
			$this->output["results"] = $this->graph->create_node($name,$type);
		});
		$this->api->post('/edit_relationship',function(){
			$post = $this->api->request()->post();
			$from_id = $post['from_id'];
			$to_id = $post['to_id'];
			$old_relationship = $post['old_relationship'];
			$new_relationship = $post['new_relationship'];
			$this->output["results"] = $this->graph->edit_relationship($from_id,$to_id,$old_relationship,$new_relationship);
		});
		$this->api->post('/edit_node',function(){
			if($this->debug){
				$this->output["messages"][] = "edit route engaged";
			}
			$post = $this->api->request()->post();
			$id = $post['id'];
			$args = $post['args'];
			$this->output["results"] = $this->graph->edit_node($id,$args);
		});
		$this->api->post('/relate',function(){
			$from_id = $this->api->request()->post()['from'];
			$to_id = $this->api->request()->post()['to'];
			$relationship = $this->api->request()->post()['relationship'];
			$this->output["results"] = $this->graph->relate_nodes($from_id,$relationship,$to_id);
		});
		$this->api->post('/put',function(){
		});
		$this->api->delete('/node/:id',function($id){
			$this->output["results"] = $this->graph->delete_node($id);
		});
		$this->api->delete('/delete_relationship',function(){
			$post = $this->api->request()->post();
			$from_id = $post['from_id'];
			$relationship = $post['relationship'];
			$to_id = $post['to_id'];
			$this->output["results"] = $this->graph->delete_relationship($from_id,$relationship,$to_id);
		});
	}
	function sanitize_requests(){
		if (get_magic_quotes_gpc()) {
			$process = array(&$_GET, &$_POST, &$_COOKIE, &$_REQUEST);
			while (list($key, $val) = each($process)) {
				foreach ($val as $k => $v) {
					unset($process[$key][$k]);
					if (is_array($v)) {
						$process[$key][stripslashes($k)] = $v;
						$process[] = &$process[$key][stripslashes($k)];
					} else {
						$process[$key][stripslashes($k)] = stripslashes($v);
					}
				}
			}
			unset($process);
		}
	}
	function serve($type='json'){
		header('Access-Control-Allow-Origin: *');
		switch($type){
			case 'json':
				echo json_encode($this->output,JSON_PRETTY_PRINT);
				break;
			case 'xml':
				// need to add PEAR xml serializer
				break;
			case 'html':
				print_r($this->output);
				break;
		}
	}
}