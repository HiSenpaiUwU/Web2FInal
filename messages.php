<?php
require __DIR__.'/config.php'; require __DIR__.'/database_upgrade.php'; require __DIR__.'/owner_layout.php'; require __DIR__.'/customer_layout.php';
try{$pdo=db();apply_database_upgrade($pdo);}catch(Throwable $e){http_response_code(503);exit('Messaging is unavailable. Please try again later.');}
if(!logged_in()||!in_array(user()['role'],['customer','owner'],true)){flash('Please log in to use messages.','error');redirect('index.php?page=login');}
$u=user();$isOwner=is_role('owner');
if($_SERVER['REQUEST_METHOD']==='POST'){verify_csrf();$id=(int)($_POST['conversation_id']??0);$text=trim($_POST['message']??'');if($text===''||mb_strlen($text)>1000){flash('Write a message of up to 1,000 characters.','error');redirect('messages.php?conversation='.$id);}$q=$pdo->prepare('SELECT * FROM conversations WHERE id=? AND '.($isOwner?'owner_id':'customer_id').'=?');$q->execute([$id,$u['id']]);$conversation=$q->fetch();if(!$conversation){http_response_code(403);exit('Not permitted.');}$receiver=$isOwner?$conversation['customer_id']:$conversation['owner_id'];$pdo->prepare('INSERT INTO messages(conversation_id,sender_id,receiver_id,message)VALUES(?,?,?,?)')->execute([$id,$u['id'],$receiver,$text]);$pdo->prepare('UPDATE conversations SET updated_at=NOW() WHERE id=?')->execute([$id]);$pdo->prepare('INSERT INTO notifications(user_id,message)VALUES(?,?)')->execute([$receiver,'You have a new message in Davao Local.']);flash('Message sent.');redirect('messages.php?conversation='.$id);}
$where=$isOwner?'cv.owner_id=?':'cv.customer_id=?';$q=$pdo->prepare('SELECT cv.*,b.name business,cu.name customer,ow.name owner,(SELECT message FROM messages m WHERE m.conversation_id=cv.id ORDER BY m.id DESC LIMIT 1) latest,(SELECT COUNT(*) FROM messages m WHERE m.conversation_id=cv.id AND m.receiver_id=? AND m.read_at IS NULL) unread FROM conversations cv JOIN businesses b ON b.id=cv.business_id JOIN users cu ON cu.id=cv.customer_id JOIN users ow ON ow.id=cv.owner_id WHERE '.$where.' ORDER BY cv.updated_at DESC');$q->execute([$u['id'],$u['id']]);$conversations=$q->fetchAll();$selected=(int)($_GET['conversation']??($conversations[0]['id']??0));$current=null;foreach($conversations as $c)if($c['id']===$selected)$current=$c;if($current){$pdo->prepare('UPDATE messages SET read_at=NOW() WHERE conversation_id=? AND receiver_id=? AND read_at IS NULL')->execute([$selected,$u['id']]);$q=$pdo->prepare('SELECT m.*,u.name sender FROM messages m JOIN users u ON u.id=m.sender_id WHERE m.conversation_id=? ORDER BY m.id');$q->execute([$selected]);$thread=$q->fetchAll();}
$unreadQuery=$pdo->prepare('SELECT COUNT(*) FROM messages WHERE receiver_id=? AND read_at IS NULL');$unreadQuery->execute([$u['id']]);$unreadMessages=(int)$unreadQuery->fetchColumn();
if($isOwner){$businessQuery=$pdo->prepare('SELECT id FROM businesses WHERE owner_id=? ORDER BY updated_at DESC');$businessQuery->execute([$u['id']]);$firstBusiness=(int)$businessQuery->fetchColumn();}
ob_start();
?>
<?php if($f=take_flash()):?><div class="flash <?=e($f[1])?>"><?=e($f[0])?></div><?php endif?>
<section class="message-layout">
  <aside class="conversation-list">
    <?php foreach($conversations as $c):?><a class="<?=$current&&$current['id']===$c['id']?'selected':''?>" href="?conversation=<?=$c['id']?>"><b><?=e($isOwner?$c['customer']:$c['business'])?></b><span><?=e(mb_strimwidth($c['latest']??'No messages yet',0,55,'…'))?></span><small><?=e(date('M j, g:i A',strtotime($c['updated_at'])))?><?=$c['unread']?' · '.$c['unread'].' new':''?></small></a><?php endforeach;if(!$conversations):?><p class="empty">No conversations yet.</p><?php endif?>
  </aside>
  <section class="thread">
    <?php if($current):?><header><b><?=e($isOwner?$current['customer']:$current['business'])?></b><small><?=e($isOwner?$current['business']:'Business owner')?></small></header><div class="thread-messages"><?php foreach($thread as $m):?><div class="message <?=$m['sender_id']==$u['id']?'mine':''?>"><p><?=e($m['message'])?></p><small><?=e(date('M j, g:i A',strtotime($m['created_at'])))?><?=$m['sender_id']==$u['id']?($m['read_at']?' · Read':' · Sent'):''?></small></div><?php endforeach?></div><form method="post" class="message-compose"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="conversation_id" value="<?=$current['id']?>"><textarea required maxlength="1000" name="message" placeholder="Type a message..."></textarea><button class="button coral">Send</button></form><?php else:?><div class="empty">Select a conversation to read and reply.</div><?php endif?>
  </section>
</section>
<?php
$content=ob_get_clean();
if($isOwner)owner_page('Messages',$content,'messages',$firstBusiness);
customer_page('Messages',$content,$unreadMessages,'','messages');
