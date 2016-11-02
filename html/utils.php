<?php

$index=getenv('INDEX');
if($index==null) {
  $index='dwc';
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
  if(isset($_SERVER['HTTP_ACCEPTS']) && $_SERVER['HTTP_ACCEPTS']== 'application/json') {
    header('Content-Type: application/json');
    foreach($props as $k=>$v) {
      if(preg_match('/json/',$k)) {
        unset($props[$k]);
      } else if(is_object($props[$k]) || is_array($props[$k])) {
        foreach($props[$k] as $kk=>$vv) {
          if(preg_match('/json/',$kk)) {
            unset($props[$k][$kk]);
          }
        }
      }
    }
    return json_encode($props);
  }

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
  $m->addHelper('json',function($v){ return json_encode($v);});
  $m->addHelper('geojson',function($v){
    if($v != null) {
      return json_encode($v);
    } else {
      return '{"features":[],"type":"FeatureCollection"}';
    }
  });
  $content = $m->render($template,$props);
  return $content;
}

function stats($query=null) {
  $es = es();

  $stats=[];

  $q=['match_all'=> new \StdClass];
  if($query != null && is_string($query)) {
    $q=['query_string'=>['analyze_wildcard'=>false,'query'=>$query]];
  }

  /* taxa count */
  $params = [
    'index'=>INDEX,
    'type'=>'analysis',
    'body'=>[
      'query'=>$q
    ]];
  $stats['accepted_count']=$es->count($params)['count'];

  /* last update */
  $params = ['index'=>INDEX, 'type'=>'analysis',
              'body'=>[
                'size'=>1,
                '_source'=>['timestamp'],
                'query'=>['match_all'=>new \StdClass],
                'sort'=>['timestamp'=>'desc']]];
  $r = $es->search($params)['hits']['hits'][0]['_source']['timestamp'][0] / 1000;
  $stats['last_updated']=date('Y-m-d H:m:s',$r);

  // main stats
  $params = [
    'index'=>INDEX,
    'type'=>'analysis',
    'size'=>0,
    'body'=> [
      'size'=>0,
      'query'=>$q,
      'aggs'=>[
        'categories'=>['terms'=>['field'=>'main-risk-assessment.category','size'=>9]],
        'occs_count'=>['sum'=>['field'=>'occurrences.count']],
        'points_count'=>['sum'=>['field'=>'points.count']],
        'occs_ranges'=>[
         'range'=>[ 
           'field'=>'occurrences.count',
           'ranges'=> [ ["from"=>0,"to"=>1]
                       ,["from"=>1,"to"=>3]
                       ,["from"=>3,"to"=>10]
                       ,["from"=>10,"to"=>100]
                       ,["from"=>100,"to"=>99999]]]],
       'points_ranges'=>[
         'range'=>[ 
           'field'=>'points.count',
           'ranges'=> [ ["from"=>0,"to"=>1]
                       ,["from"=>1,"to"=>3]
                       ,["from"=>3,"to"=>10]
                       ,["from"=>10,"to"=>100]
                       ,["from"=>100,"to"=>99999]]]],
       'clusters_ranges'=>[
         'range'=>[ 
           'field'=>'clusters.all.count',
           'ranges'=> [ ["from"=>0,"to"=>1]
                       ,["from"=>1,"to"=>3]
                       ,["from"=>3,"to"=>10]
                       ,["from"=>10,"to"=>100]
                       ,["from"=>100,"to"=>99999]]]],
       'aoo_ranges'=>[
         'range'=>[ 
           'field'=>'aoo.all.area',
           'ranges'=> [ ["from"=>0,"to"=>1]
                       ,["from"=>1,"to"=>10]
                       ,["from"=>10,"to"=>50]
                       ,["from"=>50,"to"=>100]
                       ,["from"=>100,"to"=>500]
                       ,["from"=>500,"to"=>2000]
                       ,["from"=>2000,"to"=>5000]
                       ,["from"=>5000,"to"=>99999]]]],
       'eoo_ranges'=>[
         'range'=>[ 
           'field'=>'eoo.all.area',
           'ranges'=> [ ["from"=>0,"to"=>1]
                       ,["from"=>1,"to"=>100]
                       ,["from"=>100,"to"=>5000]
                       ,["from"=>500,"to"=>5000]
                       ,["from"=>5000,"to"=>20000]
                       ,["from"=>20000,"to"=>50000]
                       ,["from"=>50000,"to"=>999999]]]]]]]; 

  $r = $es->search($params);

  foreach($r['aggregations'] as $k=>$agg) {
    if(isset($agg['buckets'])) {
      $stats[$k]=[];
      foreach($agg['buckets'] as $value) {
        $key = str_replace("-"," ~ ",str_replace(".0","",strtoupper($value['key'])));
        $q=null;
        if(strpos($key,"~") !== false){
          $partsq=explode(" ~ ",$key);
          $q='(>='.$partsq[0].' AND <'.$partsq[1].')';
        } else {
          $q=$key;
        }
        $stats[$k][]=['label'=>$key,'value'=>$value['doc_count'],'q'=>$q];
      }
    } else if(isset($agg['value'])) {
      $stats[$k]=$agg['value'];
    }
  }

  $stats['not_points_count']=$stats['occs_count']-$stats['points_count'];
  $stats['occurrence_count']=$stats['occs_count'];

  $stats['json']=json_encode($stats);
  return $stats;
}
