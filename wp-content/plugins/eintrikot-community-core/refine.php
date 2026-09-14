<?php
namespace Eintrikot\Community;
if(!defined('ABSPATH'))exit;
function refine_home_blocks($blocks){
 foreach($blocks as &$block){$cls=$block['attrs']['className']??'';
 if($block['blockName']==='core/group'&&($block['attrs']['tagName']??'')==='article'){
  $text=wp_strip_all_tags(serialize_blocks($block['innerBlocks']));$phase=str_contains($text,'Rein ins Trikot.')?'rein':(str_contains($text,'Im Trikot.')?'drin':(str_contains($text,'Raus aus dem Trikot.')?'raus':''));
  if($phase&&!str_contains(serialize_block($block),'phase-icon')){$icon=parse_blocks('<!-- wp:image {"sizeSlug":"full","className":"phase-icon"} --><figure class="wp-block-image size-full phase-icon"><img src="'.esc_url(get_theme_file_uri('assets/phase-'.$phase.'.svg')).'" alt=""/></figure><!-- /wp:image -->')[0];array_unshift($block['innerBlocks'],$icon);array_splice($block['innerContent'],1,0,array(null));}
 }
 if($cls==='section'&&!empty($block['innerBlocks'])&&($block['innerBlocks'][0]['attrs']['className']??'')==='news-head'){$block['attrs']['className']='section news-section';foreach($block['innerContent'] as &$piece)if(is_string($piece))$piece=str_replace('class="wp-block-group section"','class="wp-block-group section news-section"',$piece);unset($piece);}
 if(in_array($cls,array('section intro','section quote'),true)&&!str_contains(serialize_block($block),'et-home-photo')){
  $label=$cls==='section intro'?'Gemeinsam im Nationaltrikot · Bild folgt':'Begegnungen über Generationen · Bild folgt';
  $photo=parse_blocks('<!-- wp:cover {"dimRatio":100,"className":"et-home-photo","style":{"color":{"background":"#f1f1ef"}}} --><div class="wp-block-cover et-home-photo has-background" style="background-color:#f1f1ef"><span aria-hidden="true" class="wp-block-cover__background has-background-dim-100 has-background-dim"></span><div class="wp-block-cover__inner-container"><!-- wp:paragraph --><p>'.$label.'</p><!-- /wp:paragraph --></div></div><!-- /wp:cover -->')[0];$block['innerBlocks'][]=$photo;array_splice($block['innerContent'],count($block['innerContent'])-1,0,array(null));
 }
 if(!empty($block['innerBlocks']))$block['innerBlocks']=refine_home_blocks($block['innerBlocks']);
 }unset($block);return $blocks;
}
function apply_refinements(){
 $home=(int)get_option('page_on_front');$post=get_post($home);if(!$post)return new \WP_Error('home','Startseite fehlt.');$content=serialize_blocks(refine_home_blocks(parse_blocks($post->post_content)));$result=wp_update_post(wp_slash(array('ID'=>$home,'post_content'=>$content)),true);if(is_wp_error($result))return $result;
 foreach(array('impressum'=>'Impressum','datenschutz'=>'Datenschutz') as $slug=>$title){$file=get_theme_file_path('patterns/mvp-'.$slug.'.php');if(!is_readable($file))return new \WP_Error('theme','Theme 0.6.0 fehlt.');$existing=get_page_by_path($slug);$content=explode('?>',file_get_contents($file),2)[1];$id=wp_insert_post(wp_slash(array('ID'=>$existing?$existing->ID:0,'post_type'=>'page','post_status'=>'publish','post_title'=>$title,'post_name'=>$slug,'post_content'=>$content)),true);if(is_wp_error($id))return $id;update_post_meta($id,'_wp_page_template','mvp-public');}
 update_option('eintrikot_refinement_version','0.6.0',false);return true;
}
add_action('admin_menu',function(){add_submenu_page('eintrikot-community-setup','Website verfeinern','Website verfeinern','manage_options','eintrikot-refine',function(){echo '<div class="wrap"><h1>Website verfeinern</h1><p>Ergänzt Phasen-Icons und zwei feste Bildplätze auf der Startseite. Übernimmt das Impressum auf Basis der vorhandenen Website und einen klar gekennzeichneten Datenschutz-Zwischenstand.</p>';if(get_option('eintrikot_refinement_version')==='0.6.0'){echo '<p>Version 0.6.0 ist übernommen.</p></div>';return;}echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('et_refine');echo '<input type="hidden" name="action" value="et_refine">';submit_button('Verbesserungen 0.6.0 übernehmen');echo '</form></div>';});});
add_action('admin_post_et_refine',function(){if(!current_user_can('manage_options'))wp_die('Keine Berechtigung.','',array('response'=>403));check_admin_referer('et_refine');if(get_option('eintrikot_refinement_version')!=='0.6.0'){$r=apply_refinements();if(is_wp_error($r))wp_die(esc_html($r->get_error_message()));}wp_safe_redirect(home_url('/'));exit;});
