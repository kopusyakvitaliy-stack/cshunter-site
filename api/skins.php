<?php
header('Content-Type: application/json');
header('Cache-Control: public, max-age=3600');

$type=$_GET['type']??'weapon'; $wk=$_GET['weapon']??''; $di=intval($_GET['defindex']??0); $lang='uk';
$dirs=[__DIR__.'/../data/','/home/container/game/csgo/addons/counterstrikesharp/plugins/WeaponPaints/data/'];
$dir=null; foreach($dirs as $d){if(is_dir($d)){$dir=$d;break;}}
if(!$dir){echo json_encode([]);exit;}

function norm($s){return['paint'=>$s['paint']??null,'name'=>$s['paint_name']??$s['name']??'','paint_name'=>$s['paint_name']??$s['name']??'','img'=>$s['image']??'','image'=>$s['image']??'','weapon_name'=>$s['weapon_name']??'','weapon_defindex'=>$s['weapon_defindex']??0];}
function read($f){return file_exists($f)?json_decode(file_get_contents($f),true)??[]:[]; }

if($type==='music'){
  $a=read($dir.'music_'.$lang.'.json');
  echo json_encode(array_map(fn($s)=>['id'=>$s['id'],'name'=>$s['name'],'img'=>$s['image'],'image'=>$s['image']],$a));exit;
}
if($type==='pins'){
  $a=read($dir.'collectibles_'.$lang.'.json');
  echo json_encode(array_map(fn($s)=>['id'=>$s['id'],'name'=>$s['name'],'img'=>$s['image'],'image'=>$s['image']],$a));exit;
}
if($type==='agents'){
  // Try local en file first (we built it), fallback to plugin data
  $f = $dir.'agents_en.json';
  if(!file_exists($f)) $f = $dir.'agents_'.$lang.'.json';
  $a = read($f);
  $team = intval($_GET['team'] ?? 0);
  if($team) $a = array_values(array_filter($a, fn($ag)=>(int)$ag['team']===$team));
  echo json_encode(array_map(fn($ag)=>[
    'model'=> $ag['model']??'null',
    'name' => $ag['agent_name']??$ag['name']??'',
    'image'=> $ag['image']??'',
    'team' => (int)($ag['team']??0),
  ], $a)); exit;
}
if($type==='gloves'){
  $a=read($dir.'gloves_'.$lang.'.json');
  if($di>0)$a=array_values(array_filter($a,fn($g)=>(int)$g['weapon_defindex']===$di));
  echo json_encode(array_map(fn($g)=>['paint'=>$g['paint'],'name'=>$g['paint_name'],'paint_name'=>$g['paint_name'],'img'=>$g['image'],'image'=>$g['image'],'weapon_defindex'=>$g['weapon_defindex']],array_values($a)));exit;
}
$a=read($dir.'skins_'.$lang.'.json');
$r=array_values(array_filter($a,fn($s)=>$s['weapon_name']===$wk||(int)$s['weapon_defindex']===$di));
echo json_encode(array_map('norm',array_values($r)));
