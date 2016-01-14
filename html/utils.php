<?php

function es() {
  $client = \Elasticsearch\ClientBuilder::create()
    ->setHosts(['http://elasticsearch:9200'])
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

  $template = file_get_contents(__DIR__.'/../templates/'.$name.'.mustache');
  $m = new \Mustache_Engine(array('partials'=>$partials));
  $content = $m->render($template,$props);
  return $content;
}

