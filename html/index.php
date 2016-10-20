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
    if(trim( $f['key'] ) == "") continue;
    $families[]=trim(strtoupper($f['key']));
  }
  sort($families);
  $props['families']=array_unique($families);

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
            [ 'match'=>[ 'taxonRank'=>'species' ] ],
            [ 'match'=>[ 'family'=>trim($family) ] ] ] ] ] ] ];

  $result = $es->search($params);

  $spps=[];
  $got=[];
  foreach($result['hits']['hits'] as $hit) {
    if(!isset($got[$hit['_source']['scientificNameWithoutAuthorship']])) {
      $spps[] = $hit['_source'];
      $got[$hit['_source']['scientificNameWithoutAuthorship']]=true;
    }
  }
  usort($spps,function($a,$b){
    return strcmp($a['scientificName'],$b['scientificName']);
  });

  $props['species']=$spps;
  $props['stats']=stats("family:".$family);
  $props['query']="family:\"{$family}\"";

  $res->getBody()->write(view('family',$props));
  return $res;
});

$app->get('/taxon/{taxon}',function($req,$res) {
  $props=[];

  $es = es();

  $taxon = urldecode($req->getAttribute('taxon'));

  $params=['index'=>INDEX
          ,'type'=>'analysis'
          ,'body'=> [
            'size'=>1,
            'query'=>[
                'match'=>['scientificNameWithoutAuthorship'=>$taxon]]]];
  $props = $es->search($params)['hits']['hits'][0]['_source'];
  $props['occurrence_count']=$props['occurrences'];
  $props['title'] = $props['scientificName'];

  $params=[
    'index'=>INDEX,
    'type'=>'occurrence',
    'body'=>[
      'size'=> 9999,
      'query'=>[
        'bool'=>[
          'should'=>[]]]]];

  $params['body']['query']['bool']['should'][]=['match'=>['scientificNameWithoutAuthorship'=>['query'=>$props['scientificNameWithoutAuthorship'],'type'=>'phrase']]];
  foreach($props['synonyms'] as $syn) {
    $params['body']['query']['bool']['should'][]=['match'=>['scientificNameWithoutAuthorship'=>['query'=>$syn['scientificNameWithoutAuthorship'],'type'=>'phrase']]];
  }

  $result = $es->search($params);
  $occurrences=[];
  foreach($result['hits']['hits'] as $hit) {
    $occurrences[] = $hit['_source'];
  }
  $props['occurrences']=$occurrences;

  $res->getBody()->write(view('taxon',$props));
  return $res;
});

$app->get('/search',function($req,$res) {
  $q = $_GET['query'];

  $es = es();
  $props=[];

  $params=[
    'index'=>INDEX,
    'type'=>'analysis',
    'body'=>[
      'size'=> 9999,
      'query'=>['query_string'=>['analyze_wildcard'=>false,'query'=>$q]]]];

  $result = $es->search($params);
  $found=[];
  foreach($result['hits']['hits'] as $hit) {
    $found[]=$hit['_source'];
  }
  usort($found,function($a,$b){
    return strcmp($a['scientificName'],$b['scientificName']);
  });

  $props['stats']=stats($q);
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

