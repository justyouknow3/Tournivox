<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
Auth::requireLogin();
Database::ensureFlexibleFormatSchema();
$id=(int)($_GET['tournament_id']??0);
$t=Database::fetch("SELECT * FROM tournaments WHERE id=?",[$id]);
if(!$t || !Auth::canManageTournament($id)){http_response_code(403);exit('Access denied');}
$games=supportedGames();
$formats=['best_of_series'=>'Best-of Series','single_elimination'=>'Single Elimination','double_elimination'=>'Double Elimination','round_robin'=>'Round Robin','swiss'=>'Swiss','group_stage'=>'Group Stage','hybrid'=>'Hybrid Format','gauntlet'=>'Gauntlet','custom'=>'Custom'];
if($_SERVER['REQUEST_METHOD']==='POST' && verifyCSRFToken($_POST['csrf_token']??'')){
 $action=$_POST['action']??'';
 $game=array_key_exists($_POST['game_code']??'', $games)?$_POST['game_code']:$t['game'];
 $settings=['rounds'=>(int)($_POST['rounds']??3),'groups'=>(int)($_POST['groups']??1),'advance'=>(int)($_POST['advance']??2),'allow_rematch'=>!empty($_POST['allow_rematch']),'grand_final_reset'=>!empty($_POST['grand_final_reset'])];
 if($action==='add'){
  Database::insert('tournament_stages',['tournament_id'=>$id,'stage_order'=>(int)($_POST['stage_order']??1),'stage_name'=>trim($_POST['stage_name']??'New Stage'),'game_code'=>$game,'format_type'=>$_POST['format_type']??'custom','best_of'=>$_POST['best_of']??'BO3','settings_json'=>json_encode($settings),'is_enabled'=>1]);
 } elseif($action==='update'){
  $sid=(int)$_POST['stage_id'];
  Database::update('tournament_stages',['stage_order'=>(int)$_POST['stage_order'],'stage_name'=>trim($_POST['stage_name']),'game_code'=>$game,'format_type'=>$_POST['format_type'],'best_of'=>$_POST['best_of'],'settings_json'=>json_encode($settings),'is_enabled'=>!empty($_POST['is_enabled'])?1:0],'id=? AND tournament_id=?',[$sid,$id]);
 } elseif($action==='delete') Database::delete('tournament_stages','id=? AND tournament_id=?',[(int)$_POST['stage_id'],$id]);
 setFlash('success','Stage name, game, and format were saved.'); header('Location: format-manager.php?tournament_id='.$id);exit;
}
$stages=Database::fetchAll("SELECT * FROM tournament_stages WHERE tournament_id=? ORDER BY stage_order,id",[$id]);
$pageTitle='Format Manager'; require_once __DIR__.'/../includes/header.php';
?>
<style>
.stage-hero{background:linear-gradient(135deg,#450011,#171011);border:1px solid rgba(243,182,31,.25);border-radius:18px;padding:22px;margin-bottom:20px}.stage-grid{display:grid;grid-template-columns:90px minmax(220px,2fr) minmax(190px,1fr) minmax(190px,1fr) 130px;gap:14px}.stage-card{border-left:5px solid #f3b61f;box-shadow:0 12px 28px rgba(0,0,0,.12)}.stage-card-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px}.stage-number{display:inline-grid;place-items:center;width:42px;height:42px;border-radius:12px;background:#2a161a;color:#ffd45a;font-weight:800}.save-bar{display:flex;gap:10px;justify-content:flex-end;align-items:center;margin-top:16px}@media(max-width:1100px){.stage-grid{grid-template-columns:1fr 1fr}}@media(max-width:650px){.stage-grid{grid-template-columns:1fr}}
</style>
<div class="page-header"><div><h1>Stage & Game Manager</h1><p><?=sanitize($t['name'])?> — edit each stage name, game, format, and series.</p></div><a class="btn btn-outline-primary" href="<?=APP_URL?>/tournaments/view.php?id=<?=$id?>">Back to Tournament</a></div>
<div class="stage-hero"><h4 class="mb-2">Add a Tournament Stage</h4><p class="mb-3 text-white-50">Stage names are shown in management pages. The bracket columns remain simple: Round 1, Round 2, Round 3, and so on.</p><form method="post" class="stage-grid"><?=csrfField()?><input type="hidden" name="action" value="add">
<label>Order<input class="form-control" type="number" name="stage_order" min="1" value="<?=count($stages)+1?>"></label>
<label>Stage Name<input class="form-control" name="stage_name" placeholder="Example: Main Playoffs" required></label>
<label>Game<select class="form-select" name="game_code"><?php foreach($games as $v=>$l):?><option value="<?=$v?>" <?=$t['game']===$v?'selected':''?>><?=$l?></option><?php endforeach?></select></label>
<label>Format<select class="form-select" name="format_type"><?php foreach($formats as $v=>$l):?><option value="<?=$v?>"><?=$l?></option><?php endforeach?></select></label>
<label>Best Of<select class="form-select" name="best_of"><?php foreach(['BO1','BO2','BO3','BO5','BO7'] as $bo):?><option><?=$bo?></option><?php endforeach?></select></label>
<div style="grid-column:1/-1;text-align:right"><button class="btn btn-primary px-4">Add Stage</button></div></form></div>
<?php foreach($stages as $index=>$s):$cfg=json_decode($s['settings_json']?:'{}',true)?:[];?>
<div class="card p-4 mb-3 stage-card"><div class="stage-card-head"><div class="d-flex gap-3 align-items-center"><span class="stage-number"><?=$index+1?></span><div><h5 class="mb-0"><?=sanitize($s['stage_name'])?></h5><small class="text-muted"><?=sanitize($games[$s['game_code']??$t['game']]??($s['game_code']??$t['game']))?></small></div></div></div>
<form method="post"><?=csrfField()?><input type="hidden" name="stage_id" value="<?=$s['id']?>"><input type="hidden" name="action" value="update"><div class="stage-grid">
<label>Order<input class="form-control" type="number" name="stage_order" min="1" value="<?=$s['stage_order']?>"></label>
<label>Stage Name<input class="form-control" name="stage_name" value="<?=sanitize($s['stage_name'])?>" required></label>
<label>Game<select class="form-select" name="game_code"><?php foreach($games as $v=>$l):?><option value="<?=$v?>" <?=($s['game_code']??$t['game'])===$v?'selected':''?>><?=$l?></option><?php endforeach?></select></label>
<label>Format<select class="form-select" name="format_type"><?php foreach($formats as $v=>$l):?><option value="<?=$v?>" <?=$s['format_type']===$v?'selected':''?>><?=$l?></option><?php endforeach?></select></label>
<label>Best Of<select class="form-select" name="best_of"><?php foreach(['BO1','BO2','BO3','BO5','BO7'] as $bo):?><option <?=$s['best_of']===$bo?'selected':''?>><?=$bo?></option><?php endforeach?></select></label>
<label>Rounds<input class="form-control" type="number" name="rounds" min="1" value="<?=(int)($cfg['rounds']??3)?>"></label>
<label>Groups<input class="form-control" type="number" name="groups" min="1" value="<?=(int)($cfg['groups']??1)?>"></label>
<label>Teams Advancing<input class="form-control" type="number" name="advance" min="1" value="<?=(int)($cfg['advance']??2)?>"></label>
<label class="form-check mt-4"><input class="form-check-input" type="checkbox" name="is_enabled" <?=$s['is_enabled']?'checked':''?>><span class="form-check-label">Enabled</span></label>
</div><div class="save-bar"><button class="btn btn-primary px-4"><i class="bi bi-check2-circle"></i> Save Stage</button></div></form>
<form method="post" class="mt-2" onsubmit="return confirm('Remove this stage?')"><?=csrfField()?><input type="hidden" name="action" value="delete"><input type="hidden" name="stage_id" value="<?=$s['id']?>"><button class="btn btn-sm btn-outline-danger">Remove Stage</button></form></div>
<?php endforeach?>
<?php require_once __DIR__.'/../includes/footer.php';?>
