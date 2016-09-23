<?php
session_start();
include '../vendor/autoload.php';
include 'utils.php';

$configuration = [
  'settings' => [
    'displayErrorDetails' => true,
  ],
];

$app = new \Slim\App($configuration);

$app->get('/',function($req,$res) {
  $props=[];

  $props['stats']=stats();

  $res->getBody()->write(view('index',$props));
  return $res;
});

$app->get('/about',function($req,$res) {
  $props=[];

  $res->getBody()->write(view('about',$props));
  return $res;
});

$app->get("/stats.json",function($req,$res){
  header("Content-Type: application/json");

  $res->getBody()->write(json_encode(stats()));

  return $res;
});

$app->get('/families',function($req,$res){
  $props=[];

  $es = es();

  $params = [
    'index'=>INDEX,
    'type'=>'taxon',
    'size'=>0,
    'fields'=>[],
    'body'=> [
      'aggs'=>[
        'families'=>[
          'terms'=>[
            'field'=>'family.raw',
            'size'=>0
          ]
        ]
      ]
    ]
  ];

  $r = $es->search($params);
  $families=[];
  foreach($r['aggregations']['families']['buckets'] as $f) {
    if(trim( $f['key'] ) == "") continue;
    $families[]=strtoupper($f['key']);
  }
  sort($families);
  $props['families']=$families;

  $res->getBody()->write(view('families',$props));
  return $res;
});

$app->get('/family/{family}',function($req,$res){
  $family = $req->getAttribute('family');
  $props['family']=strtoupper( $family );
  $props['title'] = strtoupper( $family );

  $es = es();

  $params=[
    'index'=>INDEX,
    'type'=>'taxon',
    'body'=>[
      'size'=> 9999,
      'query'=>[
        'bool'=>[
          'must'=>[
            [ 'match'=>[ 'taxonomicStatus'=>'accepted' ] ],
            [ 'match'=>[ 'family'=>trim($family) ] ] ] ] ] ] ];

  $result = $es->search($params);

  $spps=[];
  foreach($result['hits']['hits'] as $hit) {
    $spps[] = $hit['_source'];
  }
  usort($spps,function($a,$b){
    return strcmp($a['scientificName'],$b['scientificName']);
  });

  $props['species']=$spps;
  $props['stats']=stats($family);

  $res->getBody()->write(view('family',$props));
  return $res;
});

$app->get('/taxon/{taxon}',function($req,$res) {
  $props=[];

  $es = es();

  $taxon = urldecode($req->getAttribute('taxon'));

  $params=['index'=>INDEX
          ,'type'=>'taxon'
          ,'body'=> [
            'query'=>[
              'bool'=>[
                'must'=> [
                  ['term'=>[ 'scientificNameWithoutAuthorship.raw'=>$taxon]]
                  ,['term'=>[ 'taxonomicStatus'=>'accepted']]]]]]];
  $props['taxon'] = $es->search($params)['hits']['hits'][0]['_source'];
  $props['title'] = $props['taxon']['scientificName'];
  $props['taxon']['synonyms']=[];

  $stats = ['eoo',
            'eoo_historic',
            'eoo_recent',
            'aoo_variadic',
            'aoo_variadic_historic',
            'aoo_variadic_recent',
            'aoo',
            'aoo_historic',
            'aoo_recent',
            'clusters',
            'clusters_historic',
            'clusters_recent',
            'risk_assessment',
            'count'];

  foreach($stats as $s) {
    $params=['index'=>INDEX,'type'=>$s,'id'=>$taxon];
    
    try {
      $r = $es->get($params);
      if(isset($r['_source'])) {
        $props[$s]=$r['_source'];
        if(isset($r['_source']['geo'])) {
          $props[$s.'_geojson']=json_encode($r['_source']['geo']);
          if(preg_match("/^aoo/",$s)) {
            $nfeats = count($r['_source']['geo']['features']);
            if($nfeats > 0) {
              $props[$s]['step'] =  $r['_source']['area'] / $nfeats;
            } else {
              $props[$s]['step']=0;
            }
          }
        }
        if(isset($r['_source']['area'])) {
          if(is_float($r['_source']['area'])) {
            $props[$s]['area']=number_format($r['_source']['area'],2);
          }
        }
      } else {
        $props[$s]=false;
      }
    }catch(Exception $e) {
      $props[$s]=false;
    }
  }

  $params=[
    'index'=>INDEX,
    'type'=>'taxon',
    'body'=>[
      'size'=> 9999,
      'fields'=>['scientificNameWithoutAuthorship'],
      'query'=>['match'=>['acceptedNameUsage'=>['query'=>$taxon,'type'=>'phrase']]]]];

  $result = $es->search($params);

  $params=[
    'index'=>INDEX,
    'type'=>'occurrence',
    'body'=>[
      'size'=> 9999,
      'query'=>[
        'bool'=>[
          'should'=>[]]]]];

  $names=[];
  foreach($result['hits']['hits'] as $hit) {
    foreach($hit['fields'] as $f) {
      foreach($f as $name) {
        if($name != $props['taxon']['scientificNameWithoutAuthorship'])
          $props[ 'taxon' ]['synonyms'][] = $name;
        $params['body']['query']['bool']['should'][]=['match'=>['scientificNameWithoutAuthorship'=>['query'=>$name,'type'=>'phrase']]];
      }
    }
  }

  $result = $es->search($params);
  $occurrences=[];
  foreach($result['hits']['hits'] as $hit) {
    $occ = $hit['_source'];
    $occurrences[] = $occ;
  }
  $props['occurrences']=$occurrences;
  $props['occurrences_json']=json_encode($occurrences);

  $res->getBody()->write(view('taxon',$props));
  return $res;
});

$app->get('/search',function($req,$res) {
  $q = $_GET['query'];
  $t=time();

  $es = es();

  $params=[
    'index'=>INDEX,
    'body'=>[
      'size'=> 9999,
      '_source'=>['scientificNameWithoutAuthorship'],
      'query'=>['query_string'=>['analyze_wildcard'=>true,'query'=>$q]]]];

  $result = $es->search($params);

  $names=[];
  foreach($result['hits']['hits'] as $hit) {
    $names[] = $hit['_source']['scientificNameWithoutAuthorship'];
  }
  $names = array_unique($names);

  $filter = ['bool'=>['should'=>[]]];
  foreach($names as $name) {
    $filter['bool']['should'][]=['match'=>['scientificNameWithoutAuthorship.raw'=>$name]];
  }

  $params=[
    'index'=>INDEX,
    'type'=>'taxon',
    'body'=>[
      'size'=> 9999,
      'query'=>['filtered'=>['query'=>['match_all'=>[]],'filter'=>$filter]]]];
  $result = $es->search($params);
  $found=[];
  foreach($result['hits']['hits'] as $hit) {
    $found[] = $hit['_source'];
  }
  usort($found,function($a,$b){
    return strcmp($a['scientificName'],$b['scientificName']);
  });

  $props['stats']=stats($filter);

  $props['found']=$found;
  $props['query']=$q;

  $res->getBody()->write(view('search',$props));
  return $res;
});

$app->get('/lang/{lang}',function($req,$res){
  $_SESSION['lang']=$req->getAttribute('lang');
  header('Location: '.$_SERVER['HTTP_REFERER']);
  exit;
});

$app->add(function($req,$res,$next) {
  // CORS
  if(isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Headers: X-Requested-With');
  }

  if($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
      header("Access-Control-Allow-Methods: GET, POST, OPTIONS");         
    }
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
      header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    }
    exit(0);
  }

  $response = $next($req,$res);

  return $response;

});

$app->run();

