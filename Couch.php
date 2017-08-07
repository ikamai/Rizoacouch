<?php
namespace App\Plugins\RizoaCouch;

final class Couch {

  protected $dbserver;
  protected $database;

  public function __construct($dbserver,$database){
    $this->dbserver       = $dbserver;
    $this->database       = $database;
  }

	/*
		Delete document
	*/
  function delete($data,$dbname=''){
    if($dbname!==''){
      return 	shell_exec('curl -X DELETE http://'.$this->dbserver.':5984/'.$dbname.'/'.$data['_id'].'?rev='.$data['_rev']);
    }else{
      return 	shell_exec('curl -X DELETE http://'.$this->dbserver.':5984/'.$this->database.'/'.$data['_id'].'?rev='.$data['_rev']);
    }
	}

	/*
		Compact database
	*/
	function compact($dbname){

		$r = shell_exec('curl -H "Content-Type: application/json" -X POST http://'.$this->dbserver.':5984/'.$dbname.'/_compact');
		return json_decode($r);

	}

	/*
		Create Database
		- auto insert _design
	*/
  function create($dbname,$design='./../app/plugins/RizoaCouch/views'){

		$r 			= $this->send('PUT','/'.$dbname);
		$result = '';
		if(isset($r->ok)){

			$result = [
				'status' => 'success',
				'msg'		 => 'database '.$dbname.' created'
			];

			/** Sisan Gawe Edit */
			$item = [
				'_id' 			=> '_design/edit',
				'language'	=> 'javascript',
				'updates'		=> [
					'edit'				=> 'function (doc, req) { var fields = JSON.parse(req.body); for (var i in fields) { doc[i] = fields[i] } var resp = eval(uneval(doc)); delete resp._revisions; return [doc, toJSON(resp)]; }'
				]
			];
			$this->send('PUT','/'.$dbname.'/_design/edit',$item);


		}else{
			$result = [
				'status' => $r->error,
				'msg'		 => $r->reason
			];
		}

		/*
			auto insert _design
		*/
		if($design==''){

		}else{

			/*
				lets insert
			*/
			$e = $this->scandir($design);
			foreach($e as $f){
				$this->send('PUT','/'.$dbname.'/_design/'.$f,json_decode(file_get_contents($design.'/'.$f)));
			}

		}

		return $result;

	}

  /*
    GET documents
  */
  function get($view,$arr,$dbname=''){

    if($dbname==''){
      $dbname = $this->database;
    }else{
      $dbname = $dbname;
    }

    $md = '';
    foreach($arr as $k=>$v){
      $md .= $k.'='.$v.'&';
    }
    //echo "\n".'http://localhost:5984/'.$this->database.'/_design/'.$view.'/_view/'.$view.'?'.$md;
    //die();
    $data = $this->send('GET','/'.$dbname.'/_design/'.$view.'/_view/'.$view.'?'.$md);
    $mt 	= array();
    if(isset($data->rows[0])){

      if(isset($arr['include_docs'])){
        if($arr['include_docs']=='true'){
          foreach($data->rows as $r){
            //if(isset($r->key)){
            //	$r->doc->_value = ;
            //}
            $r->doc->value_ = $r->key;
            $mt[] =	$r->doc;
          }
        }else{
          $mt = $data->rows;
        }
      }else{
        $mt = $data->rows;
      }

    }
    return $mt;

  }

  /*
    Create Document
  */
  function put($data,$dbname=''){
    if($dbname!==''){
      $dat       = $this->send('PUT','/'.$dbname.'/'.$data['_id'],$data);
    }else{
      $dat       = $this->send('PUT','/'.$this->database.'/'.$data['_id'],$data);
    }
    $dat->data = $data;
    return $dat;
  }

	/*
		Edit document
	*/
  function edit($data,$dbname=''){

    if($dbname!==''){
      $i = $this->send('GET','/'.$dbname.'/'.$data['_id']);
    }else{
      $i = $this->send('GET','/'.$this->database.'/'.$data['_id']);
    }
		$d = [];

		if($i){
			$d['_rev'] = $i->_rev;
			foreach($data as $c=>$v){
				$d[$c] = $v;
			}
		}
    unset($d['value_']);
    //print_r($d);die();

    if($dbname!==''){
      $data = $this->send('POST','/'.$dbname.'/_design/edit/_update/edit/'.$data['_id'],$d);
    }else{
      $data = $this->send('POST','/'.$this->database.'/_design/edit/_update/edit/'.$data['_id'],$d);
    }
		return $data;

	}

  /*
    General couchdb wrapper
  */
  function send($method, $url, $post_data = NULL) {

    $post_data = json_encode($post_data);
    $host = $this->dbserver;
    $port = '5984';
    $s = fsockopen($host, $port, $errno, $errstr);
    if(!$s) {
       //echo "$errno: $errstr\n";
       //return false;
    }
    $request = "$method $url HTTP/1.0\r\nHost: $host\r\n";
    if($post_data) {
     $request .= "Content-Length: ".strlen($post_data)."\r\n\r\n";
     $request .= "$post_data\r\n";
    }
    else {
     $request .= "\r\n";
    }
    fwrite($s, $request);
    $response = "";
    while(!feof($s)) {
      $response .= fgets($s);
    }

    list($a,$b) = explode("\r\n\r\n", $response);
    return json_decode($b);
  }

  function scandir($dir){

    $ignored = array('.', '..');
    $files = array();
    foreach (scandir($dir) as $file) {
      if (in_array($file, $ignored)) continue;
      $files[$file] = filemtime($dir . '/' . $file);
    }
    arsort($files);
    $files = array_keys($files);
    foreach($files as $d){
      $ds[] = str_replace('.php','',$d);
    }
    return $ds;

  }
}
