<?php
if (!defined('ABSPATH')) exit;
class OMX_Connect_Admin {
    public function __construct(){
        add_action('admin_menu',[$this,'menu']);
        add_action('admin_enqueue_scripts',[$this,'assets']);
        add_action('admin_post_omx_save_settings',[$this,'save_settings']);
        add_action('admin_post_omx_save_canned',[$this,'save_canned']);
        add_action('admin_post_omx_delete_canned',[$this,'delete_canned']);
    }
    public function menu(){
        add_menu_page('Omnexivo Connect','Omnexivo Connect','edit_posts','omx-connect',[$this,'page'],'dashicons-format-chat',26);
        add_submenu_page('omx-connect','Inbox','Inbox','edit_posts','omx-connect',[$this,'page']);
        add_submenu_page('omx-connect','Settings','Settings','manage_options','omx-connect-settings',[$this,'settings_page']);
    }
    public function assets($hook){
        if($hook==='toplevel_page_omx-connect'){
            wp_enqueue_style('omx-connect-admin',OMX_CONNECT_URL.'assets/css/admin.css',[],OMX_CONNECT_VERSION);
            wp_enqueue_script('omx-connect-admin',OMX_CONNECT_URL.'assets/js/admin.js',[],OMX_CONNECT_VERSION,true);
            wp_localize_script('omx-connect-admin','OMXConnectAdmin',['rest'=>esc_url_raw(rest_url('omx-connect/v1')),'nonce'=>wp_create_nonce('wp_rest')]);
        }
    }
    private function defaults(){return [
        'widget_title'=>'Chat with us',
        'welcome_message'=>'Hi! How can we help you today?',
        'accent_color'=>'#6C5CE7',
        'departments'=>'General Support,DPL,CPL,ICL,Materials,IT,Billing',
        'widget_enabled'=>1,
    ];}
    private function redirect_settings($notice){wp_safe_redirect(add_query_arg(['page'=>'omx-connect-settings','omx_notice'=>$notice],admin_url('admin.php')));exit;}
    public function save_settings(){
        if(!current_user_can('manage_options'))wp_die('Permission denied');
        check_admin_referer('omx_save_settings');
        $raw=isset($_POST['departments'])?wp_unslash($_POST['departments']):'';
        $departments=array_values(array_unique(array_filter(array_map('sanitize_text_field',preg_split('/[\r\n,]+/',(string)$raw)))));
        $departments=array_slice(array_filter($departments,static function($d){return $d!=='';}),0,100);
        if(!$departments) $departments=['General Support'];
        $title=sanitize_text_field(wp_unslash($_POST['widget_title']??''));
        $welcome=sanitize_textarea_field(wp_unslash($_POST['welcome_message']??''));
        $color=sanitize_hex_color(wp_unslash($_POST['accent_color']??''));
        $settings=[
          'widget_title'=>$title?:'Chat with us',
          'welcome_message'=>$welcome?:'Hi! How can we help you today?',
          'accent_color'=>$color?:'#6C5CE7',
          'departments'=>implode(',',$departments),
          'widget_enabled'=>!empty($_POST['widget_enabled'])?1:0,
        ];
        update_option('omx_connect_settings',$settings);
        $this->redirect_settings('saved');
    }
    public function save_canned(){
        if(!current_user_can('manage_options'))wp_die('Permission denied');
        check_admin_referer('omx_save_canned');
        global $wpdb;$t=OMX_Connect_DB::tables();
        $id=absint($_POST['id']??0);
        $title=sanitize_text_field(wp_unslash($_POST['title']??''));
        $shortcut=sanitize_text_field(wp_unslash($_POST['shortcut']??''));
        $body=sanitize_textarea_field(wp_unslash($_POST['body']??''));
        if(!$title||!$body)$this->redirect_settings('required');
        $data=['title'=>$title,'shortcut'=>$shortcut,'body'=>$body];
        if($id) $wpdb->update($t['canned'],$data,['id'=>$id]);
        else $wpdb->insert($t['canned'],$data);
        $this->redirect_settings('canned_saved');
    }
    public function delete_canned(){
        if(!current_user_can('manage_options'))wp_die('Permission denied');
        $id=absint($_POST['id']??0);
        check_admin_referer('omx_delete_canned_'.$id);
        global $wpdb;$t=OMX_Connect_DB::tables();
        if($id)$wpdb->delete($t['canned'],['id'=>$id]);
        $this->redirect_settings('canned_deleted');
    }
    public function settings_page(){
        if(!current_user_can('manage_options'))return;
        global $wpdb;$t=OMX_Connect_DB::tables();
        $s=wp_parse_args(get_option('omx_connect_settings',[]),$this->defaults());
        $canned=$wpdb->get_results("SELECT id,title,shortcut,body FROM {$t['canned']} ORDER BY title ASC",ARRAY_A);
        $notice=sanitize_key($_GET['omx_notice']??'');
        $messages=['saved'=>'Settings saved.','canned_saved'=>'Canned response saved.','canned_deleted'=>'Canned response deleted.','required'=>'Title and response text are required.'];
        ?>
        <div class="wrap" style="max-width:1000px">
          <h1>Omnexivo Connect — Settings</h1>
          <?php if(isset($messages[$notice])): ?><div class="notice <?php echo $notice==='required'?'notice-error':'notice-success'; ?> is-dismissible"><p><?php echo esc_html($messages[$notice]); ?></p></div><?php endif; ?>
          <p>Manage your departments and the customer-facing chat widget here. Existing conversations and their department labels are preserved when departments are renamed or removed.</p>
          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="omx_save_settings">
            <?php wp_nonce_field('omx_save_settings'); ?>
            <h2>Departments</h2>
            <table class="form-table" role="presentation"><tr><th scope="row"><label for="omx-departments">Department names</label></th><td>
              <textarea id="omx-departments" name="departments" rows="8" class="large-text code"><?php echo esc_textarea(implode("\n",array_filter(array_map('trim',explode(',',$s['departments']))))); ?></textarea>
              <p class="description">One department per line. Add, rename or remove departments here. Existing conversations retain their original department until reassigned.</p>
            </td></tr></table>
            <h2>Chat widget</h2>
            <table class="form-table" role="presentation">
              <tr><th scope="row">Website chat widget</th><td><label><input type="checkbox" name="widget_enabled" value="1" <?php checked(!empty($s['widget_enabled'])); ?>> Enable on website</label></td></tr>
              <tr><th scope="row"><label for="omx-title">Widget title</label></th><td><input id="omx-title" name="widget_title" type="text" class="regular-text" maxlength="100" value="<?php echo esc_attr($s['widget_title']); ?>"></td></tr>
              <tr><th scope="row"><label for="omx-welcome">Welcome message</label></th><td><textarea id="omx-welcome" name="welcome_message" rows="3" class="large-text"><?php echo esc_textarea($s['welcome_message']); ?></textarea></td></tr>
              <tr><th scope="row"><label for="omx-color">Accent color</label></th><td><input id="omx-color" name="accent_color" type="color" value="<?php echo esc_attr(sanitize_hex_color($s['accent_color'])?:'#6C5CE7'); ?>"></td></tr>
            </table>
            <?php submit_button('Save settings'); ?>
          </form>
          <hr>
          <h2>Canned responses</h2>
          <p>Create reusable replies for your agents. Edit a reply below or add a new one.</p>
          <?php foreach($canned as $r): ?>
            <details style="background:#fff;border:1px solid #c3c4c7;border-radius:5px;padding:12px;margin-bottom:10px">
              <summary style="cursor:pointer"><strong><?php echo esc_html($r['title']); ?></strong> <code><?php echo esc_html($r['shortcut']); ?></code></summary>
              <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:12px">
                <input type="hidden" name="action" value="omx_save_canned"><input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                <?php wp_nonce_field('omx_save_canned'); ?>
                <p><label>Title<br><input type="text" name="title" class="regular-text" required value="<?php echo esc_attr($r['title']); ?>"></label></p>
                <p><label>Shortcut<br><input type="text" name="shortcut" class="regular-text" value="<?php echo esc_attr($r['shortcut']); ?>" placeholder="/barcode"></label></p>
                <p><label>Response<br><textarea name="body" rows="4" class="large-text" required><?php echo esc_textarea($r['body']); ?></textarea></label></p>
                <?php submit_button('Update reply','secondary','submit',false); ?>
              </form>
              <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Delete this canned response?')" style="margin-top:8px">
                <input type="hidden" name="action" value="omx_delete_canned"><input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                <?php wp_nonce_field('omx_delete_canned_'.(int)$r['id']); ?>
                <button type="submit" class="button-link-delete">Delete reply</button>
              </form>
            </details>
          <?php endforeach; ?>
          <h3>Add canned response</h3>
          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="omx_save_canned">
            <?php wp_nonce_field('omx_save_canned'); ?>
            <table class="form-table" role="presentation">
              <tr><th scope="row"><label for="omx-new-title">Title</label></th><td><input type="text" id="omx-new-title" name="title" class="regular-text" required></td></tr>
              <tr><th scope="row"><label for="omx-new-shortcut">Shortcut</label></th><td><input type="text" id="omx-new-shortcut" name="shortcut" placeholder="/welcome" class="regular-text"></td></tr>
              <tr><th scope="row"><label for="omx-new-body">Response</label></th><td><textarea id="omx-new-body" name="body" rows="4" class="large-text" required></textarea></td></tr>
            </table>
            <?php submit_button('Add canned response','secondary'); ?>
          </form>
        </div><?php
    }
    public function page(){ ?>
    <div class="wrap omx-app">
      <div class="omx-topbar"><div><div class="omx-eyebrow">✦ OMNEXIVO · CUSTOMER EXPERIENCE</div><h1>Connect <span class="omx-live-dot"></span></h1><p>Your conversations, beautifully organized.</p></div><div class="omx-header-actions"><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=omx-connect-settings')); ?>">⚙ Settings</a> <span class="omx-badge">v0.2.2</span></div></div>
      <div class="omx-overview"><div class="omx-overview-card"><span>All conversations</span><strong id="omx-total-stat">—</strong></div><div class="omx-overview-card"><span>Open now</span><strong id="omx-open-stat">—</strong></div><div class="omx-overview-card"><span>Waiting / pending</span><strong id="omx-pending-stat">—</strong></div><div class="omx-overview-card"><span>Unread messages</span><strong id="omx-unread-stat">—</strong></div></div>
      <div id="omx-feedback" class="omx-feedback" role="status" aria-live="polite" hidden></div>
      <div class="omx-toolbar">
        <input id="omx-search" type="search" placeholder="Search customer, barcode, message, tag…">
        <select id="omx-priority-filter"><option value="">All priorities</option><option>low</option><option>normal</option><option>high</option><option>urgent</option></select>
        <select id="omx-department-filter"><option value="">All departments</option></select>
        <button id="omx-refresh" class="button">↻ Refresh</button>
      </div>
      <div class="omx-layout">
        <aside class="omx-sidebar">
          <button class="omx-filter active" data-status="">All conversations</button>
          <button class="omx-filter" data-status="open">Open</button>
          <button class="omx-filter" data-status="pending">Pending</button>
          <button class="omx-filter" data-status="resolved">Resolved</button>
        </aside>
        <section class="omx-list"><div class="omx-list-head"><strong>Inbox</strong><span id="omx-count"></span></div><div id="omx-conversations"></div></section>
        <section class="omx-chat"><div id="omx-empty"><span class="omx-empty-icon">✦</span><h2>Ready when you are</h2><p>Choose a conversation to reply, assign or leave an internal note.</p></div><div id="omx-thread" hidden>
          <div class="omx-chat-head"><div><strong id="omx-customer-name"></strong><div id="omx-customer-meta"></div></div><select id="omx-status"><option value="open">Open</option><option value="pending">Pending</option><option value="resolved">Resolved</option></select></div>
          <div class="omx-info-grid">
            <label>Priority<select id="omx-priority"><option>low</option><option>normal</option><option>high</option><option>urgent</option></select></label>
            <label>Department<select id="omx-department"></select></label>
            <label>Assign agent<select id="omx-agent"><option value="0">Unassigned</option></select></label>
            <label>Tags<input id="omx-tags" placeholder="billing, urgent, barcode"></label>
          </div>
          <div id="omx-messages"></div>
          <div class="omx-composer">
            <div class="omx-compose-tools"><select id="omx-canned"><option value="">Canned response…</option></select><label><input type="checkbox" id="omx-note"> Internal note</label></div>
            <textarea id="omx-reply" rows="4" placeholder="Type a reply…"></textarea>
            <button id="omx-send" class="button button-primary">Send message ↗</button>
          </div>
        </div></section>
      </div>
    </div><?php }
}
