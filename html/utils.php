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
    $props['strings']=json_decode(file_get_contents(__DIR__.'/../lang/pt.json'));
  }

  $template = file_get_contents(__DIR__.'/../templates/'.$name.'.mustache');
  $m = new \Mustache_Engine(array('partials'=>$partials));
  $content = $m->render($template,$props);
  return $content;
}

function stats($family=null) {
  $es = es();

  $stats=[];

  $q=['match_all'=>[]];
  $filter=[];
  if($family != null) {
    if(is_string($family)) {
      $params=[
        'index'=>INDEX,
        'type'=>'taxon',
        'body'=>[
          'size'=> 9999,
          'fields'=>['scientificNameWithoutAuthorship','synonyms.scientificNameWithoutAuthorship'],
          'query'=>[
            'bool'=>[
              'must'=>[
                [ 'match'=>[ 'taxonomicStatus'=>'accepted' ] ],
                [ 'match'=>[ 'family'=>trim($family) ] ] ] ] ] ] ];
      $result = $es->search($params);

      $names= [];
      foreach($result['hits']['hits'] as $hit) {
        foreach($hit['fields'] as $f) {
          foreach($f as $name) {
            $names[] = $name;
          }
        }
      }

      $filter = ['bool'=>['minimum_should_match'=>0,'should'=>[]]];
      foreach($names as $name) {
        $filter['bool']['should'][]=['match'=>['scientificNameWithoutAuthorship'=>['query'=>$name ,'operator'=>'and']]];
      }
      $q=['filtered'=>['query'=>['match_all'=>[]],'filter'=>$filter]];
    } else if (is_array($family)) {
      $filter = $family;
      $q=['filtered'=>['query'=>['match_all'=>[]],'filter'=>$filter]];
    }
  }

  $params =[
    'index'=>INDEX,
    'type'=>'taxon',
    'body'=>[
      'size'=>0,
      'query'=>$q,
      'aggs'=>[
            'synonyms'=>[
              'value_count'=>['field'=>'synonyms.taxonRank']
            ],
            'accepted'=>[
              'value_count'=>['field'=>'taxonRank']
            ]
      ]
    ]
  ];

  $r = $es->search($params);
  $stats['synonyms_count']=$r['aggregations']['synonyms']['value'];
  $stats['accepted_count']=$r['aggregations']['accepted']['value'];
  $stats['taxa_count']=$stats['synonyms_count']+$stats['accepted_count'];

  $params = [ 'index'=>INDEX, 'type'=>'occurrence','body'=>['query'=>$q]];
  $stats['occurrence_count']=$es->count($params)['count'];

  //$params = [ 'index'=>INDEX,'body'=>[['query']=>$q]];
  //$stats['analysis_count']=$es->count($params)['count'] - $props['taxa_count'] - $props['occurrence_count'];

  $params = ['index'=>INDEX, 'type'=>'taxon',
              'body'=>[
                'size'=>1,
                'fields'=>['timestamp'],
                'query'=>['match_all'=>[]],
                'sort'=>['timestamp'=>'desc']]];
  $r = $es->search($params)['hits']['hits'][0]['fields']['timestamp'][0] / 1000;
  $stats['last_updated']=date('Y-m-d H:m:s',$r);

  $params = [
    'index'=>INDEX,
    'size'=>0,
    'fields'=>[],
    'body'=> [
      'size'=>0,
      'query'=>$q,
      'aggs'=>[
       'categories'=>['terms'=>['field'=>'main-risk-assessment.category','size'=>0]],
       'occs_count'=>['sum'=>['field'=>'occurrences.count']],
       'points_count'=>['sum'=>['field'=>'points.count']],
       'occs_ranges'=>[
         'range'=>[ 
           'field'=>'occurrences.count',
           'ranges'=> [ ["from"=>0,"to"=>1]
                       ,["from"=>1,"to"=>3]
                       ,["from"=>3,"to"=>10]
                       ,["from"=>10,"to"=>100]
                       ,["from"=>100,"to"=>99999]]
         ]],
       'points_ranges'=>[
         'range'=>[ 
           'field'=>'points.count',
           'ranges'=> [ ["from"=>0,"to"=>1]
                       ,["from"=>1,"to"=>3]
                       ,["from"=>3,"to"=>10]
                       ,["from"=>10,"to"=>100]
                       ,["from"=>100,"to"=>99999]]]],
       'clusters'=>[
         'filter'=>['term'=>['_type'=>'clusters']],
         'aggs'=>[
           'clusters_ranges'=>[
             'range'=>[ 
               'field'=>'count',
               'ranges'=> [ ["from"=>0,"to"=>1]
                           ,["from"=>1,"to"=>3]
                           ,["from"=>3,"to"=>10]
                           ,["from"=>10,"to"=>100]
                           ,["from"=>100,"to"=>99999]]]]
         ]],
       'aoo'=>[
         'filter'=>['term'=>['_type'=>'aoo']],
         'aggs'=>[
           'aoo_ranges'=>[
             'range'=>[ 
               'field'=>'area',
               'ranges'=> [ ["from"=>0,"to"=>1]
                           ,["from"=>1,"to"=>10]
                           ,["from"=>10,"to"=>50]
                           ,["from"=>50,"to"=>100]
                           ,["from"=>100,"to"=>500]
                           ,["from"=>500,"to"=>2000]
                           ,["from"=>2000,"to"=>5000]
                           ,["from"=>5000,"to"=>99999]]]]]],
       'eoo'=>[
         'filter'=>['term'=>['_type'=>'eoo']],
         'aggs'=>[
           'eoo_ranges'=>[
             'range'=>[ 
               'field'=>'area',
               'ranges'=> [ ["from"=>0,"to"=>1]
                           ,["from"=>1,"to"=>100]
                           ,["from"=>100,"to"=>5000]
                           ,["from"=>500,"to"=>5000]
                           ,["from"=>5000,"to"=>20000]
                           ,["from"=>20000,"to"=>50000]
                           ,["from"=>50000,"to"=>999999]]]]]],
       ]]]; 

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
    } else{
      foreach($agg as $rek=>$reagg) {
        if(isset($reagg['buckets'])) {
          $stats[$rek]=[];
          foreach($reagg['buckets'] as $value) {
            $key = str_replace("-"," ~ ",str_replace(".0","",strtoupper( $value['key'] )));
            $q=null;
            if(strpos($key,"~") !== false){
              $partsq=explode(" ~ ",$key);
              $q='(>='.$partsq[0].' AND <'.$partsq[1].')';
            } else {
              $q=$key;
            }
            $stats[$rek][]=['label'=>$key,'value'=>$value['doc_count'],'q'=>$q];
          }
        }
      }
    }
  }

  $stats['not_points_count']=$stats['occs_count']-$stats['points_count'];

  $stats['json']=json_encode($stats);
  return $stats;
}
