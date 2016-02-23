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
            'field'=>'family',
            'size'=>0
          ]
        ]
      ]
    ]
  ];

  $r = $es->search($params);
  $families=[];
  foreach($r['aggregations']['families']['buckets'] as $f) {
    $families[]=strtoupper( $f['key'] );
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
    $spp = $hit['_source'];
    $spp['family']=strtoupper(trim($spp['family']));
    $spps[] = $spp;
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

  $params=['index'=>INDEX,'type'=>'taxon','id'=>$taxon];
  $props['taxon'] = $es->get($params)['_source'];
  $props['title'] = $props['taxon']['scientificName'];

  $stats = ['eoo','eoo_historic','eoo_recent','aoo','aoo_historic','aoo_recent','clusters','clusters_historic','clusters_recent','risk_assessment','count'];
  $props['stats']=$stats;

  foreach($stats as $s) {
    $params=['index'=>INDEX,'type'=>$s,'id'=>$taxon];
    
    try {
      $r = $es->get($params);
      if(isset($r['_source'])) {
        $props[$s]=$r['_source'];
        if(isset($r['_source']['geo'])) {
          $props[$s.'_geojson']=json_encode($r['_source']['geo']);
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
    'type'=>'occurrence',
    'body'=>[
      'size'=> 9999,
      'query'=>[ 'match'=>['scientificNameWithoutAuthorship'=>['query'=> $taxon,'operator'=>'and']]]]];

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

  $es = es();

  $params=[
    'index'=>INDEX,
    'body'=>[
      'size'=> 9999,
      'query'=>['query_string'=>['analyze_wildcard'=>true,'query'=>$q]]]];

  $result = $es->search($params);

  $names=[];
  foreach($result['hits']['hits'] as $hit) {
    $names[] = $hit['_source']['scientificNameWithoutAuthorship'];
  }
  $names = array_unique($names);

  $filter = ['bool'=>['minimum_should_match'=>0,'should'=>[]]];
  foreach($names as $name) {
    $filter['bool']['should'][]=['match'=>['scientificNameWithoutAuthorship'=>['query'=>$name ,'operator'=>'and']]];
  }

  $params=[
    'index'=>INDEX,
    'type'=>'taxon',
    'body'=>[
      'size'=> 9999,
      'filter'=>$filter]];
  $result = $es->search($params);
  $found=[];
  foreach($result['hits']['hits'] as $hit) {
    $found[] = $hit['_source'];
  }
  usort($found,function($a,$b){
    return strcmp($a['scientificName'],$b['scientificName']);
  });

  $props['found']=$found;
  $props['query']=$q;

  $res->getBody()->write(view('search',$props));
  return $res;
});

$app->get("/about",function($req,$res){
  $res->getBody()->write(view('about',[]));
  return $res;
});


$app->get('/lang/{lang}',function($req,$res){
  $_SESSION['lang']=$req->getAttribute('lang');
  header('Location: '.$_SERVER['HTTP_REFERER']);
  exit;
});

$app->run();

