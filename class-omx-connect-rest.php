<?php
if (!defined('ABSPATH')) exit;
class OMX_Connect_REST {
    public function __construct(){ add_action('rest_api_init',[$this,'routes']); }
    public function routes(){
        $pub='__return_true'; $adm=[$this,'can_manage'];
        register_rest_route('omx-connect/v1','/conversation',['methods'=>'POST','callback'=>[$this,'create_conversation'],'permission_callback'=>$pub]);
        register_rest_route('omx-connect/v1','/conversation/(?P<public_id>[a-zA-Z0-9\-_]+)',['methods'=>'GET','callback'=>[$this,'get_public_conversation'],'permission_callback'=>$pub]);
        register_rest_route('omx-connect/v1','/message',['methods'=>'POST','callback'=>[$this,'send_customer_message'],'permission_callback'=>$pub]);
        register_rest_route('omx-connect/v1','/upload',['methods'=>'POST','callback'=>[$this,'customer_upload'],'permission_callback'=>$pub]);
        register_rest_route('omx-connect/v1','/admin/conversations',['methods'=>'GET','callback'=>[$this,'admin_conversations'],'permission_callback'=>$adm]);
        register_rest_route('omx-connect/v1','/admin/conversation/(?P<id>\d+)',['methods'=>'GET','callback'=>[$this,'admin_conversation'],'permission_callback'=>$adm]);
        register_rest_route('omx-connect/v1','/admin/reply',['methods'=>'POST','callback'=>[$this,'admin_reply'],'permission_callback'=>$adm]);
        register_rest_route('omx-connect/v1','/admin/update',['methods'=>'POST','callback'=>[$this,'admin_update'],'permission_callback'=>$adm]);
        register_rest_route('omx-connect/v1','/admin/canned',['methods'=>'GET','callback'=>[$this,'canned'],'permission_callback'=>$adm]);
        register_rest_route('omx-connect/v1','/admin/agents',['methods'=>'GET','callback'=>[$this,'agents'],'permission_callback'=>$adm]);
        register_rest_route('omx-connect/v1','/admin/stats',['methods'=>'GET','callback'=>[$this,'stats'],'permission_callback'=>$adm]);
        register_rest_route('omx-connect/v1','/admin/meta',['methods'=>'GET','callback'=>[$this,'meta'],'permission_callback'=>$adm]);
    }
    public function can_manage(){ return current_user_can('edit_posts'); }
    private function body($v){ return wp_kses_post(wp_unslash((string)$v)); }
    private function conv_by_pid($pid){ global $wpdb; $t=OMX_Connect_DB::tables(); return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['conversations']} WHERE public_id=%s",sanitize_text_field($pid)),ARRAY_A); }
    public function create_conversation(WP_REST_Request $r){
        global $wpdb; $t=OMX_Connect_DB::tables(); $pid=wp_generate_uuid4();
        $wpdb->insert($t['conversations'],['public_id'=>$pid,'customer_name'=>sanitize_text_field($r['name']??''),'customer_email'=>sanitize_email($r['email']??''),'customer_phone'=>sanitize_text_field($r['phone']??'')]);
        return rest_ensure_response(['id'=>(int)$wpdb->insert_id,'public_id'=>$pid]);
    }
    public function get_public_conversation(WP_REST_Request $r){
        global $wpdb; $t=OMX_Connect_DB::tables(); $c=$this->conv_by_pid($r['public_id']);
        if(!$c) return new WP_Error('not_found','Conversation not found',['status'=>404]);
        $m=$wpdb->get_results($wpdb->prepare("SELECT id,sender_type,message_type,body,attachment_url,attachment_name,created_at FROM {$t['messages']} WHERE conversation_id=%d AND message_type='message' ORDER BY id ASC",$c['id']),ARRAY_A);
        $wpdb->update($t['conversations'],['unread_customer'=>0],['id'=>$c['id']]);
        return rest_ensure_response(['conversation'=>['id'=>$c['id'],'status'=>$c['status']],'messages'=>$m]);
    }
    public function send_customer_message(WP_REST_Request $r){
        global $wpdb; $t=OMX_Connect_DB::tables(); $c=$this->conv_by_pid($r['public_id']??''); $body=trim($this->body($r['body']??''));
        if(!$c) return new WP_Error('not_found','Conversation not found',['status'=>404]); if(!$body) return new WP_Error('empty','Message is required',['status'=>400]);
        if(false===$wpdb->insert($t['messages'],['conversation_id'=>$c['id'],'sender_type'=>'customer','message_type'=>'message','body'=>$body])) return new WP_Error('save_failed','Message could not be saved',['status'=>500]);
        $updated=$wpdb->query($wpdb->prepare("UPDATE {$t['conversations']} SET status='open', unread_agent=unread_agent+1, last_message_at=%s, updated_at=%s WHERE id=%d",current_time('mysql'),current_time('mysql'),$c['id']));
        if(false===$updated) return new WP_Error('status_failed','Message saved but status update failed',['status'=>500]);
        return rest_ensure_response(['ok'=>true,'status'=>'open']);
    }
    public function customer_upload(WP_REST_Request $r){
        global $wpdb; $t=OMX_Connect_DB::tables(); $c=$this->conv_by_pid($r->get_param('public_id'));
        if(!$c) return new WP_Error('not_found','Conversation not found',['status'=>404]);
        $files=$r->get_file_params(); if(empty($files['file'])) return new WP_Error('no_file','No file uploaded',['status'=>400]);
        require_once ABSPATH.'wp-admin/includes/file.php'; require_once ABSPATH.'wp-admin/includes/media.php'; require_once ABSPATH.'wp-admin/includes/image.php';
        $file=$files['file']; if((int)$file['size']>5*1024*1024) return new WP_Error('too_large','Maximum file size is 5 MB',['status'=>400]);
        $allowed=['image/jpeg','image/png','image/gif','image/webp','application/pdf']; if(!in_array($file['type'],$allowed,true)) return new WP_Error('type','Only images and PDF files are allowed',['status'=>400]);
        $upload=wp_handle_upload($file,['test_form'=>false]); if(isset($upload['error'])) return new WP_Error('upload',$upload['error'],['status'=>400]);
        $name=sanitize_file_name($file['name']);
        if(false===$wpdb->insert($t['messages'],['conversation_id'=>$c['id'],'sender_type'=>'customer','message_type'=>'message','body'=>'Attachment: '.$name,'attachment_url'=>esc_url_raw($upload['url']),'attachment_name'=>$name])) return new WP_Error('save_failed','Attachment uploaded but message could not be saved',['status'=>500]);
        $updated=$wpdb->query($wpdb->prepare("UPDATE {$t['conversations']} SET status='open', unread_agent=unread_agent+1, last_message_at=%s, updated_at=%s WHERE id=%d",current_time('mysql'),current_time('mysql'),$c['id']));
        if(false===$updated) return new WP_Error('status_failed','Attachment saved but status update failed',['status'=>500]);
        return rest_ensure_response(['ok'=>true,'status'=>'open','url'=>$upload['url'],'name'=>$name]);
    }
    public function admin_conversations(WP_REST_Request $r){
        global $wpdb; $t=OMX_Connect_DB::tables(); $clauses=[]; $args=[];
        foreach(['status','priority','department'] as $f){ $v=sanitize_text_field($r->get_param($f)?:''); if($v){$clauses[]="c.$f=%s";$args[]=$v;} }
        $q=sanitize_text_field($r->get_param('q')?:''); if($q){$like='%'.$wpdb->esc_like($q).'%';$clauses[]="(c.customer_name LIKE %s OR c.customer_email LIKE %s OR c.customer_phone LIKE %s OR c.tags LIKE %s OR EXISTS(SELECT 1 FROM {$t['messages']} sx WHERE sx.conversation_id=c.id AND sx.body LIKE %s))"; array_push($args,$like,$like,$like,$like,$like);}
        $where=$clauses?'WHERE '.implode(' AND ',$clauses):''; $sql="SELECT c.*,u.display_name assigned_name,(SELECT body FROM {$t['messages']} m WHERE m.conversation_id=c.id ORDER BY m.id DESC LIMIT 1) last_message FROM {$t['conversations']} c LEFT JOIN {$wpdb->users} u ON u.ID=c.assigned_user $where ORDER BY c.last_message_at DESC LIMIT 300";
        if($args)$sql=$wpdb->prepare($sql,$args); return rest_ensure_response($wpdb->get_results($sql,ARRAY_A));
    }
    public function admin_conversation(WP_REST_Request $r){
        global $wpdb; $t=OMX_Connect_DB::tables(); $id=(int)$r['id']; $c=$wpdb->get_row($wpdb->prepare("SELECT c.*,u.display_name assigned_name FROM {$t['conversations']} c LEFT JOIN {$wpdb->users} u ON u.ID=c.assigned_user WHERE c.id=%d",$id),ARRAY_A);
        if(!$c)return new WP_Error('not_found','Conversation not found',['status'=>404]); $m=$wpdb->get_results($wpdb->prepare("SELECT m.*,u.display_name sender_name FROM {$t['messages']} m LEFT JOIN {$wpdb->users} u ON u.ID=m.sender_user WHERE m.conversation_id=%d ORDER BY m.id ASC",$id),ARRAY_A);
        $wpdb->update($t['conversations'],['unread_agent'=>0],['id'=>$id]); return rest_ensure_response(['conversation'=>$c,'messages'=>$m]);
    }
    public function admin_reply(WP_REST_Request $r){
        global $wpdb; $t=OMX_Connect_DB::tables(); $id=(int)($r['conversation_id']??0); $body=trim($this->body($r['body']??'')); $note=!empty($r['internal_note']); if(!$id||!$body)return new WP_Error('invalid','Conversation and message required',['status'=>400]);
        $wpdb->insert($t['messages'],['conversation_id'=>$id,'sender_type'=>'agent','sender_user'=>get_current_user_id(),'message_type'=>$note?'note':'message','body'=>$body]);
        if($note){$wpdb->update($t['conversations'],['updated_at'=>current_time('mysql')],['id'=>$id]);} else {$wpdb->query($wpdb->prepare("UPDATE {$t['conversations']} SET unread_customer=unread_customer+1,last_message_at=%s,updated_at=%s WHERE id=%d",current_time('mysql'),current_time('mysql'),$id));}
        return rest_ensure_response(['ok'=>true]);
    }
    public function admin_update(WP_REST_Request $r){
        global $wpdb; $t=OMX_Connect_DB::tables(); $id=(int)($r['conversation_id']??0); if(!$id)return new WP_Error('invalid','Conversation required',['status'=>400]); $data=[];
        if(isset($r['status'])&&in_array($r['status'],['open','pending','resolved'],true))$data['status']=$r['status'];
        if(isset($r['priority'])&&in_array($r['priority'],['low','normal','high','urgent'],true))$data['priority']=$r['priority'];
        if(isset($r['department']))$data['department']=sanitize_text_field($r['department']);
        if(isset($r['assigned_user']))$data['assigned_user']=(int)$r['assigned_user']?:null;
        if(isset($r['tags']))$data['tags']=sanitize_text_field($r['tags']);
        $data['updated_at']=current_time('mysql'); $wpdb->update($t['conversations'],$data,['id'=>$id]); return rest_ensure_response(['ok'=>true]);
    }
    public function canned(){ global $wpdb;$t=OMX_Connect_DB::tables();return rest_ensure_response($wpdb->get_results("SELECT * FROM {$t['canned']} ORDER BY title",ARRAY_A)); }
    public function agents(){ $u=get_users(['role__in'=>['administrator','editor','author'],'fields'=>['ID','display_name','user_email']]); return rest_ensure_response(array_map(fn($x)=>['id'=>$x->ID,'name'=>$x->display_name,'email'=>$x->user_email],$u)); }
    public function stats(){
        global $wpdb; $t=OMX_Connect_DB::tables();
        $r=$wpdb->get_row("SELECT COUNT(*) total, SUM(status='open') open_count, SUM(status='pending') pending_count, COALESCE(SUM(unread_agent),0) unread_count FROM {$t['conversations']}",ARRAY_A);
        return rest_ensure_response(array_map('intval',$r?:['total'=>0,'open_count'=>0,'pending_count'=>0,'unread_count'=>0]));
    }
    public function meta(){ $s=wp_parse_args(get_option('omx_connect_settings',[]),['departments'=>'General Support,DPL,CPL,ICL,Materials,IT,Billing']); $deps=array_values(array_filter(array_map('trim',explode(',',$s['departments'])))); return rest_ensure_response(['departments'=>$deps]); }
}
