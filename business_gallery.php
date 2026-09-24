<?php
require __DIR__.'/config.php'; require __DIR__.'/database_upgrade.php'; require __DIR__.'/owner_layout.php';
try{$pdo=db();apply_database_upgrade($pdo);}catch(Throwable $e){http_response_code(503);exit('Gallery is unavailable.');}
if(!is_role('owner')){flash('Business owner access is required.','error');redirect('index.php?page=login');}
$u=user(); $id=(int)($_GET['business']??$_POST['business_id']??0);
$q=$pdo->prepare('SELECT id,name,logo_path FROM businesses WHERE id=? AND owner_id=?');$q->execute([$id,$u['id']]);$business=$q->fetch(); if(!$business){http_response_code(403);exit('You can only manage your own business gallery.');}
if($_SERVER['REQUEST_METHOD']==='POST'){verify_csrf();try{$action=$_POST['action']??'';
 if($action==='upload_logo'){$path=upload_business_image($_FILES['image']??[],$id);$pdo->prepare('UPDATE businesses SET logo_path=? WHERE id=? AND owner_id=?')->execute([$path,$id,$u['id']]);flash('Business logo updated.');}
 if($action==='upload_photo'){$path=upload_business_image($_FILES['image']??[],$id);$pdo->prepare('INSERT INTO business_images(business_id,image_path,caption) VALUES(?,?,?)')->execute([$id,$path,mb_strimwidth(trim($_POST['caption']??''),0,160,'')]);flash('Gallery photo uploaded.');}
 if($action==='delete_photo'){$photo=(int)($_POST['photo_id']??0);$q=$pdo->prepare('SELECT image_path FROM business_images WHERE id=? AND business_id=?');$q->execute([$photo,$id]);$path=$q->fetchColumn();if(!$path){http_response_code(403);exit('Not permitted.');}$pdo->prepare('DELETE FROM business_images WHERE id=? AND business_id=?')->execute([$photo,$id]);$file=__DIR__.'/'.str_replace(['..','\\'],'',$path);if(is_file($file))@unlink($file);flash('Gallery photo deleted.');}
}catch(Throwable $e){flash($e->getMessage(),'error');}redirect('business_gallery.php?business='.$id);}
$q=$pdo->prepare('SELECT * FROM business_images WHERE business_id=? ORDER BY created_at DESC');$q->execute([$id]);$images=$q->fetchAll();
$q=$pdo->prepare('SELECT id FROM businesses WHERE owner_id=? ORDER BY updated_at DESC');$q->execute([$u['id']]);$firstBusiness=(int)$q->fetchColumn();
ob_start();
?>
<section class="panel">
  <div class="panel-title"><div><h2>Logo / profile image</h2><p>Update the image customers see for your business.</p></div><a class="button light" href="owner.php?edit=<?=$id?>">Back to business</a></div>
  <?php if($business['logo_path']):?><img class="business-logo" src="<?=e($business['logo_path'])?>" alt="Current business logo"><?php endif?>
  <form method="post" enctype="multipart/form-data" class="mini-form image-upload"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="upload_logo"><input required type="file" name="image" accept="image/jpeg,image/png,image/webp" data-image-preview><img class="image-preview" hidden alt="Preview"><button class="button coral">Upload logo</button></form>
</section>
<section class="panel">
  <h2>Gallery photos</h2>
  <form method="post" enctype="multipart/form-data" class="mini-form image-upload"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="upload_photo"><input required type="file" name="image" accept="image/jpeg,image/png,image/webp" data-image-preview><img class="image-preview" hidden alt="Preview"><input name="caption" maxlength="160" placeholder="Optional caption"><button class="button coral">Add photo</button></form>
  <div class="gallery-grid"><?php foreach($images as $image):?><figure><img src="<?=e($image['image_path'])?>" alt="<?=e($image['caption']?:'Business gallery photo')?>"><figcaption><?=e($image['caption'])?><form method="post"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="delete_photo"><input type="hidden" name="photo_id" value="<?=$image['id']?>"><button class="danger">Delete</button></form></figcaption></figure><?php endforeach;if(!$images):?><p class="empty">No gallery photos yet.</p><?php endif?></div>
</section>
<?php
owner_page('Gallery', ob_get_clean(), 'gallery', $firstBusiness);
