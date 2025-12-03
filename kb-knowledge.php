<?php
/*
Plugin Name: KB Cyber KnowledgeBase 
Description: המאגר - תיקון באגים: כפילויות, שדות חובה, תאריך, ניווט. עברית מלאה.
Version: 20.0 BUGS FIXED FINAL
*/

if (!defined('ABSPATH')) exit;

class KB_KnowledgeBase_Editor {
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_upload_pasted_image', [$this, 'upload_image']);
        add_action('wp_ajax_nopriv_upload_pasted_image', [$this, 'upload_image']);
        add_action('wp_ajax_save_article', [$this, 'save_article']);
        add_action('wp_ajax_nopriv_save_article', [$this, 'save_article']);
        add_action('wp_ajax_kb_get_categories', [$this, 'ajax_get_categories']);
        add_action('wp_ajax_nopriv_kb_get_categories', [$this, 'ajax_get_categories']);
        add_action('wp_ajax_kb_add_category', [$this, 'ajax_add_category']);
        add_action('wp_ajax_nopriv_kb_add_category', [$this, 'ajax_add_category']);
        add_action('wp_ajax_kb_delete_category', [$this, 'ajax_delete_category']);
        add_action('wp_ajax_nopriv_kb_delete_category', [$this, 'ajax_delete_category']);
        add_action('wp_ajax_kb_update_order', [$this, 'ajax_update_order']);
        add_action('wp_ajax_nopriv_kb_update_order', [$this, 'ajax_update_order']);
        add_action('wp_ajax_kb_check_subject', [$this, 'ajax_check_subject']);
		add_action('wp_ajax_nopriv_kb_check_subject', [$this, 'ajax_check_subject']);
		add_shortcode('kb_categories_tree', [$this, 'shortcode_tree']);

        add_action('init', [$this, 'disable_cache_for_kb'], 1);
        
        register_activation_hook(__FILE__, [$this, 'create_table']);
        add_shortcode('kb_public_form', [$this, 'public_form_shortcode']);
        add_shortcode('kb_home_page', [$this, 'home_page_shortcode']);
        $this->create_table();
    }
	// AJAX - כפילות LIVE בשדה נושא
	public function ajax_check_subject() {
		global $wpdb;
		$subject = sanitize_text_field($_POST['subject']);
		$id = isset($_POST['article_id']) ? intval($_POST['article_id']) : 0;
		$table = $wpdb->prefix . 'kb_articles';
		if(!$subject) wp_send_json_error(['msg'=>'לא נכתב נושא']);
		$where = $wpdb->prepare('subject = %s', $subject);
		if($id) { $where .= $wpdb->prepare(' AND id != %d', $id); }
		$exists = $wpdb->get_row("SELECT * FROM $table WHERE $where");
		if($exists)
			wp_send_json_success(['exists'=>true, 'msg'=>'❌ נושא זה קיים כבר במערכת.']);
		else
			wp_send_json_success(['exists'=>false, 'msg'=>'✔️ הנושא פנוי לשימוש']);
}

    public function disable_cache_for_kb() {
        if(isset($_GET['kbs']) || isset($_GET['kbcat']) || isset($_GET['kb_article']) || isset($_GET['edit_article'])) {
            if (!defined('DONOTCACHEPAGE')) {
                define('DONOTCACHEPAGE', true);
            }
            if (!defined('DONOTCACHEOBJECT')) {
                define('DONOTCACHEOBJECT', true);
            }
            if (!defined('DONOTCACHEDB')) {
                define('DONOTCACHEDB', true);
            }
            if(function_exists('comet_cache_disable')) {
                comet_cache_disable();
            }
            nocache_headers();
        }
    }

    public function create_table() {
        global $wpdb;
        $table = $wpdb->prefix . 'kb_articles';
        $cats_table = $wpdb->prefix . 'kb_categories';
        $charset_collate = $wpdb->get_charset_collate();
        $sql1 = "CREATE TABLE IF NOT EXISTS $table (
            id INT AUTO_INCREMENT PRIMARY KEY,
            category VARCHAR(100) DEFAULT NULL,
            subject VARCHAR(255) DEFAULT NULL,
            short_desc TEXT DEFAULT NULL,
            technical_desc TEXT DEFAULT NULL,
            technical_solution TEXT DEFAULT NULL,
            solution_script TEXT DEFAULT NULL,
            solution_files TEXT DEFAULT NULL,
            post_check TEXT DEFAULT NULL,
            check_script TEXT DEFAULT NULL,
            check_files TEXT DEFAULT NULL,
            links TEXT DEFAULT NULL,
            review_status TINYINT(1) NOT NULL DEFAULT 0,
            is_deleted TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) $charset_collate;";
        $sql2 = "CREATE TABLE IF NOT EXISTS $cats_table (
            id INT AUTO_INCREMENT PRIMARY KEY,
            category_name VARCHAR(100) NOT NULL,
            parent_id INT DEFAULT 0,
            sort_order INT DEFAULT 0
        ) $charset_collate;";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql1);
        dbDelta($sql2);
        
        $cols = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'solution_files'");
        if(empty($cols)) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN solution_files TEXT DEFAULT NULL AFTER solution_script");
        }
        $cols2 = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'check_files'");
        if(empty($cols2)) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN check_files TEXT DEFAULT NULL AFTER check_script");
        }
        
        $cols3 = $wpdb->get_results("SHOW COLUMNS FROM $cats_table LIKE 'sort_order'");
        if(empty($cols3)) {
            $wpdb->query("ALTER TABLE $cats_table ADD COLUMN sort_order INT DEFAULT 0");
        }
        $cols4 = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'review_status'");
        if(empty($cols4)) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN review_status TINYINT(1) NOT NULL DEFAULT 0 AFTER links");
        }
        $cols5 = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'is_deleted'");
        if(empty($cols5)) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0 AFTER review_status");
        }
        if(!$wpdb->get_var("SELECT COUNT(*) FROM $cats_table")) {
            $wpdb->insert($cats_table, ['category_name'=>'שרתים', 'parent_id'=>0, 'sort_order'=>1]);
            $wpdb->insert($cats_table, ['category_name'=>'בדיקות', 'parent_id'=>0, 'sort_order'=>2]);
            $wpdb->insert($cats_table, ['category_name'=>'שרת לינוקס', 'parent_id'=>1, 'sort_order'=>1]);
        }
    }

    public function add_menu() {
        add_menu_page('המאגר', 'המאגר', 'manage_options', 'kb-editor', [$this, 'main_page'], 'dashicons-welcome-write-blog');
        add_submenu_page('kb-editor', 'מאמר חדש', 'מאמר חדש', 'manage_options', 'kb-editor-new', [$this, 'edit_page']);
        add_submenu_page('kb-editor', 'ניהול קטגוריות', 'קטגוריות', 'manage_options', 'kb-editor-categories', [$this, 'categories_page']);
    }

    public function enqueue_scripts($hook) {
        wp_enqueue_script('ckeditor5', 'https://cdn.ckeditor.com/ckeditor5/40.1.0/classic/ckeditor.js', [], '40.1.0');
        wp_localize_script('ckeditor5', 'kbAjax', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('kbnonce'),
        ]);
    }

    public function ajax_get_categories() {
        global $wpdb;
        $cats = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}kb_categories ORDER BY parent_id, sort_order, category_name");
        wp_send_json_success($cats);
    }

    public function ajax_add_category() {
        check_ajax_referer('kbnonce', 'nonce');
        global $wpdb;
        $name = sanitize_text_field($_POST['cat_name']);
        $parent = intval($_POST['parent_id']);
        if(empty($name)) wp_send_json_error('שם חסר');
        $wpdb->insert($wpdb->prefix.'kb_categories', ['category_name'=>$name, 'parent_id'=>$parent, 'sort_order'=>0]);
        wp_send_json_success();
    }

    public function ajax_delete_category() {
        check_ajax_referer('kbnonce', 'nonce');
        global $wpdb;
        $id = intval($_POST['cat_id']);
        $wpdb->delete($wpdb->prefix.'kb_categories', ['id'=>$id]);
        wp_send_json_success();
    }

    public function ajax_update_order() {
        check_ajax_referer('kbnonce', 'nonce');
        global $wpdb;
        foreach($_POST['orders'] as $id=>$order) {
            $wpdb->update($wpdb->prefix.'kb_categories', ['sort_order'=>intval($order)], ['id'=>intval($id)]);
        }
        wp_send_json_success();
    }

    public function categories_page() {
        global $wpdb;
        $cats_table = $wpdb->prefix . 'kb_categories';
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            check_admin_referer('kb-categories');
            if (isset($_POST['delcat'])) {
                $wpdb->delete($cats_table, ['id'=>intval($_POST['delete_cat'])]);
            }
            if (isset($_POST['addcat']) && !empty($_POST['new_cat'])) {
                $parent_id = isset($_POST['parent_id']) ? intval($_POST['parent_id']) : 0;
                $max_order = $wpdb->get_var("SELECT MAX(sort_order) FROM $cats_table WHERE parent_id=$parent_id");
                $wpdb->insert($cats_table, [
                    'category_name'=>sanitize_text_field($_POST['new_cat']),
                    'parent_id'=>$parent_id,
                    'sort_order'=>($max_order+1)
                ]);
            }
            if (isset($_POST['update_order'])) {
                foreach($_POST['cat_order'] as $id=>$order) {
                    $wpdb->update($cats_table, ['sort_order'=>intval($order)], ['id'=>intval($id)]);
                }
            }
            echo "<script>location.href='".admin_url('admin.php?page=kb-editor-categories')."'</script>";
            exit;
        }
        echo '<div class="wrap"><h1>ניהול קטגוריות</h1>';
        $allcats = $wpdb->get_results("SELECT * FROM $cats_table ORDER BY parent_id, sort_order, category_name");
        function print_cat_tree($cats, $parent=0, $level=0){
            foreach ($cats as $row) {
                if ($row->parent_id == $parent) {
                    echo '<tr>
                        <td style="padding-right: '.($level*15).'px;">'.esc_html($row->category_name).'</td>
                        <td>';
                    $parent_name = '';
                    foreach($cats as $c) { if($c->id == $row->parent_id) $parent_name = $c->category_name; }
                    echo $row->parent_id ? mb_substr(esc_html($parent_name), 0, 20) : 'ראשי';
                    echo '</td>
                        <td><input type="number" name="cat_order['.$row->id.']" value="'.$row->sort_order.'" style="width:60px;"></td>
                        <td>
                            <form method="post" style="display:inline;">';
                    wp_nonce_field('kb-categories');
                    echo '<input type="hidden" name="delete_cat" value="'.$row->id.'">
                        <button class="button button-small" name="delcat">מחק</button>
                        </form>
                        </td>';
                    echo '</tr>';
                    print_cat_tree($cats, $row->id, $level+1);
                }
            }
        }
        echo '<form method="post">';
        wp_nonce_field('kb-categories');
        echo '<table class="kb-admin-table" border="1" style="width:600px"><tr><th>שם קטגוריה</th><th>תחת</th><th>סדר</th><th></th></tr>';
        print_cat_tree($allcats, 0, 0);
        echo '<tr><td colspan="4"><button class="button button-primary" name="update_order">עדכן סדר</button></td></tr>';
        echo '</table></form>';
        echo '<hr><h3>הוסף קטגוריה חדשה</h3>';
        echo '<form method="post">';
        wp_nonce_field('kb-categories');
        echo '<table><tr>
            <td><input type="text" name="new_cat" placeholder="קטגוריה חדשה"></td>
            <td>
                <select name="parent_id">
                    <option value="0">ראשי</option>';
        foreach($allcats as $c)
            if ($c->parent_id==0)
                echo '<option value="'.$c->id.'">'.esc_html($c->category_name).'</option>';
        echo '</select></td>
            <td><button class="button" name="addcat">הוסף</button></td>
        </tr></table></form>';
        echo '</div>';
    }

    public function get_categories_tree($parent_id=0, $prefix='') {
        global $wpdb;
        $cats = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}kb_categories WHERE parent_id=%d ORDER BY sort_order, category_name", $parent_id));
        $out = [];
        foreach($cats as $cat) {
            $out[$cat->category_name] = $prefix . $cat->category_name;
            $out += $this->get_categories_tree($cat->id, $prefix.'-- ');
        }
        return $out;
    }

    // ⭐ פונקציה חדשה - פורמט תאריך בעברית
    public function format_hebrew_date($datetime) {
        if(empty($datetime)) return '';
        $timestamp = strtotime($datetime);
        return date('d/m/Y H:i', $timestamp);
    }

    private function get_status_labels() {
        if(class_exists('KB_KnowledgeBase_Unified_Core')) {
            return KB_KnowledgeBase_Unified_Core::status_labels();
        }
        return [0=>'לא נבדק',1=>'בתהליך',2=>'תקין'];
    }

    private function render_status_badge($status) {
        $status = is_null($status) ? 0 : intval($status);
        $labels = $this->get_status_labels();
        $label = isset($labels[$status]) ? $labels[$status] : $labels[0];
        $class = 'kb-status-badge '; $dot = '';
        if($status === 2) { $class .= 'kb-status-badge--green'; $dot = '🟢'; }
        elseif($status === 1) { $class .= 'kb-status-badge--orange'; $dot = '🟠'; }
        else { $class .= 'kb-status-badge--red'; $dot = '🔴'; }
        return '<span class="'.$class.'">'.$dot.' '.$label.'</span>';
    }

    public function main_page() {
        global $wpdb;
        $table = $wpdb->prefix . 'kb_articles';
        $view_trash = isset($_GET['view']) && $_GET['view'] === 'trash';

        $action = isset($_GET['kb_action']) ? sanitize_key($_GET['kb_action']) : '';
        $target_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if($action && $target_id){
            $nonce = isset($_GET['_wpnonce']) ? $_GET['_wpnonce'] : '';
            if(!wp_verify_nonce($nonce, 'kb_action_'.$target_id)) wp_die('Nonce failed');
            if($action === 'trash') {
                $wpdb->update($table, ['is_deleted'=>1], ['id'=>$target_id], ['%d'], ['%d']);
            } elseif($action === 'restore') {
                $wpdb->update($table, ['is_deleted'=>0], ['id'=>$target_id], ['%d'], ['%d']);
            } elseif($action === 'delete') {
                $wpdb->delete($table, ['id'=>$target_id]);
            }
            wp_safe_redirect(admin_url('admin.php?page=kb-editor'.($view_trash ? '&view=trash' : '')));
            exit;
        }

        $search = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
        $sql = "SELECT * FROM $table WHERE ".($view_trash ? "is_deleted=1" : "(is_deleted IS NULL OR is_deleted=0)");
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $sql .= $wpdb->prepare(
                " AND (category LIKE %s OR subject LIKE %s OR
                short_desc LIKE %s OR technical_desc LIKE %s OR technical_solution LIKE %s)",
                $like,$like,$like,$like,$like
            );
        }
        $sql .= " ORDER BY created_at DESC";
        $articles = $wpdb->get_results($sql);

        echo '<div class="wrap"><h1>המאגר <a href="'.admin_url('admin.php?page=kb-editor-new').'" class="button button-primary">מאמר חדש</a></h1>';
        echo '<h2 class="nav-tab-wrapper">';
        echo '<a class="nav-tab '.(!$view_trash ? 'nav-tab-active' : '').'" href="'.admin_url('admin.php?page=kb-editor').'">מאמרים פעילים</a>';
        echo '<a class="nav-tab '.($view_trash ? 'nav-tab-active' : '').'" href="'.admin_url('admin.php?page=kb-editor&view=trash').'">סל מחזור</a>';
        echo '</h2>';
        echo '<form method="get" class="kb-search-form"><input type="hidden" name="page" value="kb-editor">';
        if($view_trash) echo '<input type="hidden" name="view" value="trash">';
        echo '<input type="text" name="q" placeholder="חיפוש..." value="'.esc_attr($search).'">
            <button type="submit" class="button">חיפוש</button></form>';
        echo '<table class="wp-list-table widefat kb-table"><thead><tr>
            <th>נושא</th><th>קטגוריה</th><th>סטטוס</th><th>נוצר בתאריך</th><th>פעולות</th>
        </tr></thead><tbody>';
        foreach ($articles as $a) {
            $nonce = wp_create_nonce('kb_action_'.$a->id);
            $status_badge = $this->render_status_badge($a->review_status);
            echo '<tr>
                <td>' . esc_html($a->subject) . '</td>
                <td>' . esc_html($a->category) . '</td>
                <td>' . $status_badge . '</td>
                <td>' . esc_html($this->format_hebrew_date($a->created_at)) . '</td>
                <td>';
            if(!$view_trash) {
                echo '<a href="?page=kb-editor-new&edit=' . intval($a->id) . '" class="button">עריכה</a> ';
                echo '<a href="'.wp_nonce_url('?page=kb-editor&kb_action=trash&id='.intval($a->id), 'kb_action_'.$a->id).'" class="button button-danger" onclick="return confirm(\'להעביר לסל מחזור?\');">העבר לסל מחזור</a>';
            } else {
                echo '<a href="'.wp_nonce_url('?page=kb-editor&view=trash&kb_action=restore&id='.intval($a->id), 'kb_action_'.$a->id).'" class="button">שחזר</a> ';
                echo '<a href="'.wp_nonce_url('?page=kb-editor&view=trash&kb_action=delete&id='.intval($a->id), 'kb_action_'.$a->id).'" class="button button-danger" onclick="return confirm(\'למחוק לצמיתות?\');">מחק לצמיתות</a>';
            }
            echo '</td>
            </tr>';
        }
        echo '</tbody></table></div>';
    }
	public function shortcode_tree($atts) {
		global $wpdb;
		$cats = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}kb_categories ORDER BY parent_id, sort_order, category_name");
		$table = $wpdb->prefix . 'kb_articles';
		$home_url = home_url('/');
		ob_start();
		echo "<div style='text-align:right;direction:rtl;max-width:770px;margin:auto;padding:30px 0;'>";
		echo "<a href='{$home_url}' style='font-size:17px;background:#2980b9;color:#fff;padding:10px 20px;border-radius:7px;text-decoration:none;margin-bottom:20px;display:inline-block;'>🔙 לדף הבית</a>";
		echo '<h2 style="margin:28px 0 16px 0;border-bottom:1.5px solid #eee;">עץ קטגוריות ומאמרים</h2><ul style="list-style-type:none;padding-right:0;">';
		$this->print_tree($cats, 0, $table, $home_url);
		echo "</ul></div>";
		return ob_get_clean();
	}
public function print_tree($cats, $parent, $table, $home_url) {
    global $wpdb;
    foreach ($cats as $c) {
        if($c->parent_id == $parent) {
            echo "<li style='margin:12px 0;'><span style='font-weight:bold;color:#34495e;font-size:1.13em'>" . esc_html($c->category_name) . "</span>";
            $articles = $wpdb->get_results($wpdb->prepare("SELECT id, subject FROM $table WHERE (is_deleted IS NULL OR is_deleted=0) AND category LIKE %s ORDER BY subject", '%'.$wpdb->esc_like($c->category_name).'%'));
            if($articles) {
                echo "<ul style='margin-top:2px;'>";
                foreach($articles as $a){
                    $link = add_query_arg(['kb_article' => $a->id], $home_url);
                    echo "<li style='margin:4px 0 4px 0;font-weight:normal;'><a style='color:#1f6697;font-size:1em;text-decoration:underline;' href='".esc_url($link)."'>" . esc_html($a->subject) . "</a></li>";
                }
                echo "</ul>";
            }
            echo "<ul style='margin-top:6px;'>";
            $this->print_tree($cats, $c->id, $table, $home_url);
            echo "</ul></li>";
        }
    }
}

    public function edit_page() {
        global $wpdb;
        $table = $wpdb->prefix . 'kb_articles';
        $article = null;
        if (isset($_GET['edit'])) $article = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", intval($_GET['edit'])));
        $cats_tree = $this->get_categories_tree();
        $status_labels = $this->get_status_labels();
        $current_status = $article ? intval($article->review_status) : 0;

        echo '<div class="wrap"><h1>' . ($article ? 'עריכת מאמר' : 'הוסף מאמר חדש') . '</h1>';
        echo '<form id="kb-article-form" class="kb-form" enctype="multipart/form-data">';
        wp_nonce_field('save_article_nonce','article_nonce');
        if ($article) echo '<input type="hidden" name="article_id" value="'.intval($article->id).'">';
        
        echo '<fieldset class="kb-fieldset"><legend>נתונים כלליים</legend>';
        echo '<div class="kb-row"><label class="kb-label">קטגוריה:</label>
            <select name="category" class="kb-input">';
        foreach($cats_tree as $v)
            echo '<option value="'.$v.'" '.($article && $article->category==$v ? 'selected' : '').'>'.$v.'</option>';
        echo '</select></div>';
        echo '<div class="kb-row"><label class="kb-label">נושא: <span style="color:red;">*</span></label>
            <input type="text" name="subject" class="kb-input" value="'.($article ? esc_attr($article->subject) : '').'" required></div>';
        echo '<div class="kb-row"><label class="kb-label">סטטוס בדיקה:</label><select name="review_status" class="kb-input">';
        foreach($status_labels as $k=>$lbl) {
            echo '<option value="'.intval($k).'" '.selected($current_status, $k, false).'>'.esc_html($lbl).'</option>';
        }
        echo '</select></div>';
        echo '</fieldset>';

        echo '<fieldset class="kb-fieldset"><legend>פרטים</legend>';
        echo '<div class="kb-row"><label class="kb-label">תיאור קצר:</label>
            <textarea class="kb-ckeditor kb-input" name="short_desc" id="short_desc">'.($article ? $article->short_desc : '').'</textarea></div>';
        echo '<div class="kb-row"><label class="kb-label">תיאור טכני:</label>
            <textarea class="kb-ckeditor kb-input" name="technical_desc" id="technical_desc">'.($article ? $article->technical_desc : '').'</textarea></div>';
        echo '</fieldset>';

        echo '<fieldset class="kb-fieldset"><legend>פתרון</legend>';
        echo '<div class="kb-row"><label class="kb-label">פתרון טכני: <span style="color:red;">*</span></label>
            <textarea class="kb-ckeditor kb-input kb-required" name="technical_solution" id="technical_solution">'.($article ? $article->technical_solution : '').'</textarea></div>';
        echo '<div class="kb-row kb-script-row"><label class="kb-label">סקריפט פתרון:</label>
            <textarea class="kb-script-area" name="solution_script" id="solution_script" dir="ltr">'.($article ? esc_textarea($article->solution_script) : '').'</textarea>
            <button type="button" class="kb-copy-btn" data-target="solution_script">📋 העתק</button></div>';
        echo '<div class="kb-row"><label class="kb-label">קבצים מצורפים (ניתן לבחור מספר קבצים):</label>
            <input type="file" name="solution_files[]" class="kb-input" multiple>';
        if ($article && !empty($article->solution_files)) {
            $files = json_decode($article->solution_files, true);
            if($files) {
                echo '<br><strong>קבצים קיימים:</strong><br>';
                foreach($files as $file) {
                    echo '<a href="'.esc_url($file).'" target="_blank">'.basename($file).'</a><br>';
                }
            }
        }
        echo '</div></fieldset>';

        echo '<fieldset class="kb-fieldset"><legend>בדיקה</legend>';
        echo '<div class="kb-row"><label class="kb-label">בדיקת פתרון:</label>
            <textarea class="kb-ckeditor kb-input" name="post_check" id="post_check">'.($article ? $article->post_check : '').'</textarea></div>';
        echo '<div class="kb-row kb-script-row"><label class="kb-label">סקריפט בדיקה:</label>
            <textarea class="kb-script-area" name="check_script" id="check_script" dir="ltr">'.($article ? esc_textarea($article->check_script) : '').'</textarea>
            <button type="button" class="kb-copy-btn" data-target="check_script">📋 העתק</button></div>';
        echo '<div class="kb-row"><label class="kb-label">קבצי בדיקה מצורפים (ניתן לבחור מספר קבצים):</label>
            <input type="file" name="check_files[]" class="kb-input" multiple>';
        if ($article && !empty($article->check_files)) {
            $files = json_decode($article->check_files, true);
            if($files) {
                echo '<br><strong>קבצים קיימים:</strong><br>';
                foreach($files as $file) {
                    echo '<a href="'.esc_url($file).'" target="_blank">'.basename($file).'</a><br>';
                }
            }
        }
        echo '</div></fieldset>';

        echo '<fieldset class="kb-fieldset"><legend>קישורים רלוונטיים</legend>';
        echo '<div class="kb-row"><label class="kb-label">קישורים:</label>
            <textarea class="kb-ckeditor kb-input" name="links" id="links">'.($article ? $article->links : '').'</textarea></div>';
        echo '</fieldset>';

        echo '<div class="kb-actions">
            <button type="button" id="kb-save-btn" class="button button-primary">💾 שמירה</button>
            <button type="button" id="kb-save-new-btn" class="button button-primary">💾 שמור והוסף חדש</button>
            <a href="'.admin_url('admin.php?page=kb-editor').'" class="button">חזרה</a>
        </div>';
        echo '<div id="save-message"></div></form>';
        echo '<style>
        .kb-form { direction:rtl; text-align:right; }
        .kb-fieldset { direction:rtl; text-align:right; }
        .kb-row { direction:rtl; text-align:right; }
        .kb-label { text-align:right; }
        .kb-input { text-align:right; direction:rtl; }
        .kb-script-row { position:relative; }
        .kb-script-area { width:100%; min-height:180px; padding:12px; font-family:"Courier New",Consolas,monospace; font-size:14px; line-height:1.5; direction:ltr; text-align:left; border:1px solid #ccc; border-radius:4px; background:#f5f5f5; color:#000; }
        .kb-copy-btn { position:absolute; top:35px; left:10px; padding:6px 12px; background:#3498db; color:#fff; border:none; border-radius:4px; cursor:pointer; font-size:12px; z-index:5; }
        .kb-copy-btn:hover { background:#2980b9; }
        .kb-actions { display:flex; gap:10px; margin-top:20px; }
        </style>';
        echo '<script>
        document.addEventListener("DOMContentLoaded",function(){
            if(window._kbEditorsInitialized) return;
            window._kbEditorsInitialized = true;
            
            class MyUploadAdapter {
                constructor(loader) {
                    this.loader = loader;
                }
                upload() {
                    return this.loader.file.then(file => new Promise((resolve, reject) => {
                        const reader = new FileReader();
                        reader.onload = () => {
                            const data = new FormData();
                            data.append("action", "upload_pasted_image");
                            data.append("nonce", kbAjax.nonce);
                            data.append("imagedata", reader.result);
                            fetch(kbAjax.ajaxurl, {
                                method: "POST",
                                body: data
                            })
                            .then(res => res.json())
                            .then(json => {
                                if(json.success) resolve({ default: json.data.url });
                                else reject("Upload failed");
                            });
                        };
                        reader.readAsDataURL(file);
                    }));
                }
                abort() {}
            }
            
            function MyCustomUploadAdapterPlugin(editor) {
                editor.plugins.get("FileRepository").createUploadAdapter = (loader) => {
                    return new MyUploadAdapter(loader);
                };
            }
            
            document.querySelectorAll(".kb-ckeditor").forEach(function(el){
                if(!el.classList.contains("ck-initialized")){
                    ClassicEditor.create(el, {
                        extraPlugins: [MyCustomUploadAdapterPlugin]
                    }).then(ed=>{
                        el.classList.add("ck-initialized");
                        el.editorInstance = ed;
                    }).catch(err=>console.error(err));
                }
            });
            
            document.querySelectorAll(".kb-copy-btn").forEach(btn => {
                btn.onclick = function() {
                    let target = document.getElementById(this.getAttribute("data-target"));
                    if (target) {
                        target.select();
                        document.execCommand("copy");
                        setTimeout(function(){ window.getSelection().removeAllRanges(); }, 150);
                    }
                }
            });
            
            function saveArticle(openNew) {
                // ⭐ בדיקת שדות חובה
                let subject = document.querySelector("[name=subject]").value.trim();
                let techSolution = document.querySelector("#technical_solution").editorInstance ? 
                    document.querySelector("#technical_solution").editorInstance.getData().trim() : "";
                
                if(!subject) {
                    alert("❌ שדה נושא הוא שדה חובה!");
                    return;
                }
                
                if(!techSolution || techSolution === "<p>&nbsp;</p>" || techSolution === "<p></p>") {
                    alert("❌ שדה פתרון טכני הוא שדה חובה!");
                    return;
                }
                
                let fd = new FormData(document.getElementById("kb-article-form"));
                document.querySelectorAll(".kb-ckeditor").forEach(function(el){
                    if(el.editorInstance) fd.set(el.name, el.editorInstance.getData());
                });
                fd.append("action", "save_article");
                fd.append("article_nonce", document.querySelector("[name=article_nonce]").value);
                fd.append("open_new", openNew ? "1" : "0");
                
                jQuery.ajax({
                    url: ajaxurl,
                    type: "POST",
                    data: fd,
                    processData: false,
                    contentType: false,
                    success: function(res){
                        if(res.success) {
                            jQuery("#save-message").html("<span style=\'color:green;\'>✓ נשמר בהצלחה</span>");
                            if(openNew) {
                                setTimeout(function(){ location.href = "'.admin_url('admin.php?page=kb-editor-new').'"; }, 800);
                            }
                        } else {
                            if(res.data && res.data.message) {
                                alert("❌ " + res.data.message);
                            } else {
                                jQuery("#save-message").html("<span style=\'color:red;\'>שגיאה</span>");
                            }
                        }
                    }
                });
            }
            
            document.getElementById("kb-save-btn").addEventListener("click", function(){ saveArticle(false); });
            document.getElementById("kb-save-new-btn").addEventListener("click", function(){ saveArticle(true); });
        });
        </script></div>';
    }

    public function save_article() {
        check_ajax_referer('save_article_nonce','article_nonce');
        global $wpdb;
        
        // ⭐ בדיקת שדות חובה
        if(empty($_POST['subject'])) {
            wp_send_json_error(['message' => 'שדה נושא הוא שדה חובה']);
        }
        
        $tech_solution = isset($_POST['technical_solution']) ? wp_kses_post($_POST['technical_solution']) : '';
        if(empty($tech_solution) || $tech_solution === '<p>&nbsp;</p>' || $tech_solution === '<p></p>') {
            wp_send_json_error(['message' => 'שדה פתרון טכני הוא שדה חובה']);
        }

        $status = isset($_POST['review_status']) ? intval($_POST['review_status']) : 0;
        if($status < 0 || $status > 2) { $status = 0; }

        // ⭐ בדיקת כפילויות - רק אם זה מאמר חדש
        $article_id = isset($_POST['article_id']) ? intval($_POST['article_id']) : 0;
        if(!$article_id) {
            $subject = sanitize_text_field($_POST['subject']);
            $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}kb_articles WHERE subject = %s", $subject));
            if($existing) {
                wp_send_json_error(['message' => 'קיים כבר מאמר עם אותו נושא! אנא בחר נושא אחר.']);
            }
        }
        
        $fields = [
            'category','subject','short_desc','technical_desc',
            'technical_solution','solution_script',
            'post_check','check_script','links'
        ];
        $data = [];
        foreach ($fields as $f) {
            if (isset($_POST[$f]) && $_POST[$f] != '') {
                $data[$f] = wp_kses_post($_POST[$f]);
            }
        }

        $data['review_status'] = $status;
        
        if (isset($_FILES['solution_files']) && !empty($_FILES['solution_files']['name'][0])) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            $uploaded_files = [];
            foreach ($_FILES['solution_files']['name'] as $key => $value) {
                if ($_FILES['solution_files']['name'][$key]) {
                    $file = [
                        'name' => $_FILES['solution_files']['name'][$key],
                        'type' => $_FILES['solution_files']['type'][$key],
                        'tmp_name' => $_FILES['solution_files']['tmp_name'][$key],
                        'error' => $_FILES['solution_files']['error'][$key],
                        'size' => $_FILES['solution_files']['size'][$key]
                    ];
                    $upload = wp_handle_upload($file, ['test_form' => false]);
                    if (!isset($upload['error'])) {
                        $uploaded_files[] = $upload['url'];
                    }
                }
            }
            $data['solution_files'] = json_encode($uploaded_files);
        }
        
        if (isset($_FILES['check_files']) && !empty($_FILES['check_files']['name'][0])) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            $uploaded_files = [];
            foreach ($_FILES['check_files']['name'] as $key => $value) {
                if ($_FILES['check_files']['name'][$key]) {
                    $file = [
                        'name' => $_FILES['check_files']['name'][$key],
                        'type' => $_FILES['check_files']['type'][$key],
                        'tmp_name' => $_FILES['check_files']['tmp_name'][$key],
                        'error' => $_FILES['check_files']['error'][$key],
                        'size' => $_FILES['check_files']['size'][$key]
                    ];
                    $upload = wp_handle_upload($file, ['test_form' => false]);
                    if (!isset($upload['error'])) {
                        $uploaded_files[] = $upload['url'];
                    }
                }
            }
            $data['check_files'] = json_encode($uploaded_files);
        }
        
        if ($article_id) {
            $wpdb->update($wpdb->prefix."kb_articles", $data, ['id'=>$article_id]);
        } else {
            $wpdb->insert($wpdb->prefix."kb_articles", $data);
        }
        wp_send_json_success();
    }

    public function upload_image() {
        check_ajax_referer('kbnonce', 'nonce');
        $data = preg_replace('#^data:image/\w+;base64,#', '', $_POST['imagedata']);
        $data = base_decode($data);
        if (!$data) wp_send_json_error();
        $dir = wp_upload_dir();
        $name = "kb-paste-".time().".png";
        $path = $dir['path'] . "/" . $name;
        $url = $dir['url'] . "/" . $name;
        if (file_put_contents($path, $data)) {
            wp_send_json_success(['url'=>$url]);
        } else {
            wp_send_json_error();
        }
    }

    public function public_form_shortcode() {
        global $wpdb;
        $cats_tree = $this->get_categories_tree();

        $edit_id = isset($_GET['edit_article']) ? intval($_GET['edit_article']) : 0;
        $article = $edit_id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}kb_articles WHERE id=%d", $edit_id)) : null;
        $status_labels = $this->get_status_labels();
        $current_status = $article ? intval($article->review_status) : 0;

        $kb_home_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : get_permalink(get_the_ID());
        $kb_home_url = remove_query_arg('edit_article', $kb_home_url);
        
        ob_start();
        wp_nonce_field('save_article_nonce','article_nonce');
        ?>
        <div class="kb-public-form-container">
        <h2><?php echo $article ? 'ערוך מאמר' : 'הוסף מאמר חדש'; ?></h2>
        
        <div class="kb-public-form-header">
            <button type="button" id="pub-save-btn" class="kb-btn-save">💾 שמירה</button>
            <button type="button" id="pub-save-new-btn" class="kb-btn-save" style="background:#f39c12;">💾 שמור והוסף חדש</button>
            <a href="<?php echo esc_url($kb_home_url); ?>" class="kb-btn-back">← חזור לרשימה</a>
        </div>
        
        <form id="kb-public-form" class="kb-form" enctype="multipart/form-data">
            <?php if($article): ?><input type="hidden" name="article_id" value="<?php echo $article->id; ?>"><?php endif; ?>
            
            <fieldset class="kb-fieldset">
                <legend>נתונים כלליים</legend>
                <div class="kb-row"><label class="kb-label">קטגוריה:</label>
                    <select name="category" class="kb-input" required>
                        <?php foreach($cats_tree as $v) echo '<option value="'.esc_attr($v).'" '.($article && $article->category==$v ? 'selected' : '').'>'.esc_html($v).'</option>'; ?>
                    </select>
                </div>
                <div class="kb-row"><label class="kb-label">נושא: <span style="color:red;">*</span></label>
                    <input type="text" name="subject" class="kb-input" value="<?php echo $article ? esc_attr($article->subject) : ''; ?>" required>
                </div>
                <div class="kb-row"><label class="kb-label">סטטוס בדיקה:</label>
                    <select name="review_status" class="kb-input">
                        <?php foreach($status_labels as $k=>$lbl) echo '<option value="'.intval($k).'" '.selected($current_status, $k, false).'>'.esc_html($lbl).'</option>'; ?>
                    </select>
                </div>
            </fieldset>
            
            <fieldset class="kb-fieldset">
                <legend>פרטים</legend>
                <div class="kb-row"><label class="kb-label">תיאור קצר:</label>
                    <textarea class="kb-ckeditor kb-input" name="short_desc" id="pub_short_desc"><?php echo $article ? $article->short_desc : ''; ?></textarea>
                </div>
                <div class="kb-row"><label class="kb-label">תיאור טכני:</label>
                    <textarea class="kb-ckeditor kb-input" name="technical_desc" id="pub_technical_desc"><?php echo $article ? $article->technical_desc : ''; ?></textarea>
                </div>
            </fieldset>
            
            <fieldset class="kb-fieldset">
                <legend>פתרון</legend>
                <div class="kb-row"><label class="kb-label">פתרון טכני: <span style="color:red;">*</span></label>
                    <textarea class="kb-ckeditor kb-input kb-required" name="technical_solution" id="pub_technical_solution"><?php echo $article ? $article->technical_solution : ''; ?></textarea>
                </div>
                <div class="kb-row kb-script-row"><label class="kb-label">סקריפט פתרון:</label>
                    <textarea class="kb-script-area" name="solution_script" id="pub_solution_script" dir="ltr"><?php echo $article ? esc_textarea($article->solution_script) : ''; ?></textarea>
                    <button type="button" class="kb-copy-btn" data-target="pub_solution_script">📋 העתק</button>
                </div>
                <div class="kb-row"><label class="kb-label">קבצים מצורפים (מספר קבצים):</label>
                    <input type="file" name="solution_files[]" class="kb-input" multiple>
                </div>
            </fieldset>
            
            <fieldset class="kb-fieldset">
                <legend>בדיקה</legend>
                <div class="kb-row"><label class="kb-label">בדיקת פתרון:</label>
                    <textarea class="kb-ckeditor kb-input" name="post_check" id="pub_post_check"><?php echo $article ? $article->post_check : ''; ?></textarea>
                </div>
                <div class="kb-row kb-script-row"><label class="kb-label">סקריפט בדיקה:</label>
                    <textarea class="kb-script-area" name="check_script" id="pub_check_script" dir="ltr"><?php echo $article ? esc_textarea($article->check_script) : ''; ?></textarea>
                    <button type="button" class="kb-copy-btn" data-target="pub_check_script">📋 העתק</button>
                </div>
                <div class="kb-row"><label class="kb-label">קבצי בדיקה מצורפים (מספר קבצים):</label>
                    <input type="file" name="check_files[]" class="kb-input" multiple>
                </div>
            </fieldset>
            
            <fieldset class="kb-fieldset">
                <legend>קישורים רלוונטיים</legend>
                <div class="kb-row"><label class="kb-label">רשימת קישורים:</label>
                    <textarea class="kb-ckeditor kb-input" name="links" id="pub_links"><?php echo $article ? $article->links : ''; ?></textarea>
                </div>
            </fieldset>
            
            <div class="kb-actions">
                <button type="button" id="pub-save-btn2" class="kb-btn-save">💾 שמירה</button>
                <button type="button" id="pub-save-new-btn2" class="kb-btn-save" style="background:#f39c12;">💾 שמור והוסף חדש</button>
                <a href="<?php echo esc_url($kb_home_url); ?>" class="kb-btn-back">← חזור לרשימה</a>
            </div>
            <div id="save-message"></div>
        </form>
        </div>
        
        <style>
        .kb-public-form-container { max-width:900px; margin:30px auto; padding:30px; background:#fff; border-radius:8px; box-shadow:0 2px 10px rgba(0,0,0,0.1); direction:rtl; text-align:right; }
        .kb-public-form-container h2 { text-align:center; color:#2c3e50; margin-bottom:25px; }
        .kb-public-form-header, .kb-actions { display:flex; justify-content:space-between; align-items:center; gap:10px; margin-bottom:20px; flex-wrap:wrap; }
        .kb-btn-save { background:#27ae60; color:#fff; border:none; border-radius:5px; padding:12px 30px; font-size:16px; font-weight:bold; cursor:pointer; text-decoration:none; display:inline-block; }
        .kb-btn-save:hover { background:#229954; }
        .kb-btn-back { background:#95a5a6; color:#fff; border:none; border-radius:5px; padding:12px 30px; font-size:16px; font-weight:bold; cursor:pointer; text-decoration:none; display:inline-block; }
        .kb-btn-back:hover { background:#7f8c8d; }
        .kb-form { direction:rtl; text-align:right; }
        .kb-fieldset { border:1.5px solid #aaa; border-radius:8px; padding:20px; margin-bottom:20px; background:#ececec; direction:rtl; text-align:right; }
        .kb-fieldset legend { font-weight:bold; color:#34495e; padding:0 10px; font-size:1.1em; }
        .kb-row { margin-bottom:15px; position:relative; direction:rtl; text-align:right; }
        .kb-label { display:block; margin-bottom:5px; font-weight:600; color:#555; text-align:right; }
        .kb-input { width:100%; padding:10px; border:1px solid #ccc; border-radius:4px; font-size:15px; text-align:right; direction:rtl; }
        .kb-script-row { position:relative; }
        .kb-script-area { width:100%; min-height:140px; padding:12px; font-family:"Courier New",Consolas,monospace; font-size:14px; line-height:1.5; direction:ltr; text-align:left; border:1px solid #ccc; border-radius:4px; background:#fff; color:#000; }
        .kb-copy-btn { position:absolute; top:35px; left:10px; padding:6px 12px; background:#3498db; color:#fff; border:none; border-radius:4px; cursor:pointer; font-size:12px; z-index:10; }
        .kb-copy-btn:hover { background:#2980b9; }
        #save-message { margin-top:15px; text-align:center; font-size:1.1em; }
        </style>
        
        <script>
        jQuery(document).ready(function(){
            if(window._kbPubInit) return;
            window._kbPubInit=true;
            
            class MyUploadAdapter {
                constructor(loader) {
                    this.loader = loader;
                }
                upload() {
                    return this.loader.file.then(file => new Promise((resolve, reject) => {
                        const reader = new FileReader();
                        reader.onload = () => {
                            const data = new FormData();
                            data.append("action", "upload_pasted_image");
                            data.append("nonce", kbAjax.nonce);
                            data.append("imagedata", reader.result);
                            fetch(kbAjax.ajaxurl, {
                                method: "POST",
                                body: data
                            })
                            .then(res => res.json())
                            .then(json => {
                                if(json.success) resolve({ default: json.data.url });
                                else reject("Upload failed");
                            });
                        };
                        reader.readAsDataURL(file);
                    }));
                }
                abort() {}
            }
            
            function MyCustomUploadAdapterPlugin(editor) {
                editor.plugins.get("FileRepository").createUploadAdapter = (loader) => {
                    return new MyUploadAdapter(loader);
                };
            }
            
            document.querySelectorAll(".kb-ckeditor").forEach(function(el){
                if(!el.classList.contains("ck-initialized")){
                    ClassicEditor.create(el, {
                        extraPlugins: [MyCustomUploadAdapterPlugin]
                    }).then(ed=>{
                        el.classList.add("ck-initialized");
                        el.editorInstance = ed;
                    });
                }
            });
            
            document.querySelectorAll(".kb-copy-btn").forEach(btn => {
                btn.onclick = function() {
                    let target = document.getElementById(this.getAttribute("data-target"));
                    if (target) {
                        target.select();
                        document.execCommand("copy");
                        setTimeout(function(){ window.getSelection().removeAllRanges(); }, 150);
                    }
                }
            });
            
            function saveArticle(openNew) {
                // ⭐ בדיקת שדות חובה
                let subject = document.querySelector("[name=subject]").value.trim();
                let techSolution = document.querySelector("#pub_technical_solution").editorInstance ? 
                    document.querySelector("#pub_technical_solution").editorInstance.getData().trim() : "";
                
                if(!subject) {
                    alert("❌ שדה נושא הוא שדה חובה!");
                    return;
                }
                
                if(!techSolution || techSolution === "<p>&nbsp;</p>" || techSolution === "<p></p>") {
                    alert("❌ שדה פתרון טכני הוא שדה חובה!");
                    return;
                }
                
                let fd = new FormData(document.getElementById("kb-public-form"));
                document.querySelectorAll(".kb-ckeditor").forEach(function(el){
                    if(el.editorInstance) fd.set(el.name, el.editorInstance.getData());
                });
                fd.append("action", "save_article");
                fd.append("article_nonce", document.querySelector("[name=article_nonce]").value);
                
                jQuery.ajax({
                    url: "<?php echo admin_url('admin-ajax.php'); ?>",
                    type: "POST",
                    data: fd,
                    processData: false,
                    contentType: false,
                    success: function(res){
                        if(res.success){
                            jQuery("#save-message").html("<span style=\'color:green;font-size:1.3em;font-weight:bold;\'>✓ המאמר נשמר בהצלחה!</span>");
                            if(openNew) {
                                setTimeout(function(){ location.href = location.pathname; }, 1000);
                            } else {
                                setTimeout(function(){ location.href = '<?php echo esc_js($kb_home_url); ?>'; }, 1500);
                            }
                        }
                        else {
                            if(res.data && res.data.message) {
                                alert("❌ " + res.data.message);
                            } else {
                                jQuery("#save-message").html("<span style=\'color:red;\'>שגיאה בשמירה</span>");
                            }
                        }
                    }
                });
            }
            
            document.getElementById("pub-save-btn").addEventListener("click", function(){ saveArticle(false); });
            document.getElementById("pub-save-new-btn").addEventListener("click", function(){ saveArticle(true); });
            document.getElementById("pub-save-btn2").addEventListener("click", function(){ saveArticle(false); });
            document.getElementById("pub-save-new-btn2").addEventListener("click", function(){ saveArticle(true); });
        });
        </script>
        <?php
        return ob_get_clean();
    }

    public function home_page_shortcode() {
        global $wpdb;
        $table = $wpdb->prefix . 'kb_articles';
        $cats_table = $wpdb->prefix . 'kb_categories';
        
        $search = isset($_GET['kbs']) ? sanitize_text_field($_GET['kbs']) : '';
        $cat_filter = isset($_GET['kbcat']) ? sanitize_text_field($_GET['kbcat']) : '';
        $article_id = isset($_GET['kb_article']) ? intval($_GET['kb_article']) : 0;
        
        $page_id = get_the_ID();
        $page_url = get_permalink($page_id);
        
        if($article_id > 0){
            $article = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE (is_deleted IS NULL OR is_deleted=0) AND id=%d", $article_id));
            if(!$article) return '<div class="kb-notfound">❌ מאמר לא נמצא.</div>';
            
            $add_article_page = get_page_by_path('add-article');
            $edit_url = $add_article_page ? add_query_arg('edit_article', $article->id, get_permalink($add_article_page->ID)) : '';
            
            $back_url = add_query_arg('page_id', $page_id, home_url('/'));
            if($search) $back_url = add_query_arg('kbs', $search, $back_url);
            if($cat_filter) $back_url = add_query_arg('kbcat', $cat_filter, $back_url);
            
            ob_start();
            ?>
            <div class="kb-single-article">
                <div class="kb-article-header">
                    <?php if($edit_url): ?>
                        <a href="<?php echo esc_url($edit_url); ?>" class="kb-btn-edit">✏️ ערוך מאמר</a>
                    <?php endif; ?>
                    <a href="<?php echo esc_url($back_url); ?>" class="kb-btn-back">← חזרה לרשימה</a>
                </div>
                
                <h1><?php echo esc_html($article->subject); ?></h1>
                <div class="kb-meta">📁 <strong><?php echo esc_html($article->category); ?></strong> | 📅 <?php echo esc_html($this->format_hebrew_date($article->created_at)); ?></div>
                <div class="kb-meta kb-meta-status"><?php echo $this->render_status_badge($article->review_status); ?></div>
                <?php if($article->short_desc): ?><div class="kb-section"><h3>תיאור קצר</h3><?php echo $article->short_desc; ?></div><?php endif; ?>
                <?php if($article->technical_desc): ?><div class="kb-section"><h3>תיאור טכני</h3><?php echo $article->technical_desc; ?></div><?php endif; ?>
                <?php if($article->technical_solution): ?><div class="kb-section"><h3>פתרון טכני</h3><?php echo $article->technical_solution; ?></div><?php endif; ?>
                <?php if($article->solution_script): ?>
                <div class="kb-section kb-script-section">
                    <h3>סקריפט פתרון <button type="button" class="kb-copy-btn-inline" onclick="copyScript('sol_script')">📋 העתק</button></h3>
                    <pre id="sol_script" dir="ltr"><?php echo esc_html($article->solution_script); ?></pre>
                </div>
                <?php endif; ?>
                <?php if($article->solution_files): 
                    $files = json_decode($article->solution_files, true);
                    if($files):
                ?>
                <div class="kb-section"><h3>קבצים מצורפים</h3>
                <?php foreach($files as $file): ?>
                    <a href="<?php echo esc_url($file); ?>" target="_blank" class="kb-download-btn">📥 <?php echo basename($file); ?></a><br>
                <?php endforeach; ?>
                </div>
                <?php endif; endif; ?>
                <?php if($article->post_check): ?><div class="kb-section"><h3>בדיקת פתרון</h3><?php echo $article->post_check; ?></div><?php endif; ?>
                <?php if($article->check_script): ?>
                <div class="kb-section kb-script-section">
                    <h3>סקריפט בדיקה <button type="button" class="kb-copy-btn-inline" onclick="copyScript('check_script')">📋 העתק</button></h3>
                    <pre id="check_script" dir="ltr"><?php echo esc_html($article->check_script); ?></pre>
                </div>
                <?php endif; ?>
                <?php if($article->check_files): 
                    $files = json_decode($article->check_files, true);
                    if($files):
                ?>
                <div class="kb-section"><h3>קבצי בדיקה</h3>
                <?php foreach($files as $file): ?>
                    <a href="<?php echo esc_url($file); ?>" target="_blank" class="kb-download-btn">📥 <?php echo basename($file); ?></a><br>
                <?php endforeach; ?>
                </div>
                <?php endif; endif; ?>
                <?php if($article->links): ?><div class="kb-section"><h3>קישורים רלוונטיים</h3><?php echo $article->links; ?></div><?php endif; ?>
                
                <div class="kb-article-footer">
                    <?php if($edit_url): ?>
                        <a href="<?php echo esc_url($edit_url); ?>" class="kb-btn-edit">✏️ ערוך מאמר</a>
                    <?php endif; ?>
                    <a href="<?php echo esc_url($back_url); ?>" class="kb-btn-back">← חזרה לרשימה</a>
                </div>
            </div>
            <style>
            .kb-article-header, .kb-article-footer { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; gap:10px; flex-wrap:wrap; }
            .kb-article-footer { margin-top:30px; margin-bottom:0; }
            .kb-btn-edit { display:inline-block; padding:10px 20px; background:#f39c12; color:#fff; text-decoration:none; border-radius:5px; font-weight:bold; order:1; }
            .kb-btn-edit:hover { background:#e67e22; }
            .kb-single-article { max-width:900px; margin:30px auto; padding:30px; background:#fff; border-radius:8px; box-shadow:0 2px 10px rgba(0,0,0,0.1); }
            .kb-single-article h1 { color:#2c3e50; margin:20px 0 15px; }
            .kb-meta { color:#7f8c8d; margin-bottom:25px; font-size:0.95em; }
            .kb-section { margin:25px 0; padding:20px; background:#ececec; border-right:5px solid #3498db; border-radius:7px; }
            .kb-section h3 { margin-top:0; color:#34495e; font-size:1.3em; }
            .kb-script-section h3 { display:flex; justify-content:space-between; align-items:center; }
            .kb-section pre { background:transparent; color:#222; padding:15px 0; border:none; overflow-x:auto; white-space:pre-wrap; direction:ltr; text-align:left; font-family:"Courier New",Consolas,monospace; font-size:14px; line-height:1.5; }
            .kb-section img { max-width:100%; height:auto; border-radius:5px; margin:10px 0; }
            .kb-download-btn { display:inline-block; padding:10px 20px; background:#3498db; color:#fff; text-decoration:none; border-radius:5px; font-weight:bold; margin:5px 5px 5px 0; }
            .kb-download-btn:hover { background:#2980b9; }
            .kb-btn-back { display:inline-block; padding:10px 20px; background:#95a5a6; color:#fff; text-decoration:none; border-radius:5px; font-weight:bold; order:2; }
            .kb-btn-back:hover { background:#7f8c8d; }
            .kb-copy-btn-inline { padding:5px 12px; background:#27ae60; color:#fff; border:none; border-radius:4px; cursor:pointer; font-size:13px; }
            .kb-copy-btn-inline:hover { background:#229954; }
            </style>
            <script>
            function copyScript(id){
                let el = document.getElementById(id);
                if(el){
                    let range = document.createRange();
                    range.selectNode(el);
                    window.getSelection().removeAllRanges();
                    window.getSelection().addRange(range);
                    document.execCommand('copy');
                    setTimeout(function(){ window.getSelection().removeAllRanges(); }, 150);
                }
            }
            </script>
            <?php
            return ob_get_clean();
        }
        
        $add_article_page = get_page_by_path('add-article');
        $add_article_url = $add_article_page ? get_permalink($add_article_page->ID) : home_url('/add-article/');
        
        ob_start();
        ?>
        <div class="kb-home-container">
            <div class="kb-home-header">
                <h1>המאגר</h1>
                <div class="kb-home-actions">
                    <a href="<?php echo esc_url($add_article_url); ?>" class="kb-btn kb-btn-primary">➕ הוסף מאמר חדש</a>
                    <button type="button" id="kb-toggle-cats" class="kb-btn kb-btn-secondary">📁 עיון לפי קטגוריות</button>
                    <button type="button" id="kb-open-cat-popup" class="kb-btn kb-btn-warning">⚙️ ערוך קטגוריות</button>
                </div>
            </div>
            
            <div class="kb-search-box">
                <form method="get" action="<?php echo esc_url(home_url('/')); ?>" class="kb-search-form-home">
                    <input type="hidden" name="page_id" value="<?php echo $page_id; ?>">
                    <input type="text" name="kbs" placeholder="🔍 חפש מאמר לפי מילה, ביטוי או נושא..." value="<?php echo esc_attr($search); ?>" class="kb-search-input">
                    <button type="submit" class="kb-btn kb-btn-search">חפש</button>
                    <?php if($search || $cat_filter): ?>
                        <a href="<?php echo esc_url($page_url); ?>" class="kb-btn kb-btn-clear">✖ נקה</a>
                    <?php endif; ?>
                </form>
            </div>
            
            <div id="kb-categories-panel" style="display:none;" class="kb-categories-panel">
                <h3>בחר קטגוריה:</h3>
                <ul class="kb-cat-list">
                    <li><a href="<?php echo esc_url($page_url); ?>">📂 כל המאמרים</a></li>
                    <?php
                    $cats = $wpdb->get_results("SELECT * FROM $cats_table ORDER BY parent_id, sort_order, category_name");
                    function print_cats_links($cats, $parent, $level, $base_url, $page_id) {
                        foreach($cats as $cat) {
                            if($cat->parent_id == $parent) {
                                $indent = str_repeat('&nbsp;&nbsp;&nbsp;', $level);
                                $cat_url = add_query_arg(['page_id' => $page_id, 'kbcat' => urlencode($cat->category_name)], home_url('/'));
                                echo '<li>'.$indent.'<a href="'.esc_url($cat_url).'">'.esc_html($cat->category_name).'</a></li>';
                                print_cats_links($cats, $cat->id, $level+1, $base_url, $page_id);
                            }
                        }
                    }
                    print_cats_links($cats, 0, 0, $page_url, $page_id);
                    ?>
                </ul>
            </div>
            
            <div id="kb-cat-popup" style="display:none;">
                <div class="kb-cat-popup-overlay" onclick="document.getElementById('kb-cat-popup').style.display='none';"></div>
                <div class="kb-cat-popup-content">
                    <span class="kb-cat-close" onclick="document.getElementById('kb-cat-popup').style.display='none';">×</span>
                    <h3>ניהול קטגוריות</h3>
                    <div id="kb-cat-list"></div>
                    <hr>
                    <h4>הוסף קטגוריה חדשה</h4>
                    <input type="text" id="new_cat_name" placeholder="שם קטגוריה" style="padding:8px;width:200px;">
                    <select id="new_cat_parent" style="padding:8px;">
                        <option value="0">ראשי</option>
                    </select>
                    <button type="button" onclick="kbAddCategory()" class="kb-btn kb-btn-success">➕ הוסף</button>
                    <div id="cat-message" style="margin-top:10px;"></div>
                </div>
            </div>
            
            <div class="kb-results">
                <?php
                $sql = "SELECT * FROM $table WHERE (is_deleted IS NULL OR is_deleted=0)";
                if($search !== '') {
                    $like = '%' . $wpdb->esc_like($search) . '%';
                    $sql .= $wpdb->prepare(" AND (subject LIKE %s OR short_desc LIKE %s OR technical_desc LIKE %s OR category LIKE %s)", $like, $like, $like, $like);
                }
                if($cat_filter !== '' && $cat_filter !== 'all') {
                    $sql .= $wpdb->prepare(" AND category LIKE %s", '%'.$wpdb->esc_like($cat_filter).'%');
                }
                $sql .= " ORDER BY created_at DESC LIMIT 50";
                $results = $wpdb->get_results($sql);
                
                if($results): ?>
                    <h2>נמצאו <?php echo count($results); ?> מאמרים</h2>
                    <?php foreach($results as $article): 
                        $excerpt = wp_strip_all_tags($article->short_desc ? $article->short_desc : $article->technical_desc);
                        $excerpt = mb_substr($excerpt, 0, 150) . '...';
                        $article_url = add_query_arg(['page_id' => $page_id, 'kb_article' => $article->id], home_url('/'));
                    ?>
                    <div class="kb-result-item">
                        <div class="kb-result-header">
                            <h3><a href="<?php echo esc_url($article_url); ?>"><?php echo esc_html($article->subject); ?></a></h3>
                            <div class="kb-result-status"><?php echo $this->render_status_badge($article->review_status); ?></div>
                        </div>
                        <div class="kb-meta">
                            <span class="kb-category">📁 <?php echo esc_html($article->category); ?></span> |
                            <span class="kb-date">📅 <?php echo esc_html($this->format_hebrew_date($article->created_at)); ?></span>
                        </div>
                        <p><?php echo esc_html($excerpt); ?></p>
                        <a href="<?php echo esc_url($article_url); ?>" class="kb-read-more">קרא עוד →</a>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="kb-no-results">
                        <p>❌ לא נמצאו מאמרים התואמים לחיפוש שלך.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <style>
        .kb-home-container { max-width:900px; margin:30px auto; padding:20px; font-family:Arial,sans-serif; }
        .kb-home-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:25px; flex-wrap:wrap; gap:15px; }
        .kb-home-header h1 { margin:0; color:#2c3e50; }
        .kb-home-actions { display:flex; gap:10px; flex-wrap:wrap; }
        .kb-btn { padding:10px 20px; border:none; border-radius:5px; cursor:pointer; text-decoration:none; display:inline-block; font-size:15px; font-weight:600; transition:all 0.3s; color:#fff; }
        .kb-btn-primary { background:#3498db; }
        .kb-btn-primary:hover { background:#2980b9; }
        .kb-btn-secondary { background:#95a5a6; }
        .kb-btn-secondary:hover { background:#7f8c8d; }
        .kb-btn-warning { background:#f39c12; }
        .kb-btn-warning:hover { background:#e67e22; }
        .kb-btn-search { background:#27ae60; }
        .kb-btn-search:hover { background:#229954; }
        .kb-btn-clear { background:#e74c3c; padding:10px 15px; }
        .kb-btn-clear:hover { background:#c0392b; }
        .kb-btn-success { background:#27ae60; }
        .kb-btn-success:hover { background:#229954; }
        .kb-search-box { margin-bottom:20px; }
        .kb-search-form-home { display:flex; gap:8px; flex-wrap:wrap; }
        .kb-search-input { flex:1; min-width:250px; padding:10px 15px; border:1px solid #bdc3c7; border-radius:5px; font-size:16px; }
        .kb-categories-panel { background:#ececec; padding:20px; border-radius:8px; margin-bottom:20px; box-shadow:0 2px 5px rgba(0,0,0,0.1); }
        .kb-categories-panel h3 { margin-top:0; color:#34495e; }
        .kb-cat-list { list-style:none; padding:0; }
        .kb-cat-list li { padding:8px 0; }
        .kb-cat-list a { text-decoration:none; color:#2980b9; font-weight:500; }
        .kb-cat-list a:hover { color:#3498db; text-decoration:underline; }
        .kb-results h2 { color:#34495e; margin-bottom:20px; font-size:1.5em; }
        .kb-result-item { background:#fff; border:1px solid #ddd; border-radius:8px; padding:25px; margin-bottom:20px; box-shadow:0 2px 5px rgba(0,0,0,0.05); }
        .kb-result-item:hover { box-shadow:0 4px 12px rgba(0,0,0,0.15); }
        .kb-result-item h3 { margin:0 0 10px 0; font-size:1.5em; }
        .kb-result-item h3 a { color:#2c3e50; text-decoration:none; }
        .kb-result-item h3 a:hover { color:#3498db; }
        .kb-result-header { display:flex; justify-content:space-between; align-items:center; gap:10px; }
        .kb-result-status { flex-shrink:0; }
        .kb-meta { font-size:0.9em; color:#7f8c8d; margin-bottom:12px; }
        .kb-category { font-weight:bold; color:#e67e22; }
        .kb-result-item p { margin:12px 0; line-height:1.7; color:#555; }
        .kb-read-more { color:#3498db; text-decoration:none; font-weight:bold; font-size:1.05em; }
        .kb-read-more:hover { text-decoration:underline; color:#2980b9; }
        .kb-no-results { text-align:center; padding:60px 20px; color:#95a5a6; font-size:1.3em; }
        .kb-cat-popup-overlay { position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); z-index:9998; }
        .kb-cat-popup-content { position:fixed; top:50%; left:50%; transform:translate(-50%,-50%); background:#fff; padding:25px; border-radius:8px; box-shadow:0 4px 15px rgba(0,0,0,0.3); z-index:9999; max-width:600px; width:90%; max-height:70vh; overflow-y:auto; }
        .kb-cat-close { position:absolute; top:8px; left:12px; font-size:30px; font-weight:bold; color:#aaa; cursor:pointer; }
        .kb-cat-close:hover { color:#000; }
        #kb-cat-list table { width:100%; border-collapse:collapse; margin-bottom:15px; font-size:14px; }
        #kb-cat-list th, #kb-cat-list td { border:1px solid #ddd; padding:8px; text-align:right; }
        #kb-cat-list th { background:#34495e; color:#fff; }
        .kb-cat-btn-del { padding:4px 8px; background:#e74c3c; color:#fff; border:none; border-radius:3px; cursor:pointer; font-size:12px; }
        .kb-cat-btn-del:hover { background:#c0392b; }
        .kb-status-badge { display:inline-flex; align-items:center; gap:6px; padding:6px 12px; border-radius:20px; font-weight:700; font-size:13px; }
        .kb-status-badge--red { background:#fee2e2; color:#b91c1c; }
        .kb-status-badge--orange { background:#ffedd5; color:#c2410c; }
        .kb-status-badge--green { background:#dcfce7; color:#15803d; }
        .kb-meta-status { margin:6px 0 14px; }
        </style>
        
        <script>
        document.getElementById('kb-toggle-cats').addEventListener('click', function(){
            var panel = document.getElementById('kb-categories-panel');
            panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
        });
        
        document.getElementById('kb-open-cat-popup').addEventListener('click', function(){
            document.getElementById('kb-cat-popup').style.display='block';
            kbLoadCategories();
        });
        
        function kbLoadCategories(){
            fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=kb_get_categories'
            })
            .then(res=>res.json())
            .then(json=>{
                if(json.success){
                    let cats = json.data;
                    let html = '<table><tr><th>שם</th><th>תחת</th><th>סדר</th><th></th></tr>';
                    cats.forEach(c=>{
                        let parentName = c.parent_id ? cats.find(x=>x.id==c.parent_id)?.category_name || 'ראשי' : 'ראשי';
                        html += '<tr><td>'+c.category_name+'</td><td>'+(parentName.length>15?parentName.substr(0,15)+'...':parentName)+'</td><td><input type="number" id="order_'+c.id+'" value="'+c.sort_order+'" style="width:50px;"></td><td><button class="kb-cat-btn-del" onclick="kbDeleteCat('+c.id+')">מחק</button></td></tr>';
                    });
                    html += '</table><button class="kb-btn kb-btn-primary" onclick="kbUpdateOrder()" style="padding:8px 15px;font-size:14px;">עדכן סדר</button>';
                    document.getElementById('kb-cat-list').innerHTML = html;
                    
                    let parentSel = '<option value="0">ראשי</option>';
                    cats.filter(c=>c.parent_id==0).forEach(c=>{
                        parentSel += '<option value="'+c.id+'">'+c.category_name+'</option>';
                    });
                    document.getElementById('new_cat_parent').innerHTML = parentSel;
                }
            });
        }
        
        function kbAddCategory(){
            let name = document.getElementById('new_cat_name').value;
            let parent = document.getElementById('new_cat_parent').value;
            if(!name) { alert('שם חסר'); return; }
            let fd = new FormData();
            fd.append('action', 'kb_add_category');
            fd.append('nonce', kbAjax.nonce);
            fd.append('cat_name', name);
            fd.append('parent_id', parent);
            fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                method: 'POST',
                body: fd
            }).then(res=>res.json()).then(json=>{
                if(json.success){
                    document.getElementById('cat-message').innerHTML = '<span style="color:green;">✓ נוסף בהצלחה</span>';
                    document.getElementById('new_cat_name').value = '';
                    kbLoadCategories();
                } else {
                    alert('שגיאה');
                }
            });
        }
        
        function kbDeleteCat(id){
            if(!confirm('למחוק קטגוריה?')) return;
            let fd = new FormData();
            fd.append('action', 'kb_delete_category');
            fd.append('nonce', kbAjax.nonce);
            fd.append('cat_id', id);
            fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                method: 'POST',
                body: fd
            }).then(res=>res.json()).then(json=>{
                if(json.success) kbLoadCategories();
            });
        }
       
        function kbUpdateOrder(){
            let orders = {};
            document.querySelectorAll('[id^="order_"]').forEach(el=>{
                let id = el.id.replace('order_','');
                orders[id] = el.value;
            });
            let fd = new FormData();
            fd.append('action', 'kb_update_order');
            fd.append('nonce', kbAjax.nonce);
            for(let k in orders) fd.append('orders['+k+']', orders[k]);
            fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                method: 'POST',
                body: fd
            }).then(res=>res.json()).then(json=>{
                if(json.success){
                    alert('✓ הסדר עודכן');
                    kbLoadCategories();
                }
            });
        }
        </script>
        <?php
        return ob_get_clean();
    }
}

new KB_KnowledgeBase_Editor();


/*
 * === KB KnowledgeBase – Unified Core (embedded) ===
 * Features: Tree button (home + single), tree link fix, article status (DB + badges + AJAX).
 */
if ( ! class_exists('KB_KnowledgeBase_Unified_Core') ) {
class KB_KnowledgeBase_Unified_Core {

    const TREE_PAGE_ID = 11102;  // עץ קטגוריות
    const LIST_PAGE_ID = 10852;  // דף רשימת המאמרים
    const STATUS_FIELD = 'review_status'; // עמודת סטטוס בטבלת kb_articles

    public static function status_labels() { return [0=>'לא נבדק',1=>'בתהליך',2=>'תקין']; }

    public function __construct() {
        add_action('plugins_loaded', [__CLASS__,'maybe_add_column']);
        add_action('wp_enqueue_scripts', [$this,'enqueue_css']);

        add_action('wp_footer', [$this,'render_home_tree_button']);                       // עץ בעמוד הבית
        add_filter('the_content', [$this,'tree_prepend_back_and_tree'], 5);               // חזרה+עץ בעמוד העץ
        add_action('template_redirect', [$this,'tree_start_buffer'], 0);                  // תיקון קישורים בעץ

        add_filter('the_content', [$this,'single_article_header'], 8);                    // כפתורים + סטטוס בדף מאמר
        add_action('template_redirect', [$this,'tree_start_status_badges'], 1);           // תג סטטוס ליד כל מאמר בעץ

        add_action('wp_ajax_kb_set_review_status', [$this,'ajax_set_status']);            // שמירת סטטוס
    }

    public static function maybe_add_column() {
        global $wpdb;
        $table = $wpdb->prefix.'kb_articles';
        $col   = self::STATUS_FIELD;
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s AND COLUMN_NAME=%s",
            DB_NAME, $table, $col
        ) );
        if ( ! $exists ) {
            $wpdb->query("ALTER TABLE `$table` ADD `$col` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0=לא נבדק,1=בתהליך,2=תקין' AFTER `links`");
        }
    }

    public function enqueue_css() {
        $css = "
        .kb-badge{display:inline-flex;align-items:center;gap:.5ch;border-radius:999px;padding:.25rem .6rem;font-weight:700;font-size:.85rem;line-height:1;color:#fff;vertical-align:middle}
        .kb-badge svg{width:12px;height:12px}
        .kb-badge--red{background:#dc2626}.kb-dot--red{fill:#fecaca;stroke:#7f1d1d;stroke-width:.8}
        .kb-badge--orange{background:#ea580c}.kb-dot--orange{fill:#fed7aa;stroke:#7c2d12;stroke-width:.8}
        .kb-badge--green{background:#16a34a}.kb-dot--green{fill:#bbf7d0;stroke:#14532d;stroke-width:.8}
        .kb-top-status{margin:6px 0 10px}
        .kb-inline-buttons{display:flex;gap:.5rem;align-items:center;margin:10px 0 14px}
        .kb-btn{display:inline-block;padding:10px 16px;border-radius:12px;font-weight:700;text-decoration:none!important;color:#fff!important;box-shadow:0 3px 10px rgba(0,0,0,.12)}
        .kb-btn--grey{background:#94a3b8}.kb-btn--blue{background:#1e40af}
        .kb-btn:hover{filter:brightness(1.06)}
        ";
        wp_register_style('kb-unified-core-embed', false, [], null);
        wp_add_inline_style('kb-unified-core-embed', $css);
        wp_enqueue_style('kb-unified-core-embed');
    }

    public function render_home_tree_button() {
        if ( ! (is_front_page() || is_home()) ) return;
        $url = esc_url( add_query_arg(['page_id'=> self::TREE_PAGE_ID], home_url('/')) );
        echo '<div style="position:fixed;right:18px;bottom:24px;z-index:9999">';
        echo '<a class="kb-btn kb-btn--blue" href="'.$url.'">עץ קטגוריות</a>';
        echo '</div>';
    }

    public function tree_prepend_back_and_tree($content) {
        if ( ! is_page(self::TREE_PAGE_ID) ) return $content;
        $home = preg_quote( home_url('/'), '~' );
        $content = preg_replace('~<a[^>]+href=["\']'.$home.'["\'][^>]*>.*?</a>~i', '', $content);
        $back_url = esc_url( add_query_arg(['page_id'=> self::LIST_PAGE_ID], home_url('/')) );
        $tree_url = esc_url( add_query_arg(['page_id'=> self::TREE_PAGE_ID], home_url('/')) );
        $btns = '<div class="kb-inline-buttons"><a class="kb-btn kb-btn--grey" href="'.$back_url.'">חזרה לרשימה ←</a>'.
                '<a class="kb-btn kb-btn--blue" href="'.$tree_url.'">עץ קטגוריות</a></div>';
        return $btns . $content;
    }

    public function tree_start_buffer() {
        if ( ! is_page(self::TREE_PAGE_ID) ) return;
        ob_start([$this,'tree_buffer_cb']);
    }

    public function tree_buffer_cb($html) {
        $html = preg_replace_callback('~href=([\'"])([^\'"]*?)\\?(?:(?!page_id=)[^\'"]*?)\\bkb_article=(\\d+)\\1~i',
            function($m){ $q=$m[1]; $kb=$m[3]; $new=add_query_arg(['page_id'=>self::LIST_PAGE_ID,'kb_article'=>$kb],home_url('/')); return 'href='.$q.$new.$q; }, $html);
        $html = preg_replace_callback('~href=([\'"])/(?:\\?)*kb_article=(\\d+)\\1~i',
            function($m){ $q=$m[1]; $kb=$m[2]; $new=add_query_arg(['page_id'=>self::LIST_PAGE_ID,'kb_article'=>$kb],home_url('/')); return 'href='.$q.$new.$q; }, $html);
        return $html;
    }

    public function single_article_header($content) {
        if ( ! is_page(self::LIST_PAGE_ID) ) return $content;
        $id = isset($_GET['kb_article']) ? intval($_GET['kb_article']) : 0;
        if ( ! $id ) return $content;
        $badge = self::badge_html_for_article($id, true);
        $back_url = esc_url( add_query_arg(['page_id'=> self::LIST_PAGE_ID], home_url('/')) );
        $tree_url = esc_url( add_query_arg(['page_id'=> self::TREE_PAGE_ID], home_url('/')) );
        $btns = '<div class="kb-inline-buttons"><a class="kb-btn kb-btn--grey" href="'.$back_url.'">חזרה לרשימה ←</a>'.
                '<a class="kb-btn kb-btn--blue" href="'.$tree_url.'">עץ קטגוריות</a></div>';
        return $btns . '<div class="kb-top-status">'.$badge.'</div>' . $content;
    }

    public function tree_start_status_badges() {
        if ( ! is_page(self::TREE_PAGE_ID) ) return;
        ob_start(function($html){
            $pattern = '~(<a[^>]+\\?page_id='.self::LIST_PAGE_ID.'&amp;kb_article=(\\d+)[^>]*>)(.*?)(</a>)~i';
            return preg_replace_callback($pattern, function($m){
                $before=$m[1]; $id=intval($m[2]); $text=$m[3]; $after=$m[4];
                $badge = KB_KnowledgeBase_Unified_Core::badge_html_for_article($id, false);
                return $before.$text.$after.' '.$badge;
            }, $html);
        });
    }

    public static function badge_html_for_article($article_id, $with_admin_control=false) {
        global $wpdb;
        $table = $wpdb->prefix.'kb_articles';
        $row = $wpdb->get_row( $wpdb->prepare("SELECT id, ".self::STATUS_FIELD." AS st FROM `$table` WHERE id=%d", $article_id) );
        if ( ! $row ) return '';
        $st = is_null($row->st) ? 0 : intval($row->st);
        $labels = self::status_labels(); $label = isset($labels[$st]) ? $labels[$st] : $labels[0];
        $class='kb-badge '; $dot='';
        if ($st===2){ $class.='kb-badge--green'; $dot='<svg viewBox="0 0 16 16"><circle class="kb-dot--green" cx="8" cy="8" r="5"/></svg>'; }
        elseif ($st===1){ $class.='kb-badge--orange'; $dot='<svg viewBox="0 0 16 16"><circle class="kb-dot--orange" cx="8" cy="8" r="5"/></svg>'; }
        else { $class.='kb-badge--red'; $dot='<svg viewBox="0 0 16 16"><circle class="kb-dot--red" cx="8" cy="8" r="5"/></svg>'; }
        $html = '<span class="'.$class.'" data-article="'.$article_id.'">'.$dot.' '.$label.'</span>';
        if ($with_admin_control && current_user_can('manage_options')) {
            $opts=''; foreach($labels as $k=>$t){ $opts.='<option value="'.$k.'" '.selected($k,$st,false).'>'.$t.'</option>'; }
            $nonce = wp_create_nonce('kb_set_review_status_'.$article_id);
            $html .= ' <select class="kb-status-select" data-id="'.$article_id.'" data-nonce="'.$nonce.'">'.$opts.'</select>';
            $html .= '<script>
            document.addEventListener("change",function(e){
                if(!e.target.classList.contains("kb-status-select")) return;
                var id=e.target.getAttribute("data-id"), nonce=e.target.getAttribute("data-nonce"), val=e.target.value;
                var fd=new FormData(); fd.append("action","kb_set_review_status"); fd.append("id",id); fd.append("status",val); fd.append("_wpnonce",nonce);
                fetch("'.admin_url('admin-ajax.php').'",{method:"POST",credentials:"same-origin",body:fd})
                .then(r=>r.json()).then(function(res){ if(res&&res.ok){ location.reload(); } else { alert("שמירת סטטוס נכשלה"); }})
                .catch(function(){ alert("שגיאת רשת"); });
            });
            </script>';
        }
        return $html;
    }

    public function ajax_set_status() {
        if ( ! current_user_can('manage_options') ) wp_send_json_error();
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $status = isset($_POST['status']) ? intval($_POST['status']) : 0;
        check_admin_referer('kb_set_review_status_'.$id);
        if ($id<=0 || $status<0 || $status>2) wp_send_json_error();
        global $wpdb; $table=$wpdb->prefix.'kb_articles';
        $ok = $wpdb->update($table, [ self::STATUS_FIELD=>$status ], ['id'=>$id], ['%d'], ['%d']);
        if ($ok === false) wp_send_json_error();
        wp_send_json_success(['ok'=>true]);
    }
}
add_action('plugins_loaded', function(){ new KB_KnowledgeBase_Unified_Core(); });
}
/* === End Unified Core (embedded) === */


/* Inject "עץ קטגוריות" button on homepage between "עיון לפי קטגוריות" and "הוסף מאמר חדש" */
if ( ! function_exists('kb_home_start_buffer_inject') ) {
    function kb_home_start_buffer_inject(){
        if ( ! ( is_front_page() || is_home() ) ) return;
        ob_start('kb_home_inject_cb');
    }
    function kb_home_inject_cb($html){
        $browse_pos = mb_strpos($html, 'עיון לפי קטגוריות');
        $add_pos    = mb_strpos($html, 'הוסף מאמר חדש');
        if ($browse_pos !== false && $add_pos !== false && $add_pos > $browse_pos) {
            $start_a = mb_strrpos(mb_substr($html, 0, $browse_pos), '<a');
            $end_browse = mb_strpos($html, '</a>', $browse_pos);
            if ($start_a !== false && $end_browse !== false) {
                $end_browse += 4;
                $a_tag = mb_substr($html, $start_a, $end_browse - $start_a);
                $cls = 'kb-btn kb-btn-secondary';
                if (preg_match('~class=["\']([^"\']+)["\']~u', $a_tag, $m)) $cls = $m[1];
                $url = esc_url( add_query_arg(['page_id'=> 11102], home_url('/')) );
                $new = '<a href="'. $url .'" class="'. esc_attr($cls) .'">עץ קטגוריות</a>';
                $html = mb_substr($html, 0, $end_browse) . $new . mb_substr($html, $end_browse);
            }
        }
        return $html;
    }
    add_action('template_redirect','kb_home_start_buffer_inject', 0);
}

/* === KB: Force inject "עץ קטגוריות" button on homepage (syntax-safe) === */
if ( ! function_exists('kb_force_tree_btn_assets') ) {
    function kb_force_tree_btn_assets() {
        if ( ! ( is_front_page() || is_home() ) ) { return; }
        $js = <<<'JS'
(function($){
  $(function(){
    try{
      var browse = null, add = null;
      $('a').each(function(){
        var t = $(this).text().trim();
        if(!browse && t.indexOf('עיון לפי קטגוריות') > -1){ browse = this; }
        if(!add && t.indexOf('הוסף מאמר חדש') > -1){ add = this; }
      });
      if(!browse && !add) return;
      var cls = '';
      if(browse && browse.getAttribute){ cls = browse.getAttribute('class') || ''; }
      if(!cls && add && add.getAttribute){ cls = add.getAttribute('class') || ''; }
      if(!cls) cls = 'btn btn-secondary';
      var $link = $('<a>', { href: 'https://kb.macomp.co.il/?page_id=11102', 'class': cls, text: 'עץ קטגוריות' });
      if(add){ $(add).before($link); } else if(browse){ $(browse).after($link); }
    }catch(e){}
  });
})(jQuery);
JS;
        wp_add_inline_script('jquery-core', $js);
    }
    add_action('wp_enqueue_scripts', 'kb_force_tree_btn_assets', 20);
}
/* === /KB === */
