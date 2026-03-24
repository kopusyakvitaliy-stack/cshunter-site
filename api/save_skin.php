<?php
header('Content-Type: application/json');
require_once __DIR__.'/../config.php';
require_once __DIR__.'/../includes/auth.php';
if(!isLoggedIn()){echo json_encode(['success'=>false,'error'=>'Not authenticated']);exit;}
$user=getUser();$steamId=$user['steam_id'];
$input=json_decode(file_get_contents('php://input'),true);
if(!$input){echo json_encode(['success'=>false,'error'=>'Invalid input']);exit;}
try{
  $pdo=new PDO("mysql:host=".SHARED_DB_HOST.";dbname=".SHARED_DB_NAME.";charset=utf8mb4",SHARED_DB_USER,SHARED_DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
  if(($input['action']??'')==='reset'){
    $t=intval($input['weapon_team']??3);
    foreach(['wp_player_skins','wp_player_knife','wp_player_gloves','wp_player_music','wp_player_pins'] as $tbl)
      $pdo->prepare("DELETE FROM $tbl WHERE steamid=? AND weapon_team=?")->execute([$steamId,$t]);
    echo json_encode(['success'=>true]);exit;
  }
  $def=intval($input['weapon_defindex']??0);
  $paint=intval($input['weapon_paint_id']??0);
  $wear=max(0.0001,min(1.0,floatval($input['weapon_wear']??0.01)));
  $seed=max(0,min(999,intval($input['weapon_seed']??0)));
  $st=intval($input['weapon_stattrak']??0);
  $team=in_array(intval($input['weapon_team']??3),[2,3])?intval($input['weapon_team']):3;
  $cat=$input['cat']??'weapon';
  $wname=$input['weapon_name']??'';

  if($cat==='knives'){
    $pdo->prepare("INSERT INTO wp_player_knife(steamid,weapon_team,knife)VALUES(?,?,?) ON DUPLICATE KEY UPDATE knife=VALUES(knife)")->execute([$steamId,$team,$wname]);
    $pdo->prepare("INSERT INTO wp_player_skins(steamid,weapon_team,weapon_defindex,weapon_paint_id,weapon_wear,weapon_seed,weapon_stattrak,weapon_stattrak_count)VALUES(?,?,?,?,?,?,?,0) ON DUPLICATE KEY UPDATE weapon_paint_id=VALUES(weapon_paint_id),weapon_wear=VALUES(weapon_wear),weapon_seed=VALUES(weapon_seed),weapon_stattrak=VALUES(weapon_stattrak)")->execute([$steamId,$team,$def,$paint,$wear,$seed,$st]);
  } elseif($cat==='gloves'){
    if($def === 0) {
      // Reset to default — delete glove entry
      $pdo->prepare("DELETE FROM wp_player_gloves WHERE steamid=? AND weapon_team=?")->execute([$steamId,$team]);
    } else {
      $pdo->prepare("INSERT INTO wp_player_gloves(steamid,weapon_team,weapon_defindex)VALUES(?,?,?) ON DUPLICATE KEY UPDATE weapon_defindex=VALUES(weapon_defindex)")->execute([$steamId,$team,$def]);
      if($paint>0) $pdo->prepare("INSERT INTO wp_player_skins(steamid,weapon_team,weapon_defindex,weapon_paint_id,weapon_wear,weapon_seed,weapon_stattrak,weapon_stattrak_count)VALUES(?,?,?,?,?,?,?,0) ON DUPLICATE KEY UPDATE weapon_paint_id=VALUES(weapon_paint_id),weapon_wear=VALUES(weapon_wear),weapon_seed=VALUES(weapon_seed),weapon_stattrak=VALUES(weapon_stattrak)")->execute([$steamId,$team,$def,$paint,$wear,$seed,$st]);
    }
  } elseif($cat==='music'){
    $mid=intval($input['music_id']??0);
    $pdo->prepare("INSERT INTO wp_player_music(steamid,weapon_team,music_id)VALUES(?,?,?) ON DUPLICATE KEY UPDATE music_id=VALUES(music_id)")->execute([$steamId,$team,$mid]);
  } elseif($cat==='agents'){
    $agentModel = $input['agent_model'] ?? 'null';
    // WeaponPaints stores CT/T agents in wp_player_agents
    // Column depends on team: agent_ct (team 3) or agent_t (team 2)
    $col = ($team === 3) ? 'agent_ct' : 'agent_t';
    // Try new schema first (separate columns), fallback to single column
    try {
      $pdo->prepare("INSERT INTO wp_player_agents(steamid,{$col}) VALUES(?,?) ON DUPLICATE KEY UPDATE {$col}=VALUES({$col})")->execute([$steamId, $agentModel]);
    } catch(Throwable $e) {
      // Fallback: try with agent column
      $pdo->prepare("INSERT INTO wp_player_agents(steamid,weapon_team,agent) VALUES(?,?,?) ON DUPLICATE KEY UPDATE agent=VALUES(agent)")->execute([$steamId, $team, $agentModel]);
    }
    $pid=intval($input['pin_id']??0);
    $pdo->prepare("INSERT INTO wp_player_pins(steamid,weapon_team,id)VALUES(?,?,?) ON DUPLICATE KEY UPDATE id=VALUES(id)")->execute([$steamId,$team,$pid]);
  } else {
    $pdo->prepare("INSERT INTO wp_player_skins(steamid,weapon_team,weapon_defindex,weapon_paint_id,weapon_wear,weapon_seed,weapon_stattrak,weapon_stattrak_count)VALUES(?,?,?,?,?,?,?,0) ON DUPLICATE KEY UPDATE weapon_paint_id=VALUES(weapon_paint_id),weapon_wear=VALUES(weapon_wear),weapon_seed=VALUES(weapon_seed),weapon_stattrak=VALUES(weapon_stattrak)")->execute([$steamId,$team,$def,$paint,$wear,$seed,$st]);
  }
  echo json_encode(['success'=>true]);
}catch(Throwable $e){echo json_encode(['success'=>false,'error'=>$e->getMessage()]);}
