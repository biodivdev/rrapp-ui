<?php
session_start();
include '../vendor/autoload.php';
include 'utils.php';

$app = new \Slim\App;

$app->get('/',function($req,$res) {
  $props=[];
  $es = es();

  $params = [ 'index'=>INDEX, 'type'=>'taxon' ];
    try {
      $props['taxa_count']=$es->count($params)['count'];
    }catch(Exception $e) {
      var_dump($e->getMessage());exit;
    }

  $params = [ 'index'=>INDEX, 'type'=>'occurrence' ];
  $props['occurrence_count']=$es->count($params)['count'];

  $params = [ 'index'=>INDEX];
  $props['analysis_count']=$es->count($params)['count'] - $props['taxa_count'] - $props['occurrence_count'];

  $params = [
    'index'=>INDEX,
    'size'=>0,
    'fields'=>[],
    'body'=> [
      'size'=>0,
      'query'=>['query_string'=>['analyze_wildcard'=>true,'query'=>'*']],
      'aggs'=>[
       'categories'=>['terms'=>[ 'field'=>'risk-assessment.category','size'=>0]],
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
                           ,["from"=>100,"to"=>500]
                           ,["from"=>500,"to"=>5000]
                           ,["from"=>5000,"to"=>20000]
                           ,["from"=>20000,"to"=>50000]
                           ,["from"=>50000,"to"=>999999]]]]]],
       ]]]; 

   try {
     $r = $es->search($params);
   } catch(Exception $e) {
     var_dump($e);
     $r['aggregations']=[];
   }

  $stats=[];
  foreach($r['aggregations'] as $k=>$agg) {
    if(isset($agg['buckets'])) {
      $stats[$k]=[];
      foreach($agg['buckets'] as $value) {
        $stats[$k][]=['label'=>strtoupper($value['key']),'value'=>$value['doc_count']];
      }
    } else if(isset($agg['value'])) {
      $stats[$k]=$agg['value'];
    } else{
      foreach($agg as $rek=>$reagg) {
        if(isset($reagg['buckets'])) {
          $stats[$rek]=[];
          foreach($reagg['buckets'] as $value) {
            $stats[$rek][]=['label'=>strtoupper($value['key']),'value'=>$value['doc_count']];
          }
        }
      }
    }
  }

  $props['stats']=$stats;
  $props['stats_json']=json_encode($stats);

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

  $found=[];
  foreach($result['hits']['hits'] as $hit) {
    $found[] = $hit['_source']['scientificNameWithoutAuthorship'];
  }
  $found = array_unique($found);
  sort($found);

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

