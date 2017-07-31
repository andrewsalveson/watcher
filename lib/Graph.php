<?php namespace Sal;
class Graph{
	var $output = [
		"messages" => [],
		"results" => []
	];
	var $db;
	var $index;
	function __construct($settings){
		$this->settings = $settings;
		// everyman client . . . no longer in development
		$this->db = new \Everyman\Neo4j\Client();
		if(isset($settings->neo4j)){
			$this->db->getTransport()
				->setAuth($settings->neo4j->username,$settings->neo4j->password);
		}
		$this->index = new \Everyman\Neo4j\Index\NodeIndex($this->db,'nodes');
	}
	function get_node($id){
		$this->output["messages"][] = "fetching node by id: $id";
		$idIndex = new \Everyman\Neo4j\Index\NodeIndex($this->db,'Ids');
		$node = $idIndex->findOne('id',$id);
		if(!$node){
			$this->output["messages"][] = "node $id not found in database";
			return false;
		}
		return $node;
	}
	function node_by_id($id){
		$this->output["messages"][] = "node by id: $id";
		if(!$node = $this->get_node($id)){
			$this->output["messages"][] = "node $id not found";
			return false;
		}
		$this->output["results"][$id] = $this->array_from_node($node);
		$r = $node->getRelationships();
		if(count($r) < 1){
			$this->output["messages"][] = "$id has no relationships, returning node";
		}else{
			$this->output["messages"][] = "$id has relationships, returning node and relationships";
			$this->output["results"] = $this->array_from_relationships($r);
		}
		return $this->output["results"];
	}
	function node_by_type_and_name($type,$name){
		$type = $this->format_typename($type);
		$queryTemplate = <<<CYPHER
MATCH (a:$type)
	WHERE a.name =~ '(?i).*$name.*'
RETURN DISTINCT a
LIMIT 100;
CYPHER;
		$query = new \Everyman\Neo4j\Cypher\Query($this->db,$queryTemplate);
		$result = $query->getResultSet();
		return $this->array_from_result($result);
	}
	function nodes_by_type_strict($type){
		$type = $this->format_typename($type);
		$queryTemplate = <<<CYPHER
MATCH (a:$type)
	WITH a
		OPTIONAL MATCH (a)-[r*1]-(b:$type)
RETURN DISTINCT a,b,r
LIMIT 200;
CYPHER;
		$query = new \Everyman\Neo4j\Cypher\Query($this->db,$queryTemplate);
		$result = $query->getResultSet();
		return $this->array_from_result($result);
	}
	function nodes_by_parameter_strict($par,$val,$degree){
		$queryTemplate = <<<CYPHER
MATCH a
	WHERE a.$par="$val"
	WITH a
		OPTIONAL MATCH (a)-[r*1..$degree]-(b)
RETURN DISTINCT a,b,r
LIMIT 100;
CYPHER;
		$query = new \Everyman\Neo4j\Cypher\Query($this->db,$queryTemplate);
		$result = $query->getResultSet();
		return $this->array_from_result($result);
	}
	function nodes_by_two_param_strict($par1,$val1,$par2,$val2,$degree){
		$queryTemplate = <<<CYPHER
MATCH a
	WHERE a.$par1 = "$val1"
	AND
	a.$par2 =~ '(?i).*$val2.*'
	WITH a
		OPTIONAL MATCH (a)-[r*1..$degree]-(b)
RETURN DISTINCT a,b,r
LIMIT 100;
CYPHER;
		$this->output["messages"][] = $queryTemplate;
		$query = new \Everyman\Neo4j\Cypher\Query($this->db,$queryTemplate);
		$result = $query->getResultSet();
		return $this->array_from_result($result);		
	}
	function nodes_by_two_param_search($par1,$val1,$par2,$val2,$degree){
		$this->output["messages"][] = "nodes by two parameter search";
		$queryTemplate = <<<CYPHER
MATCH a
	WHERE a.$par1 =~ '(?i).*$val1.*'
	OR
	a.$par2 =~ '(?i).*$val2.*'
	WITH a
		OPTIONAL MATCH (a)-[r*1..$degree]-(b)
RETURN DISTINCT a,b,r
LIMIT 100;
CYPHER;
		$this->output["messages"][] = $queryTemplate;
		$query = new \Everyman\Neo4j\Cypher\Query($this->db,$queryTemplate);
		$result = $query->getResultSet();
		return $this->array_from_result($result);
	}
	function nodes_by_parameter_search($par,$term,$degree){
		$queryTemplate = <<<CYPHER
MATCH a
	WHERE a.$par =~ '(?i).*$term.*'
	WITH a
		OPTIONAL MATCH (a)-[r*1..$degree]-(b)
RETURN DISTINCT a,b,r
LIMIT 100;
CYPHER;
		$this->output["messages"][] = "$queryTemplate";
		$query = new \Everyman\Neo4j\Cypher\Query($this->db,$queryTemplate);
		$result = $query->getResultSet();
		return $this->array_from_result($result);
	}
	function path_between($from_id,$to_id){
		$this->output["messages"][] = "finding path from $from_id to $to_id";
		$queryTemplate = <<<CYPHER
MATCH 
	(a),
	(b),
	p=shortestPath((a)-[r*..6]-(b))
	WHERE a.name =~ '(?i).*$from_id.*' AND
		b.name =~ '(?i).*$to_id.*'
	RETURN DISTINCT r
	LIMIT 100;
CYPHER;
		$this->output["messages"][] = $queryTemplate;
		$query = new \Everyman\Neo4j\Cypher\Query($this->db,$queryTemplate);
		$result = $query->getResultSet();
		if($result){
			$this->output["results"] = $this->array_from_path($result);
		}else{
			$this->output["messages"][] = "not found: $from_id to $to_id\n<br>";
		}
		return $this->output["results"];
	}
	function paths_between($from_id,$to_id){
		$from_id = str_replace('.','\\\\.',$from_id);
		$to_id = str_replace('.','\\\\.',$to_id);
		$this->output["messages"][] = "finding path from $from_id to $to_id";
		$queryTemplate = <<<CYPHER
MATCH
	(a),
	(b),
	p=allShortestPaths((a)-[*]-(b))
	WHERE a.name =~ '(?i).*$from_id.*' AND
		b.name =~ '(?i).*$to_id.*'
	RETURN DISTINCT relationships(p)
	LIMIT 100;
CYPHER;
		$this->output["messages"][] = $queryTemplate;
		$query = new \Everyman\Neo4j\Cypher\Query($this->db,$queryTemplate);
		$result = $query->getResultSet();
		if($result){
			$this->output["results"] = $this->array_from_path($result);
		}else{
			$this->output["messages"][] = "not found: $from_id to $to_id\n<br>";
		}
		return $this->output["results"];
	}
	function edit_relationship($from_id,$to_id,$old_relationship,$new_relationship){
		$this->output["messages"][] = "editing ($from_id)-[$old_relationship]-($to_id) to $new_relationship";
		if($this->delete_relationship($from_id,$old_relationship,$to_id)){
			$this->output["messages"][] = "old relationship deleted";
		}
		if($this->relate_nodes($from_id,$new_relationship,$to_id)){
			$this->output["messages"][] = "success";
			return true;
		}
		return false;
	}
	function edit_node($id,$args){
		$this->output['messages'][] = "editing $id";
		$has_label = false;
		$type = null;
		$idIndex = new \Everyman\Neo4j\Index\NodeIndex($this->db,'Ids');
		$node = $idIndex->findOne('id',$id);
		if(!$node){
			$this->output['messages'][] = "could not find node $id";
			return false;
		}
		
		$args = json_decode($args);
		foreach($args as $key=>$value){
			if($key == 'type'){
				$type = $value;
				$has_label = true;
				continue;
			}
			if($node->setProperty($key,$value)){
				$this->output['messages'][] = "$key set to $value";
			}
		}
		if($node->save()){
			$this->output['messages'][] = "node $id saved";
		}
		if($has_label){
			$oldLabel = $node->getLabels()[0];
			$node->removeLabels(array($oldLabel));
			$label = $this->db->makeLabel($type);
			$node->addLabels(array($label));
		}
		return true;
	}
	function collapse_link($from_id,$del_id){
		$this->output["messages"][] = "collapsing link from $from_id to $del_id";
		$from = $this->get_node($from_id);
		$del = $this->get_node($del_id);
		if(!$from || !$del){
			$this->output["messages"][] = "node not found";
			return false;
		}
		$queryTemplate = <<<CYPHER
MATCH (nDel{id:'$del_id'})-[r]-(nFrom)
RETURN nDel,nFrom,r
CYPHER;
		$this->output["messages"][] = "CYPHER query: $queryTemplate";
		$query = new \Everyman\Neo4j\Cypher\Query($this->db,$queryTemplate);
		$result = $query->getResultSet();
		foreach($result as $row){
			$r_del = $row['nDel']; // we are deleting this node
			$r_from = $row['nFrom'];
			$relationship = $row['r'];
			$type = $relationship->getType();
			$r_from_id = $r_from->getProperty('id');
			if($from_id == $r_from_id){
				continue;
			}
			$this->relate_nodes($from_id,$type,$r_from_id);
			$this->output["messages"][] = "remapped $type from $del_id to $r_from_id";
		}
		return $this->delete_node($del_id);
	}
	function create_node($name,$type){
		$type = $this->format_typename($type);
		$this->output['messages'][] = "creating $type $name";
		$test = <<<CYPHER
MATCH (a:$type)
	WHERE a.name = "$name"
RETURN a
LIMIT 1
CYPHER;
		$query = new \Everyman\Neo4j\Cypher\Query($this->db,$test);
		$result = $query->getResultSet();
		$id = '';
		if($result->count() > 0){
			$id = $result->current()['a']->getProperty('id');
			$this->output["messages"][] = "node '$name' ($type) already exists with id $id";
			$this->node_by_id($id);
		}else{
			$node = $this->db->makeNode();
			
			/*	IDs in HTML can't start with a number, and to keep things
			as straightforward as possible, the ID in the page is set
			to be exactly the same as the ID in the database -- hence
			the leading 'n'
			
			the internal ID from Neo4j is (apparently) calculated based
			on an index based on how many nodes there are, which means
			IDs of deleted nodes may be reused? Unclear on that, but in
			an attempt to prevent collisions a timestamp is used. */
			
			$id = 'n'.time().$node->getId();
			
			// $node->setProperty('type',$type);
			$node->setProperty('id',$id);
			$node->setProperty('name',$name);
			if(!$node->save()){
				$this->output['messages'][] = "sum ting wong";
				return false;
			}
			$label = $this->db->makeLabel($type);
			$node->addLabels(array($label));
			// $this->output['message'] = "DEBUG $id: $name $type";
			$idIndex = new \Everyman\Neo4j\Index\NodeIndex($this->db,'Ids');
			$nameIndex = new \Everyman\Neo4j\Index\NodeIndex($this->db,'Names');
			$idIndex->add($node,'id',$id);
			$nameIndex->add($node,'name',$name);
			$this->node_by_id($id);
		}
		return true;
	}
	function relate_nodes($from_id,$relationship,$to_id){
		$idIndex = new \Everyman\Neo4j\Index\NodeIndex($this->db,'Ids');
		$from = $idIndex->findOne('id',$from_id);
		$to = $idIndex->findOne('id',$to_id);
		if($from && $to){
			if($from->relateTo($to,$relationship)->save()){
				$this->output['messages'][] = "success: ($from_id)-[$relationship]->($to_id)";
				return true;
			}
		}
		return false;
	}
	function delete_node($id){
		$queryTemplate = <<<CYPHER
MATCH (n{id:'$id'})
OPTIONAL MATCH (n)-[r]-()
DELETE n,r;
CYPHER;
		$this->output["messages"][] = "deleting node $id and relationships using $queryTemplate";
		$query = new \Everyman\Neo4j\Cypher\Query($this->db,$queryTemplate);
		$results = $query->getResultSet();
		$this->output["results"][] = $results;
		return $this->output["results"];
	}
	function delete_relationship($from_id,$relationship,$to_id){
		// $queryTemplate = "MATCH (a{id:'$from_id'})-[r:`$relationship`]-(b{id:'$to_id'}) DELETE r";
		$queryTemplate = "MATCH (a{id:'$from_id'})-[r]-(b{id:'$to_id'}) DELETE r";
		$this->output["messages"][] = "deleting ($from_id)-[$relationship]-($to_id)";
		$query = new \Everyman\Neo4j\Cypher\Query($this->db,$queryTemplate);
		$results = $query->getResultSet();
		$this->output["results"][] = $results;
		return $this->output["results"];
	}
	function combine_nodes($a,$b){
		$queryTemplate = <<<CYPHER
MATCH (a)
WHERE a.id = '$a'
RETURN a LIMIT 1
CYPHER;
		$this->output["messages"][] = $queryTemplate;
		$query = new \Everyman\Neo4j\Cypher\Query($this->db,$queryTemplate);
		$results = $query->getResultSet();
		$this->output["messages"][] = $results;
		foreach($results as $result){
			$this->output["results"] = $this->array_from_node($result);
		}	
		return $this->output["results"];
	}
	function array_from_paths($paths){
		$rarr = [];
		foreach($paths as $path){
			$previous = null;
			foreach($path as $node){
				$id = $node->getProperty('id');
				if(!isset($rarr[$id])){
					$rarr[$id] = $this->array_from_node($node);
				}
				if($previous){
					$rarr[$previous]['relationships']['result'][$previous.'-'.$id] = $id;
				}
				$previous = $id;
			}
		}
		return $rarr;
	}
	function array_from_node($node){
		return [
			'relationships'=>[],
			'properties'=>[
				'name'=>$node->getProperty('name'),
				'type'=>$node->getLabels()[0]->getName()
			],
			'labels'=>$node->getLabels()			
		];
	}
	function array_from_relationships($rels){
		$this->output["messages"][] = "getting array of nodes from relationships";
		$rarr = [];
		foreach($rels as $row){
			$r = $row;
			$from = $r->getStartNode();
			$to = $r->getEndNode();
			$from_id = $from->getProperty('id');
			$to_id = $to->getProperty('id');
			$rtype = $r->getType();
			if(!isset($rarr[$from_id])){
				$rarr[$from_id] = $this->array_from_node($from);
			}
			if(!isset($rarr[$to_id])){
				$rarr[$to_id] = $this->array_from_node($to);
			}
			if(!isset($rarr[$from_id]['relationships'][$rtype])){
				$rarr[$from_id]['relationships'][$rtype] = [];
			}
			$rarr[$from_id]['relationships'][$rtype][$from_id.'-'.$to_id] = $to_id;
		}
		return $rarr;
	}
	function array_from_path($result){
		$rarr = [];
		foreach($result as $row){
			$numr = $row['r']->count();
			for($i =0;$i<$numr;++$i){
				$r = $row['r']->offsetGet($i);
				$start = $r->getStartNode();
				$end = $r->getEndNode();
				$from_id = $start->getProperty('id');
				$to_id = $end->getProperty('id');
				$rtype = $r->getType();
				if(!isset($rarr[$from_id])){
					$rarr[$from_id] = $this->array_from_node($start);
				}
				if(!isset($rarr[$to_id])){
					$rarr[$to_id] = $this->array_from_node($end);
				}
				if(!isset($rarr[$from_id]['relationships'][$rtype])){
					$rarr[$from_id]['relationships'][$rtype] = [];
				}
				$rarr[$from_id]['relationships'][$rtype][$from_id.'-'.$to_id] = $to_id;
			}
		}
		return $rarr;		
	}
	function array_from_result($result){
		$rarr = [];
		foreach($result as $row){
			$a = $row['a'];
			if(is_object($a)){
				$aid = $a->getProperty('id');
				if(!isset($rarr[$aid])){
					$rarr[$aid] = $this->array_from_node($a);
				}
			}
			$b = $row['b'];
			if(is_object($b)){
				$bid = $b->getProperty('id');
				if(!isset($rarr[$bid])){
					$rarr[$bid] = $this->array_from_node($b);
				}
			}
			if(!is_object($row['r'])){
				continue;
			}
			$numr = $row['r']->count();
			for($i =0;$i<$numr;++$i){
				$r = $row['r']->offsetGet($i);
				$start = $r->getStartNode();
				$end = $r->getEndNode();
				$from_id = $start->getProperty('id');
				$to_id = $end->getProperty('id');
				$rtype = $r->getType();
				if(!isset($rarr[$from_id])){
					$rarr[$from_id] = $this->array_from_node($start);
				}
				if(!isset($rarr[$to_id])){
					$rarr[$to_id] = $this->array_from_node($end);
				}
				if(!isset($rarr[$from_id]['relationships'][$rtype])){
					$rarr[$from_id]['relationships'][$rtype] = [];
				}
				$rarr[$from_id]['relationships'][$rtype][$from_id.'-'.$to_id] = $to_id;
			}
		}
		return $rarr;		
	}
	function format_typename($type){
		return preg_replace('/[^a-zA-Z_]/','',$type);
	}
}