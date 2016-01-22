<?php

$index=getenv('index');
if($index==null) {
  $index='biodiv';
}
define('INDEX',$index);
function es() {
  $es = getenv('ELASTICSEARCH');
  if($es == null) {
    $es = 'http://elasticsearch:9200';
  }
  $client = \Elasticsearch\ClientBuilder::create()
    ->setHosts([$es])
    ->build();
  return $client;
}

function view($name,$props) {
  $partials = [];
  $iterator = new \DirectoryIterator(__DIR__."/../templates");
  foreach ($iterator as $file) {
    if($file->isFile() && preg_match("/\.mustache$/",$file->getFilename())) {
      $partials[substr( $file->getFilename(),0,-9)] = file_get_contents($file->getPath()."/".$file->getFilename());
    }
  }

  $base = getenv('BASE');
  if($base == null) $base="";
  $props['base']=$base;

  if(isset($_SESSION['lang'])){
    $props['strings']=json_decode(file_get_contents(__DIR__.'/../lang/'.$_SESSION['lang'].'.json'));
  } else {
    $props['strings']=json_decode(file_get_contents(__DIR__.'/../lang/en.json'));
  }

  $template = file_get_contents(__DIR__.'/../templates/'.$name.'.mustache');
  $m = new \Mustache_Engine(array('partials'=>$partials));
  $content = $m->render($template,$props);
  return $content;
}

