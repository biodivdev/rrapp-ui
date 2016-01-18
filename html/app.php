<?php
include '../vendor/autoload.php';
include 'utils.php';

$app = new \Slim\App;

$app->get('/',function($req,$res) {
  $props=[];
  $es = es();

  $params = [ 'index'=>'biodiv', 'type'=>'taxon' ];
  $props['taxa_count']=$es->count($params)['count'];

  $params = [ 'index'=>'biodiv', 'type'=>'occurrence' ];
  $props['occurrence_count']=$es->count($params)['count'];

  $params = [ 'index'=>'biodiv'];
  $props['analysis_count']=$es->count($params)['count'] - $props['taxa_count'] - $props['occurrence_count'];

  $res->getBody()->write(view('index',$props));
  return $res;
});

$app->get('/families',function($req,$res){
  $props=[];

  $es = es();

  $params = [
    'index'=>'biodiv',
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
    'index'=>'biodiv',
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

  $res->getBody()->write(view('family',$props));
  return $res;
});

$app->get('/taxon/{taxon}',function($req,$res) {
  $props=[];

  $es = es();

  $taxon = urldecode($req->getAttribute('taxon'));

  $params=['index'=>'biodiv','type'=>'taxon','id'=>$taxon];
  $props['taxon'] = $es->get($params)['_source'];
  $props['title'] = $props['taxon']['scientificName'];

  $stats = ['eoo','eoo_historic','eoo_recent','aoo','aoo_historic','aoo_recent','clusters','clusters_historic','clusters_recent','risk_assessment'];
  $props['stats']=$stats;

  foreach($stats as $s) {
    $params=['index'=>'biodiv','type'=>$s,'id'=>$taxon];
    
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
    'index'=>'biodiv',
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
  $q = $_GET['taxon'];

  $es = es();

  $params=[
    'index'=>'biodiv',
    'type'=>'taxon',
    'body'=>[
      'size'=> 9999,
      'query'=>[ 'match'=>['scientificName'=>$q]]]];

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

  $res->getBody()->write(view('search',$props));
  return $res;
});

$app->get("/about",function($req,$res){
  $res->getBody()->write(view('about',[]));
  return $res;
});

$app->run();

