<?php
namespace Eintrikot\Community;
if(!defined('ABSPATH'))exit;
add_action('init',function(){
 foreach(array('eintrikot_editor','eintrikot_board','administrator') as $name){$role=get_role($name);if($role&&!$role->has_cap('eintrikot_edit_infos'))$role->add_cap('eintrikot_edit_infos');}
 foreach(array('eintrikot_editor','eintrikot_board') as $name){$role=get_role($name);if($role)foreach(array('edit_posts','edit_published_posts','publish_posts','upload_files') as $cap)if(!$role->has_cap($cap))$role->add_cap($cap);}
 $caps=array_fill_keys(array('edit_post','read_post','delete_post','edit_posts','edit_others_posts','publish_posts','read_private_posts','delete_posts','delete_private_posts','delete_published_posts','delete_others_posts','edit_private_posts','edit_published_posts','create_posts'),'eintrikot_edit_infos');
 foreach(array('et_info'=>'Vereinsinfos','et_calendar'=>'EINTRIKOT Kalender') as $type=>$label)register_post_type($type,array('label'=>$label,'public'=>false,'publicly_queryable'=>false,'exclude_from_search'=>true,'show_ui'=>true,'show_in_rest'=>false,'show_in_nav_menus'=>false,'query_var'=>false,'rewrite'=>false,'capabilities'=>$caps,'map_meta_cap'=>false,'supports'=>array('title','editor'),'menu_icon'=>$type==='et_info'?'dashicons-megaphone':'dashicons-calendar-alt'));
});
add_action('add_meta_boxes_et_calendar',function(){add_meta_box('et-calendar-date','Datum und Wiederholung',__NAMESPACE__.'\calendar_box','et_calendar','side');});
function calendar_box($post){wp_nonce_field('et_calendar_'.$post->ID,'et_calendar_nonce');echo '<p><label>Datum<br><input type="date" name="et_date" required value="'.esc_attr(get_post_meta($post->ID,'et_date',true)).'"></label></p><p><label><input type="checkbox" name="et_annual" value="1" '.checked(get_post_meta($post->ID,'et_annual',true),'1',false).'> Jährlich wiederholen</label></p><p>Veröffentlichte Einträge erscheinen in den Vereinsinfos, sobald sie in den nächsten 30 Tagen liegen.</p>';}
add_action('save_post_et_calendar',function($id){
 if(wp_is_post_revision($id)||(defined('DOING_AUTOSAVE')&&DOING_AUTOSAVE)||!current_user_can('eintrikot_edit_infos')||empty($_POST['et_calendar_nonce']))return;
 if(!is_string($_POST['et_calendar_nonce'])||!wp_verify_nonce($_POST['et_calendar_nonce'],'et_calendar_'.$id))return;
 $date=post_text('et_date','',10);$d=\DateTimeImmutable::createFromFormat('!Y-m-d',$date,wp_timezone());if(!$d||$d->format('Y-m-d')!==$date){delete_post_meta($id,'et_date');return;}
 update_post_meta($id,'et_date',$date);update_post_meta($id,'et_annual',isset($_POST['et_annual'])?'1':'0');
});
function upcoming_date($source,$annual,$today){
 try{$date=new \DateTimeImmutable($source,$today->getTimezone());}catch(\Exception $e){return null;}
 if($annual){$date=$date->setDate((int)$today->format('Y'),(int)$date->format('m'),(int)$date->format('d'));if($date<$today)$date=$date->modify('+1 year');}
 return $date;
}
function calendar_items(){
 $today=new \DateTimeImmutable('today',wp_timezone());$until=$today->modify('+30 days');$items=array();
 foreach(get_posts(array('post_type'=>'et_calendar','post_status'=>'publish','posts_per_page'=>-1)) as $post){$source=get_post_meta($post->ID,'et_date',true);if(!$source)continue;$date=upcoming_date($source,get_post_meta($post->ID,'et_annual',true)==='1',$today);if($date&&$date>=$today&&$date<=$until)$items[]=array('date'=>$date->format('Y-m-d'),'title'=>(get_post_meta($post->ID,'et_annual',true)==='1'&&(int)$date->format('Y')>(int)substr($source,0,4)?((int)$date->format('Y')-(int)substr($source,0,4)).'. Jahrestag · ':'').$post->post_title);}
 foreach(get_users(array('capability'=>'eintrikot_portal','fields'=>'all')) as $user){$data=profile_data($user->ID);if(empty($data['birthday_notice'])||empty($data['birthday']))continue;$date=upcoming_date($data['birthday'],true,$today);if($date&&$date<=$until)$items[]=array('date'=>$date->format('Y-m-d'),'title'=>'Geburtstag: '.$user->display_name);}
 usort($items,fn($a,$b)=>strcmp($a['date'],$b['date']));return $items;
}
function render_infos(){
 if(!member_access())return;echo '<h1>Vereinsinfos</h1>';
 if(current_user_can('eintrikot_edit_infos'))echo '<p><a href="'.esc_url(admin_url('post-new.php?post_type=et_info')).'">Vereinsinfo schreiben</a> · <a href="'.esc_url(admin_url('edit.php?post_type=et_calendar')).'">Kalender pflegen</a></p>';
 $items=calendar_items();if($items){echo '<section><h2>In den nächsten 30 Tagen</h2>';foreach($items as $item)echo '<p><strong>'.esc_html(wp_date('d.m.',strtotime($item['date'].' 12:00:00'))).'</strong> · '.esc_html($item['title']).'</p>';echo '</section>';}
 $page=max(1,absint($_GET['info_page']??1));$query=new \WP_Query(array('post_type'=>'et_info','post_status'=>'publish','posts_per_page'=>10,'paged'=>$page));
 if(!$query->have_posts())echo '<p>Hier erscheinen Mitteilungen aus dem Verein.</p>';
 foreach($query->posts as $post)echo '<article class="et-request"><h2>'.esc_html($post->post_title).'</h2><small>'.esc_html(get_the_date('d.m.Y',$post)).'</small>'.wp_kses_post(apply_filters('the_content',$post->post_content)).'</article>';
 if($page>1)echo '<a href="'.esc_url(portal_url('infos',array('info_page'=>$page-1))).'">← Neuere Infos</a> ';
 if($page<$query->max_num_pages)echo '<a href="'.esc_url(portal_url('infos',array('info_page'=>$page+1))).'">Ältere Infos →</a>';
}
