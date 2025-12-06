<?php
/*
Plugin Name: KB Cyber KnowledgeBase 
Description: המאגר - תיקון באגים: כפילויות, שדות חובה, תאריך, ניווט. עברית מלאה.
Version: 20.0 BUGS FIXED FINAL
*/

if (!defined('ABSPATH')) exit;

class KB_KnowledgeBase_Editor {

    /**
     * Determine whether the current user can edit articles from the public views.
     * Falls back to standard authoring capabilities (edit_posts) and admin access.
     *
     * @return bool
     */
    public function user_can_edit_article() {
        return current_user_can('manage_options') || current_user_can('edit_posts');
    }

    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_upload_pasted_image', [$this, 'upload_image']);
        add_action('wp_ajax_nopriv_upload_pasted_image', [$this, 'upload_image']);
        add_action('wp_ajax_kb_upload_image', [$this, 'upload_image']);
        add_action('wp_ajax_nopriv_kb_upload_image', [$this, 'upload_image']);
        add_action('wp_ajax_save_article', [$this, 'save_article']);
        add_action('wp_ajax_nopriv_save_article', [$this, 'save_article']);
        add_action('wp_ajax_kb_get_categories', [$this, 'ajax_get_categories']);
        add_action('wp_ajax_nopriv_kb_get_categories', [$this, 'ajax_get_categories']);
        add_action('wp_ajax_kb_add_category', [$this, 'ajax_add_category']);
        add_action('wp_ajax_nopriv_kb_add_category', [$this, 'ajax_add_category']);
        add_action('wp_ajax_kb_delete_category', [$this, 'ajax_delete_category']);
        add_action('wp_ajax_nopriv_kb_delete_category', [$this, 'ajax_delete_category']);
        add_action('wp_ajax_kb_update_category', [$this, 'ajax_update_category']);
        add_action('wp_ajax_nopriv_kb_update_category', [$this, 'ajax_update_category']);
        add_action('wp_ajax_kb_update_order', [$this, 'ajax_update_order']);
        add_action('wp_ajax_nopriv_kb_update_order', [$this, 'ajax_update_order']);
        add_action('wp_ajax_kb_check_subject', [$this, 'ajax_check_subject']);
                add_action('wp_ajax_nopriv_kb_check_subject', [$this, 'ajax_check_subject']);
        add_action('wp_ajax_kb_save_bulk_articles', [$this, 'save_bulk_articles']);
        add_action('wp_ajax_nopriv_kb_save_bulk_articles', [$this, 'save_bulk_articles']);
        add_action('wp_ajax_kb_import_word', [$this, 'handle_import_word_ajax']);
        add_action('wp_ajax_nopriv_kb_import_word', [$this, 'handle_import_word_ajax']);
        add_action('wp_ajax_kb_download_word_template', [$this, 'download_word_template']);
        add_action('wp_ajax_nopriv_kb_download_word_template', [$this, 'download_word_template']);
        add_shortcode('kb_categories_tree', [$this, 'shortcode_tree']);
        add_shortcode('kb_articles_table', [$this, 'articles_table_shortcode']);
        add_shortcode('kb_trash_bin', [$this, 'trash_bin_shortcode']);
        add_shortcode('kb_archive_bin', [$this, 'archive_bin_shortcode']);

        add_action('init', [$this, 'disable_cache_for_kb'], 1);

        register_activation_hook(__FILE__, [$this, 'create_table']);
        add_shortcode('kb_public_form', [$this, 'public_form_shortcode']);
        // bulk grid editor shortcode (renamed to avoid conflicts)
        add_shortcode('kb_public_grid', [$this, 'bulk_public_grid_shortcode']);
        add_shortcode('kb_manage_categories', [$this, 'manage_categories_shortcode']);
        add_shortcode('kb_maagar_setings', [$this, 'settings_shortcode']);
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
            if (!headers_sent()) {
                nocache_headers();
            }
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
            user_rating TINYINT(3) DEFAULT NULL,
            vulnerability_level TINYINT(1) DEFAULT NULL,
            review_status TINYINT(1) NOT NULL DEFAULT 0,
            is_archived TINYINT(1) NOT NULL DEFAULT 0,
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
        $cols5b = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'is_archived'");
        if(empty($cols5b)) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0 AFTER is_deleted");
        }
        $cols6 = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'user_rating'");
        if(empty($cols6)) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN user_rating TINYINT(3) DEFAULT NULL AFTER links");
        } else {
            $wpdb->query("ALTER TABLE $table MODIFY user_rating TINYINT(3) DEFAULT NULL");
        }
        $cols7 = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'vulnerability_level'");
        if(empty($cols7)) {
            $wpdb->query("ALTER TABLE $table ADD COLUMN vulnerability_level TINYINT(1) DEFAULT NULL AFTER user_rating");
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
        wp_enqueue_style('kb-style', plugins_url('css/style.css', __FILE__), [], time());
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

    public function ajax_update_category() {
        check_ajax_referer('kbnonce', 'nonce');
        global $wpdb;

        $id = isset($_POST['cat_id']) ? intval($_POST['cat_id']) : 0;
        $name = isset($_POST['cat_name']) ? sanitize_text_field(wp_unslash($_POST['cat_name'])) : '';
        $parent = isset($_POST['parent_id']) ? intval($_POST['parent_id']) : 0;
        $order = isset($_POST['sort_order']) ? intval($_POST['sort_order']) : 0;

        if(!$id || $name === '') {
            wp_send_json_error(['message' => 'נתוני קטגוריה חסרים']);
        }

        if($parent === $id) {
            wp_send_json_error(['message' => 'לא ניתן להציב קטגוריה כהורה של עצמה']);
        }

        $wpdb->update(
            $wpdb->prefix.'kb_categories',
            [
                'category_name' => $name,
                'parent_id' => $parent,
                'sort_order' => $order,
            ],
            ['id' => $id],
            ['%s','%d','%d'],
            ['%d']
        );

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

    public function manage_categories_shortcode() {
        global $wpdb;
        $cats_table = $wpdb->prefix . 'kb_categories';
        $cats = $wpdb->get_results("SELECT * FROM {$cats_table} ORDER BY parent_id, sort_order, category_name");

        $options = '<option value="0">ראשי</option>';
        foreach($cats as $c) {
            $options .= '<option value="'.intval($c->id).'">'.esc_html($c->category_name).'</option>';
        }

        ob_start();
        ?>
        <div class="kb-container">
            <?php echo $this->render_navigation_bar('categories'); ?>
            <div class="kb-cat-manager">
                <div class="kb-cat-manager__header">
                    <div>
                        <h2>ניהול קטגוריות</h2>
                        <p class="kb-text-muted">הוספה, שינוי מיקום, עריכה או מחיקה של קטגוריות ותתי קטגוריות במאגר.</p>
                    </div>
                    <div class="kb-cat-add">
                        <input type="text" id="kb-cat-new-name" class="kb-input" placeholder="שם קטגוריה חדשה">
                        <select id="kb-cat-new-parent" class="kb-input"><?php echo $options; ?></select>
                        <button type="button" id="kb-cat-add-btn" class="kb-btn kb-btn-primary">➕ הוסף</button>
                    </div>
                </div>

                <div class="kb-cat-table-wrap">
                    <table class="kb-table kb-cat-table">
                        <thead>
                            <tr>
                                <th>שם</th>
                                <th>הורה</th>
                                <th>מיקום</th>
                                <th>פעולות</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach($cats as $cat): ?>
                            <tr data-cat-id="<?php echo intval($cat->id); ?>">
                                <td><input type="text" class="kb-input kb-cat-name" value="<?php echo esc_attr($cat->category_name); ?>" aria-label="שם קטגוריה"></td>
                                <td>
                                    <select class="kb-input kb-cat-parent" aria-label="קטגוריית אם">
                                        <?php echo $options; ?>
                                    </select>
                                </td>
                                <td><input type="number" class="kb-input kb-cat-order" value="<?php echo intval($cat->sort_order); ?>" aria-label="סדר"></td>
                                <td class="kb-cat-actions">
                                    <button type="button" class="kb-btn kb-btn-secondary kb-cat-save">💾 שמור</button>
                                    <button type="button" class="kb-btn kb-btn-danger kb-cat-delete">🗑️ מחק</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function(){
            const parentOptions = `<?php echo str_replace(['`', "\n"], ['\\`',''], $options); ?>`;
            document.querySelectorAll('.kb-cat-parent').forEach(function(sel){
                const row = sel.closest('tr');
                const currentParent = <?php echo wp_json_encode(array_column($cats, 'parent_id', 'id')); ?>[row.dataset.catId] || 0;
                sel.value = currentParent;
            });

            function reloadPage(){ location.reload(); }

            document.getElementById('kb-cat-add-btn').addEventListener('click', function(){
                const name = document.getElementById('kb-cat-new-name').value.trim();
                const parent = document.getElementById('kb-cat-new-parent').value;
                if(!name){ alert('יש להזין שם'); return; }
                const fd = new FormData();
                fd.append('action','kb_add_category');
                fd.append('nonce', kbAjax.nonce);
                fd.append('cat_name', name);
                fd.append('parent_id', parent);
                fetch(kbAjax.ajaxurl, { method:'POST', body: fd })
                    .then(res=>res.json())
                    .then(json=>{ if(json.success){ reloadPage(); } else { alert('שגיאה בהוספה'); } });
            });

            document.querySelectorAll('.kb-cat-save').forEach(function(btn){
                btn.addEventListener('click', function(){
                    const row = this.closest('tr');
                    const id = row.dataset.catId;
                    const name = row.querySelector('.kb-cat-name').value.trim();
                    const parent = row.querySelector('.kb-cat-parent').value;
                    const order = row.querySelector('.kb-cat-order').value || 0;
                    if(!name){ alert('יש להזין שם'); return; }
                    const fd = new FormData();
                    fd.append('action','kb_update_category');
                    fd.append('nonce', kbAjax.nonce);
                    fd.append('cat_id', id);
                    fd.append('cat_name', name);
                    fd.append('parent_id', parent);
                    fd.append('sort_order', order);
                    fetch(kbAjax.ajaxurl, { method:'POST', body: fd })
                        .then(res=>res.json())
                        .then(json=>{
                            if(json.success){ reloadPage(); }
                            else { alert(json.data && json.data.message ? json.data.message : 'שמירה נכשלה'); }
                        });
                });
            });

            document.querySelectorAll('.kb-cat-delete').forEach(function(btn){
                btn.addEventListener('click', function(){
                    if(!confirm('למחוק קטגוריה זו?')) return;
                    const row = this.closest('tr');
                    const id = row.dataset.catId;
                    const fd = new FormData();
                    fd.append('action','kb_delete_category');
                    fd.append('nonce', kbAjax.nonce);
                    fd.append('cat_id', id);
                    fetch(kbAjax.ajaxurl, { method:'POST', body: fd })
                        .then(res=>res.json())
                        .then(json=>{ if(json.success){ reloadPage(); } else { alert('מחיקה נכשלה'); } });
                });
            });
        });
        </script>
        <?php
        return ob_get_clean();
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

    private function render_navigation_bar($active = '') {
        $home_url = 'https://kb.macomp.co.il/?page_id=10852';
        $table_url = 'https://kb.macomp.co.il/?page_id=14307';
        $trash_url = 'https://kb.macomp.co.il/?page_id=14309';
        $categories_url = 'https://kb.macomp.co.il/?page_id=11102';
        $bulk_url = 'https://kb.macomp.co.il/?page_id=14313';
        $archive_page = get_page_by_path('archive-bin');
        $archive_url = $archive_page ? get_permalink($archive_page->ID) : 'https://kb.macomp.co.il/?page_id=14311';
        $settings_url = 'https://kb.macomp.co.il/?page_id=14316';

        $links = [
            'home' => ['label' => 'ראשי', 'url' => $home_url],
            'table' => ['label' => 'טבלה', 'url' => $table_url],
            'bulk' => ['label' => 'עריכה קבוצתית', 'url' => $bulk_url],
            'archive' => ['label' => 'ארכיון', 'url' => $archive_url],
            'trash' => ['label' => 'סל מחזור', 'url' => $trash_url],
            'categories' => ['label' => 'קטגוריות', 'url' => $categories_url],
            'settings' => ['label' => 'הגדרות', 'url' => $settings_url],
        ];

        ob_start();
        ?>
        <div class="kb-nav-bar">
            <?php foreach($links as $key=>$link): ?>
                <a class="kb-nav-btn <?php echo $active === $key ? 'is-active' : ''; ?>" href="<?php echo esc_url($link['url']); ?>"><?php echo esc_html($link['label']); ?></a>
            <?php endforeach; ?>
        </div>
        <style>
        .kb-nav-bar { display:flex; justify-content:flex-start; gap:10px; flex-wrap:wrap; margin:0 0 15px 0; padding:0 5px; box-sizing:border-box; }
        .kb-nav-btn { display:inline-block; padding:7px 13px; border-radius:999px; background:#f7f9fc; color:#111827; text-decoration:none; font-weight:600; border:1.3px solid #d5dbe5; box-shadow:0 4px 10px rgba(15,23,42,0.08); transition:all .15s; }
        .kb-nav-btn:hover { background:#eef2f7; color:#111827; box-shadow:0 8px 16px rgba(15,23,42,0.14); transform:translateY(-1px); }
        .kb-nav-btn.is-active { background:#2f6df6; color:#fff; border-color:#2f6df6; box-shadow:0 10px 20px rgba(47,109,246,0.2); }
        </style>
        <?php
        return ob_get_clean();
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

    private function split_category_parts($category) {
        $category = trim($category);
        $parts = preg_split('/--\s*/', $category);
        $main = isset($parts[0]) ? trim($parts[0]) : '';
        $sub = isset($parts[1]) ? trim($parts[1]) : '';
        return [$main, $sub];
    }

    private function get_category_hierarchy() {
        global $wpdb;
        $cats = $wpdb->get_results("SELECT id, category_name, parent_id FROM {$wpdb->prefix}kb_categories ORDER BY parent_id, sort_order, category_name");
        $main = [];
        $subs = [];
        $by_name = [];
        foreach($cats as $cat) {
            $by_name[$cat->category_name] = $cat;
            if(intval($cat->parent_id) === 0) {
                $main[] = $cat;
            } else {
                $subs[$cat->parent_id][] = $cat;
            }
        }
        return [$main, $subs, $by_name];
    }

    private function build_category_value($main_id, $sub_id) {
        list($main, $subs, $by_name) = $this->get_category_hierarchy();
        $main_name = '';
        $sub_name = '';

        foreach($main as $cat) {
            if(intval($cat->id) === intval($main_id)) {
                $main_name = $cat->category_name;
                break;
            }
        }

        if($main_name && $sub_id) {
            $sub_candidates = isset($subs[$main_id]) ? $subs[$main_id] : [];
            foreach($sub_candidates as $cat) {
                if(intval($cat->id) === intval($sub_id)) {
                    $sub_name = $cat->category_name;
                    break;
                }
            }
        }

        $category_value = trim($main_name . ($sub_name ? ' -- ' . $sub_name : ''));
        return $category_value;
    }

    private function ensure_category_exists($name, $parent_id = 0) {
        global $wpdb;
        $name = trim($name);
        if($name === '') return 0;

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}kb_categories WHERE category_name = %s AND parent_id = %d",
            $name,
            intval($parent_id)
        ));
        if($existing) return intval($existing);

        $wpdb->insert(
            $wpdb->prefix . 'kb_categories',
            [
                'category_name' => $name,
                'parent_id' => intval($parent_id),
                'sort_order' => 0,
            ],
            ['%s','%d','%d']
        );

        return intval($wpdb->insert_id);
    }

    private function get_article_rating($article) {
        if(!isset($article->user_rating) || $article->user_rating === '' || is_null($article->user_rating)) return null;
        $rating = intval($article->user_rating);
        if($rating < 1 || $rating > 100) return null;
        return $rating;
    }

    private function sanitize_vulnerability_level($value) {
        $map = [
            'low' => 1,
            'medium' => 2,
            'high' => 3
        ];
        if(is_null($value) || $value === '') return null;
        $value = is_numeric($value) ? intval($value) : strtolower(trim($value));
        if(isset($map[$value])) return $map[$value];
        $flipped = array_flip($map);
        return isset($flipped[$value]) ? $value : null;
    }

    private function normalize_vulnerability_from_text($value) {
        if(is_null($value) || $value === '') return null;
        $value = trim(strtolower($value));
        $map = [
            'נמוכה' => 1,
            'בינונית' => 2,
            'גבוהה' => 3,
        ];
        if(isset($map[$value])) return $map[$value];
        return $this->sanitize_vulnerability_level($value);
    }

    private function normalize_status_from_text($value) {
        if(is_null($value) || $value === '') return 0;
        $value = trim(mb_strtolower($value));
        $map = [
            'לא נבדק' => 0,
            'בתהליך' => 1,
            'תקין' => 2,
            'not checked' => 0,
            'in progress' => 1,
            'ok' => 2,
            'ready' => 2,
        ];
        return isset($map[$value]) ? $map[$value] : 0;
    }

    private function collect_paragraph_text($node) {
        $texts = [];
        if(!$node || !$node->hasChildNodes()) return '';
        foreach($node->getElementsByTagName('t') as $t) {
            $texts[] = $t->nodeValue;
        }
        return trim(implode('', $texts));
    }

    private function collect_paragraph_images($node) {
        $ids = [];
        if(!$node) return $ids;
        $drawing_nodes = $node->getElementsByTagNameNS('http://schemas.openxmlformats.org/drawingml/2006/main', 'blip');
        foreach($drawing_nodes as $blip) {
            $rid = $blip->hasAttribute('r:embed') ? $blip->getAttribute('r:embed') : $blip->getAttribute('embed');
            if($rid) { $ids[] = $rid; }
        }
        return $ids;
    }

    private function render_docx_paragraph_html($node, &$used_images) {
        $text = $this->collect_paragraph_text($node);
        $html = esc_html($text);
        $images = $this->collect_paragraph_images($node);
        foreach($images as $rid) {
            $used_images[$rid] = true;
            $html .= '{{IMG-'.$rid.'}}';
        }
        if($html === '') return '';
        return '<p>'.$html.'</p>';
    }

    private function replace_docx_placeholders($content, $image_urls) {
        return preg_replace_callback('/\{\{IMG-([^}]+)\}\}/', function($m) use ($image_urls) {
            $rid = $m[1];
            $url = isset($image_urls[$rid]) ? $image_urls[$rid] : '';
            if(!$url) return '';
            return '<img src="'.esc_url($url).'" alt="" />';
        }, $content);
    }

    private function flatten_docx_html_text($html) {
        if($html === '') return '';
        $text = preg_replace('/<\/(p|div)>/i', "\n", $html);
        $text = preg_replace('/<br\s*\/>/i', "\n", $text);
        $text = preg_replace('/<br\s*>/i', "\n", $text);
        $text = wp_strip_all_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/\n{3,}/", "\n\n", $text);
        return trim($text);
    }

    private function upload_docx_images($zip, $rels, $used_images) {
        $uploaded = [];
        if(!$zip instanceof ZipArchive) return $uploaded;
        $uploads = wp_upload_dir();
        if(!empty($uploads['error'])) return $uploaded;
        wp_mkdir_p($uploads['path']);

        foreach(array_keys($used_images) as $rid) {
            if(!isset($rels[$rid])) continue;
            $target = $rels[$rid];
            $path = strpos($target, 'word/') === 0 ? $target : 'word/' . ltrim($target, '/');
            $file_data = $zip->getFromName($path);
            if(!$file_data) continue;

            $ext = pathinfo($path, PATHINFO_EXTENSION);
            $allowed_ext = ['png','jpg','jpeg','gif','webp'];
            if(!in_array(strtolower($ext), $allowed_ext, true)) {
                $ext = 'png';
            }

            $filename = 'kb-import-'.time().'-'.wp_generate_password(5, false, false).'.'.$ext;
            $new_path = trailingslashit($uploads['path']).$filename;
            $url = trailingslashit($uploads['url']).$filename;
            if(file_put_contents($new_path, $file_data) !== false) {
                $uploaded[$rid] = $url;
            }
        }
        return $uploaded;
    }

    private function parse_docx_articles($file_path) {
        $zip = new ZipArchive();
        $opened = $zip->open($file_path);
        if($opened !== true) {
            return ['articles'=>[], 'errors'=>['קובץ וורד לא תקין']];
        }

        $doc_xml = $zip->getFromName('word/document.xml');
        if(!$doc_xml) {
            $zip->close();
            return ['articles'=>[], 'errors'=>['לא נמצא תוכן במסמך']];
        }

        $rels_xml = $zip->getFromName('word/_rels/document.xml.rels');
        $rels = [];
        if($rels_xml) {
            $rels_dom = new DOMDocument();
            $rels_dom->loadXML($rels_xml);
            foreach($rels_dom->getElementsByTagName('Relationship') as $rel) {
                $rid = $rel->getAttribute('Id');
                $target = $rel->getAttribute('Target');
                if($rid && $target) {
                    $rels[$rid] = $target;
                }
            }
        }

        $dom = new DOMDocument();
        $dom->loadXML($doc_xml);
        $body = $dom->getElementsByTagName('body')->item(0);
        if(!$body) {
            $zip->close();
            return ['articles'=>[], 'errors'=>['המסמך ריק']];
        }

        $articles = [];
        $current = null;
        $current_section = '';
        $used_images = [];

        foreach(iterator_to_array($body->childNodes) as $node) {
            if($node->nodeName !== 'w:p') continue;
            $text = $this->collect_paragraph_text($node);
            $html = $this->render_docx_paragraph_html($node, $used_images);

            if(strpos($text, '[[ARTICLE]]') !== false) {
                if($current) { $articles[] = $current; }
                $current = [
                    'subject' => '',
                    'main_category' => '',
                    'sub_category' => '',
                    'review_status' => 0,
                    'user_rating' => null,
                    'vulnerability_level' => null,
                    'links' => '',
                    'sections' => [
                        'short_desc' => [],
                        'technical_desc' => [],
                        'technical_solution' => [],
                        'post_check' => [],
                        'solution_script' => [],
                        'check_script' => [],
                    ],
                ];
                $current_section = '';
                continue;
            }

            if(!$current) continue;

            if(preg_match('/^Subject\s*:\s*(.+)$/i', $text, $m)) {
                $current['subject'] = $m[1];
                continue;
            }
            if(preg_match('/^Category\s*:\s*(.+)$/i', $text, $m)) {
                $current['main_category'] = $m[1];
                continue;
            }
            if(preg_match('/^Subcategory\s*:\s*(.+)$/i', $text, $m)) {
                $current['sub_category'] = $m[1];
                continue;
            }
            if(preg_match('/^Review Status\s*:\s*(.+)$/i', $text, $m)) {
                $current['review_status'] = $this->normalize_status_from_text($m[1]);
                continue;
            }
            if(preg_match('/^Rating\s*:\s*(.+)$/i', $text, $m)) {
                $rating_val = trim($m[1]);
                $rating_int = ($rating_val === '') ? null : intval($rating_val);
                $current['user_rating'] = ($rating_int >=1 && $rating_int <=100) ? $rating_int : null;
                continue;
            }
            if(preg_match('/^Vulnerability\s*:\s*(.+)$/i', $text, $m)) {
                $current['vulnerability_level'] = $this->normalize_vulnerability_from_text($m[1]);
                continue;
            }
            if(preg_match('/^Links\s*:\s*(.+)$/i', $text, $m)) {
                $current['links'] = $m[1];
                continue;
            }

            $section_map = [
                'Short Description:' => 'short_desc',
                'Technical Description:' => 'technical_desc',
                'Technical Solution:' => 'technical_solution',
                'Post Check:' => 'post_check',
                'Solution Script:' => 'solution_script',
                'Check Script:' => 'check_script',
            ];
            $matched_section = '';
            foreach($section_map as $label=>$key) {
                if(stripos($text, $label) === 0) { $matched_section = $key; break; }
            }
            if($matched_section) { $current_section = $matched_section; continue; }

            if($current_section && $html) {
                $current['sections'][$current_section][] = $html;
            }
        }

        if($current) { $articles[] = $current; }

        return [
            'articles' => $articles,
            'errors' => [],
            'zip' => $zip,
            'rels' => $rels,
            'images' => $used_images,
        ];
    }

    private function generate_word_template_docx() {
        $content_types = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
    <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
</Types>
XML;

        $rels_root = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;

        $doc_rels = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>
XML;

        $styles = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
    <w:style w:type="paragraph" w:default="1" w:styleId="Normal">
        <w:name w:val="Normal"/>
        <w:rPr>
            <w:rFonts w:ascii="Arial" w:hAnsi="Arial" w:eastAsia="Arial" w:cs="Arial"/>
            <w:sz w:val="24"/>
            <w:szCs w:val="24"/>
        </w:rPr>
    </w:style>
</w:styles>
XML;

        $lines = [
            '[[ARTICLE]]',
            'Subject:',
            '',
            'Category:',
            '',
            'Subcategory:',
            '',
            'Review Status:',
            '',
            'Vulnerability:',
            '',
            'Rating:',
            '',
            'Links:',
            '',
            'Technical Description:',
            'Technical Solution:',
            'Post Check:',
            'Solution Script:',
            'Check Script:',
        ];

        $paragraphs = '';
        foreach($lines as $line) {
            $paragraphs .= '<w:p><w:r><w:t>'.htmlspecialchars($line, ENT_XML1).'</w:t></w:r></w:p>';
            if(strpos($line, ':') !== false && substr($line, -1) === ':') {
                $paragraphs .= '<w:p><w:r><w:t xml:space="preserve"> </w:t></w:r></w:p>';
            }
        }

        $document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:wpc="http://schemas.microsoft.com/office/word/2010/wordprocessingCanvas" xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006" mc:Ignorable="w14 wp14" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:m="http://schemas.openxmlformats.org/officeDocument/2006/math" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:wp14="http://schemas.microsoft.com/office/word/2010/wordprocessingDrawing" xmlns:w10="urn:schemas-microsoft-com:office:word" xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" xmlns:wps="http://schemas.microsoft.com/office/word/2010/wordprocessingShape" xmlns:wpg="http://schemas.microsoft.com/office/word/2010/wordprocessingGroup" xmlns:wpi="http://schemas.microsoft.com/office/word/2010/wordprocessingInk" xmlns:wne="http://schemas.microsoft.com/office/word/2006/wordml" xmlns:wps1="http://schemas.microsoft.com/office/word/2010/wordprocessingshape">'
            .'<w:body>'.$paragraphs.'<w:sectPr><w:pgSz w:w="12240" w:h="15840"/><w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440" w:header="708" w:footer="708" w:gutter="0"/></w:sectPr></w:body></w:document>';

        $tmp = wp_tempnam('kb-template');
        $zip = new ZipArchive();
        if($zip->open($tmp, ZipArchive::OVERWRITE) !== true) return false;
        $zip->addFromString('[Content_Types].xml', $content_types);
        $zip->addFromString('_rels/.rels', $rels_root);
        $zip->addFromString('word/_rels/document.xml.rels', $doc_rels);
        $zip->addFromString('word/styles.xml', $styles);
        $zip->addFromString('word/document.xml', $document);
        $zip->close();
        return $tmp;
    }

    private function import_articles_from_docx($file_path) {
        global $wpdb;
        $parsed = $this->parse_docx_articles($file_path);
        if(!empty($parsed['errors'])) {
            return ['created'=>0, 'errors'=>$parsed['errors']];
        }

        $articles = $parsed['articles'];
        $zip = $parsed['zip'];
        $image_urls = $this->upload_docx_images($zip, $parsed['rels'], $parsed['images']);
        if($zip instanceof ZipArchive) { $zip->close(); }

        if(empty($articles)) {
            return ['created'=>0, 'errors'=>['לא נמצאו מאמרים בקובץ']];
        }

        $created = 0;
        $errors = [];
        $row = 0;

        foreach($articles as $article) {
            $row++;
            $subject = sanitize_text_field($article['subject']);
            if(!$subject) { $errors[] = "מאמר {$row}: חסר נושא"; continue; }

            $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}kb_articles WHERE subject = %s", $subject));
            if($existing) { $errors[] = "מאמר {$row}: נושא כבר קיים"; continue; }

            $main_id = $this->ensure_category_exists($article['main_category'], 0);
            $sub_id = $article['sub_category'] ? $this->ensure_category_exists($article['sub_category'], $main_id) : 0;
            $category_value = $this->build_category_value($main_id, $sub_id);
            if(!$category_value) { $errors[] = "מאמר {$row}: קטגוריה לא תקינה"; continue; }

            $content_fields = ['short_desc','technical_desc','technical_solution','post_check','solution_script','check_script'];
            $prepared = [];
            foreach($content_fields as $field) {
                $raw = isset($article['sections'][$field]) ? implode('', $article['sections'][$field]) : '';
                $raw = $this->replace_docx_placeholders($raw, $image_urls);
                if(in_array($field, ['solution_script','check_script','post_check'], true)) {
                    $prepared[$field] = $this->flatten_docx_html_text($raw);
                } else {
                    $prepared[$field] = wp_kses_post($raw);
                }
            }

            $prepared['technical_desc'] = $this->merge_short_desc_text(
                isset($prepared['technical_desc']) ? $prepared['technical_desc'] : '',
                isset($prepared['short_desc']) ? $prepared['short_desc'] : ''
            );
            unset($prepared['short_desc']);

            $rating = isset($article['user_rating']) ? intval($article['user_rating']) : null;
            if($rating < 1 || $rating > 100) { $rating = null; }

            $data = array_merge($prepared, [
                'subject' => $subject,
                'category' => $category_value,
                'links' => wp_kses_post($article['links']),
                'review_status' => intval($article['review_status']),
                'user_rating' => $rating,
                'vulnerability_level' => $this->normalize_vulnerability_from_text($article['vulnerability_level']),
                'is_deleted' => 0,
                'is_archived' => 0,
            ]);

            $wpdb->insert($wpdb->prefix.'kb_articles', $data);
            $created++;
        }

        return ['created'=>$created, 'errors'=>$errors];
    }

    private function get_vulnerability_label($article) {
        if(!isset($article->vulnerability_level) || $article->vulnerability_level === '' || is_null($article->vulnerability_level)) return '';
        $level = intval($article->vulnerability_level);
        if($level === 1) return 'נמוכה';
        if($level === 2) return 'בינונית';
        if($level === 3) return 'גבוהה';
        return '';
    }

    private function merge_short_desc_text($technical, $short) {
        $technical = is_string($technical) ? trim($technical) : '';
        $short = is_string($short) ? trim($short) : '';
        if($short === '') return $technical;
        if($technical === '') return $short;
        return trim($technical . "\n\n" . $short);
    }

    private function merge_short_desc_into_technical($article) {
        if(!$article || !isset($article->short_desc) || trim($article->short_desc) === '') return $article;
        $article->technical_desc = $this->merge_short_desc_text(isset($article->technical_desc) ? $article->technical_desc : '', $article->short_desc);
        $article->short_desc = '';
        return $article;
    }

    private function render_rating_badge($article) {
        $rating = $this->get_article_rating($article);
        if(is_null($rating)) return '';
        return '<span class="kb-rating-badge">'.esc_html($rating).'</span>';
    }

    private function render_article_meta($article) {
        list($main_cat, $sub_cat) = $this->split_category_parts($article->category);
        ob_start();
        ?>
        <div class="kb-meta kb-meta-inline">
            <?php if($main_cat): ?><span class="kb-meta-chip">📁 <?php echo esc_html($main_cat); ?></span><?php endif; ?>
            <?php if($sub_cat): ?><span class="kb-meta-chip">📂 <?php echo esc_html($sub_cat); ?></span><?php endif; ?>
            <span class="kb-meta-chip">📅 <?php echo esc_html($this->format_hebrew_date($article->created_at)); ?></span>
            <span class="kb-meta-chip kb-meta-status-chip"><?php echo $this->render_status_badge($article->review_status); ?></span>
            <span class="kb-meta-chip">⚙️ <?php echo esc_html($this->get_execution_mode($article)); ?></span>
            <?php $vuln_label = $this->get_vulnerability_label($article); if($vuln_label): ?>
                <span class="kb-meta-chip">🛡️ <?php echo esc_html($vuln_label); ?></span>
            <?php endif; ?>
            <?php $rating_badge = $this->render_rating_badge($article); if($rating_badge): ?>
                <span class="kb-meta-chip"><?php echo $rating_badge; ?></span>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_article_body($article, $include_meta = true) {
        $article = $this->merge_short_desc_into_technical($article);
        ob_start();
        ?>
        <div class="kb-article-body-block">
            <?php if($include_meta) echo $this->render_article_meta($article); ?>
            <?php if($article->technical_desc): ?><div class="kb-section"><h3>תיאור טכני</h3><?php echo $article->technical_desc; ?></div><?php endif; ?>
            <?php if($article->technical_solution): ?><div class="kb-section"><h3>פתרון טכני</h3><?php echo $article->technical_solution; ?></div><?php endif; ?>
            <?php if($article->solution_script): ?>
            <div class="kb-section kb-script-section">
                <h3>סקריפט פתרון</h3>
                <pre dir="ltr"><?php echo esc_html($article->solution_script); ?></pre>
            </div>
            <?php endif; ?>
            <?php if($article->solution_files):
                $files = json_decode($article->solution_files, true);
                if($files): ?>
            <div class="kb-section"><h3>קבצים מצורפים</h3>
                <?php foreach($files as $file): ?>
                    <a href="<?php echo esc_url($file); ?>" target="_blank" class="kb-download-btn">📥 <?php echo basename($file); ?></a><br>
                <?php endforeach; ?>
            </div>
            <?php endif; endif; ?>
            <?php if($article->post_check): ?><div class="kb-section"><h3>בדיקת פתרון</h3><?php echo $article->post_check; ?></div><?php endif; ?>
            <?php if($article->check_script): ?>
            <div class="kb-section kb-script-section">
                <h3>סקריפט בדיקה</h3>
                <pre dir="ltr"><?php echo esc_html($article->check_script); ?></pre>
            </div>
            <?php endif; ?>
            <?php if($article->check_files):
                $files = json_decode($article->check_files, true);
                if($files): ?>
            <div class="kb-section"><h3>קבצי בדיקה</h3>
                <?php foreach($files as $file): ?>
                    <a href="<?php echo esc_url($file); ?>" target="_blank" class="kb-download-btn">📥 <?php echo basename($file); ?></a><br>
                <?php endforeach; ?>
            </div>
            <?php endif; endif; ?>
            <?php if($article->links): ?><div class="kb-section"><h3>קישורים רלוונטיים</h3><?php echo $article->links; ?></div><?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function get_execution_mode($article){
        $has_script = false;
        if(isset($article->solution_script) && trim($article->solution_script) !== '') $has_script = true;
        if(isset($article->solution_files) && trim($article->solution_files) !== '') $has_script = true;
        return $has_script ? 'אוטומטי' : 'ידני';
    }

    private function handle_public_article_action($redirect_url = '') {
        if(!isset($_GET['kb_pub_action'])) return;
        if(!current_user_can('manage_options')) return;

        $action = sanitize_key($_GET['kb_pub_action']);
        $article_id = isset($_GET['article_id']) ? intval($_GET['article_id']) : 0;
        $nonce = isset($_GET['_wpnonce']) ? $_GET['_wpnonce'] : '';

        $nonce_key = ($action === 'empty') ? 'kb_pub_action_empty' : 'kb_pub_action_'.$article_id;
        if(!wp_verify_nonce($nonce, $nonce_key)) return;

        global $wpdb; $table = $wpdb->prefix . 'kb_articles';

        if($action === 'trash' && $article_id){
            $wpdb->update($table, ['is_deleted'=>1, 'is_archived'=>0], ['id'=>$article_id], ['%d','%d'], ['%d']);
        }
        elseif($action === 'archive' && $article_id){
            $wpdb->update($table, ['is_archived'=>1, 'is_deleted'=>0], ['id'=>$article_id], ['%d','%d'], ['%d']);
        }
        elseif($action === 'unarchive' && $article_id){
            $wpdb->update($table, ['is_archived'=>0], ['id'=>$article_id], ['%d'], ['%d']);
        }
        elseif($action === 'restore' && $article_id){
            $wpdb->update($table, ['is_deleted'=>0, 'is_archived'=>0], ['id'=>$article_id], ['%d','%d'], ['%d']);
        }
        elseif($action === 'delete' && $article_id){
            $wpdb->delete($table, ['id'=>$article_id]);
        }
        elseif($action === 'empty'){
            $wpdb->query("DELETE FROM $table WHERE is_deleted=1");
        }

        $target = $redirect_url ? $redirect_url : home_url($_SERVER['REQUEST_URI']);
        $target = remove_query_arg(['kb_pub_action','article_id','_wpnonce'], $target);

        if (headers_sent()) {
            echo '<script>window.location.href=' . json_encode($target) . ';</script>';
            exit;
        }

        wp_safe_redirect($target);
        exit;
    }

    private function process_table_bulk_actions($redirect_url = '') {
        if($_SERVER['REQUEST_METHOD'] !== 'POST') return;
        if(!isset($_POST['kb_bulk_action']) || !current_user_can('manage_options')) return;
        check_admin_referer('kb_bulk_action');

        $action = sanitize_key($_POST['kb_bulk_action']);
        $ids = isset($_POST['kb_selected']) ? array_map('intval', (array)$_POST['kb_selected']) : [];
        $ids = array_filter($ids);
        if(empty($ids)) return;

        global $wpdb; $table = $wpdb->prefix . 'kb_articles';
        $id_list = implode(',', $ids);

        if($action === 'trash_bulk') {
            $wpdb->query("UPDATE $table SET is_deleted=1, is_archived=0 WHERE id IN ($id_list)");
        } elseif($action === 'archive_bulk') {
            $wpdb->query("UPDATE $table SET is_archived=1, is_deleted=0 WHERE id IN ($id_list)");
        } elseif($action === 'restore_bulk') {
            $wpdb->query("UPDATE $table SET is_deleted=0, is_archived=0 WHERE id IN ($id_list)");
        } elseif($action === 'delete_bulk') {
            $wpdb->query("DELETE FROM $table WHERE id IN ($id_list)");
        } elseif($action === 'unarchive_bulk') {
            $wpdb->query("UPDATE $table SET is_archived=0 WHERE id IN ($id_list)");
        }

        if($redirect_url) {
            if (headers_sent()) {
                echo '<script>window.location.href=' . json_encode($redirect_url) . ';</script>';
                exit;
            }

            wp_safe_redirect($redirect_url);
            exit;
        }

        wp_send_json_success($result);
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
        $sql = "SELECT * FROM $table WHERE ".($view_trash ? "is_deleted=1" : "(is_deleted IS NULL OR is_deleted=0) AND (is_archived IS NULL OR is_archived=0)");
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $sql .= $wpdb->prepare(
                " AND (category LIKE %s OR subject LIKE %s OR
                technical_desc LIKE %s OR technical_solution LIKE %s)",
                $like,$like,$like,$like
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
        return $this->manage_categories_shortcode();
    }

    public function print_tree($cats, $parent, $table, $home_url) {
        global $wpdb;
        foreach ($cats as $c) {
            if($c->parent_id == $parent) {
                echo "<li style='margin:12px 0;'><span style='font-weight:bold;color:#34495e;font-size:1.13em'>" . esc_html($c->category_name) . "</span>";
                $articles = $wpdb->get_results($wpdb->prepare("SELECT id, subject FROM $table WHERE (is_deleted IS NULL OR is_deleted=0) AND (is_archived IS NULL OR is_archived=0) AND category LIKE %s ORDER BY subject", '%'.$wpdb->esc_like($c->category_name).'%'));
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
        $article = $this->merge_short_desc_into_technical($article);
        list($main_cats, $sub_cats_map, $cats_by_name) = $this->get_category_hierarchy();
        $status_labels = $this->get_status_labels();
        $current_status = $article ? intval($article->review_status) : 0;
        $current_rating = $article ? intval($article->user_rating) : 0;
        $current_vuln = $article ? intval($article->vulnerability_level) : 0;

        list($selected_main, $selected_sub) = $this->split_category_parts($article ? $article->category : '');
        $selected_main_id = 0; $selected_sub_id = 0;
        foreach($main_cats as $cat) { if($cat->category_name === $selected_main) { $selected_main_id = intval($cat->id); break; } }
        if($selected_main_id && isset($sub_cats_map[$selected_main_id])) {
            foreach($sub_cats_map[$selected_main_id] as $cat) {
                if($cat->category_name === $selected_sub) { $selected_sub_id = intval($cat->id); break; }
            }
        }

        echo '<div class="wrap"><h1>' . ($article ? 'עריכת מאמר' : 'הוסף מאמר חדש') . '</h1>';
        echo '<form id="kb-article-form" class="kb-form" enctype="multipart/form-data">';
        wp_nonce_field('save_article_nonce','article_nonce');
        if ($article) echo '<input type="hidden" name="article_id" value="'.intval($article->id).'">';
        
        echo '<fieldset class="kb-fieldset"><legend>נתונים כלליים</legend>';
        echo '<div class="kb-row"><label class="kb-label">נושא: <span style="color:red;">*</span></label>
            <input type="text" name="subject" class="kb-input" value="'.($article ? esc_attr($article->subject) : '').'" required></div>';
        echo '<div class="kb-row"><label class="kb-label">קטגוריה:</label>
            <select name="main_category_id" class="kb-input" required>';
        echo '<option value="">בחר קטגוריה</option>';
        foreach($main_cats as $cat) {
            echo '<option value="'.intval($cat->id).'" '.selected($selected_main_id, $cat->id, false).'>'.esc_html($cat->category_name).'</option>';
        }
        echo '</select></div>';
        echo '<div class="kb-row"><label class="kb-label">תת קטגוריה:</label>
            <select name="sub_category_id" class="kb-input">';
        echo '<option value="">בחר תת קטגוריה</option>';
        if($selected_main_id && isset($sub_cats_map[$selected_main_id])) {
            foreach($sub_cats_map[$selected_main_id] as $cat) {
                echo '<option value="'.intval($cat->id).'" '.selected($selected_sub_id, $cat->id, false).'>'.esc_html($cat->category_name).'</option>';
            }
        }
        echo '</select></div>';
        echo '<div class="kb-row"><label class="kb-label">דירוג:</label>';
        echo '<input type="number" name="user_rating" class="kb-input" min="1" max="100" value="'.($current_rating ? intval($current_rating) : '').'" placeholder="1-100">';
        echo '</div>';
        echo '<div class="kb-row"><label class="kb-label">פגיעות: <span class="kb-help-icon" data-tooltip="פגיעות של הארגון לשינוי">?</span></label>';
        echo '<select name="vulnerability_level" class="kb-input">';
        echo '<option value="">בחר דרגת פגיעות</option>';
        echo '<option value="low" '.($article && intval($article->vulnerability_level)===1 ? 'selected' : '').'>נמוכה</option>';
        echo '<option value="medium" '.($article && intval($article->vulnerability_level)===2 ? 'selected' : '').'>בינונית</option>';
        echo '<option value="high" '.($article && intval($article->vulnerability_level)===3 ? 'selected' : '').'>גבוהה</option>';
        echo '</select>';
        echo '</div>';
        echo '<div class="kb-row"><label class="kb-label">סטטוס בדיקה:</label><select name="review_status" class="kb-input">';
        foreach($status_labels as $k=>$lbl) {
            echo '<option value="'.intval($k).'" '.selected($current_status, $k, false).'>'.esc_html($lbl).'</option>';
        }
        echo '</select></div>';
        echo '</fieldset>';

        echo '<fieldset class="kb-fieldset"><legend>פרטים</legend>';
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
        ob_start();
        ?>
        <script>
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
                            data.append("action", "kb_upload_image");
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

            const catData = {
                main: <?php echo wp_json_encode($main_cats); ?>,
                subs: <?php echo wp_json_encode($sub_cats_map); ?>
            };
            const mainSelect = document.querySelector('[name="main_category_id"]');
            const subSelect = document.querySelector('[name="sub_category_id"]');

            function populateSubs(parentId, selectedValue) {
                if(!subSelect) return;
                subSelect.innerHTML = '<option value="">בחר תת קטגוריה</option>';
                if(parentId && catData.subs[parentId]) {
                    catData.subs[parentId].forEach(function(cat){
                        const opt = document.createElement('option');
                        opt.value = cat.id;
                        opt.textContent = cat.category_name;
                        if(String(selectedValue) === String(cat.id)) opt.selected = true;
                        subSelect.appendChild(opt);
                    });
                }
            }

            if(mainSelect) {
                populateSubs(mainSelect.value, '<?php echo $selected_sub_id; ?>');
                mainSelect.addEventListener('change', function(){
                    populateSubs(this.value, '');
                });
            }

            function saveArticle(openNew) {
                // ⭐ בדיקת שדות חובה
                let subject = document.querySelector("[name=subject]").value.trim();
                let techSolution = document.querySelector("#technical_solution").editorInstance ?
                    document.querySelector("#technical_solution").editorInstance.getData().trim() : "";
                let mainCat = document.querySelector('[name="main_category_id"]') ? document.querySelector('[name="main_category_id"]').value : '';

                if(!subject) {
                    alert("❌ שדה נושא הוא שדה חובה!");
                    return;
                }

                if(!mainCat) {
                    alert("❌ יש לבחור קטגוריה למאמר!");
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
                                setTimeout(function(){ location.href = "<?php echo admin_url('admin.php?page=kb-editor-new'); ?>"; }, 800);
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
        </script></div>
        <?php
        echo ob_get_clean();
    }

    public function save_article() {
        check_ajax_referer('save_article_nonce','article_nonce');
        global $wpdb;
        
        // ⭐ בדיקת שדות חובה
        if(empty($_POST['subject'])) {
            wp_send_json_error(['message' => 'שדה נושא הוא שדה חובה']);
        }
        
        $tech_solution = isset($_POST['technical_solution']) ? wp_kses_post(wp_unslash($_POST['technical_solution'])) : '';
        if(empty($tech_solution) || $tech_solution === '<p>&nbsp;</p>' || $tech_solution === '<p></p>') {
            wp_send_json_error(['message' => 'שדה פתרון טכני הוא שדה חובה']);
        }

        $status = isset($_POST['review_status']) ? intval($_POST['review_status']) : 0;
        if($status < 0 || $status > 2) { $status = 0; }

        $user_rating = isset($_POST['user_rating']) && $_POST['user_rating'] !== '' ? intval($_POST['user_rating']) : null;
        if(!is_null($user_rating) && ($user_rating < 1 || $user_rating > 100)) { $user_rating = null; }

        $vulnerability_level = isset($_POST['vulnerability_level']) ? $this->sanitize_vulnerability_level($_POST['vulnerability_level']) : null;

        // ⭐ בדיקת כפילויות - רק אם זה מאמר חדש
        $article_id = isset($_POST['article_id']) ? intval($_POST['article_id']) : 0;
        if(!$article_id) {
            $subject = isset($_POST['subject']) ? sanitize_text_field(wp_unslash($_POST['subject'])) : '';
            $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}kb_articles WHERE subject = %s", $subject));
            if($existing) {
                wp_send_json_error(['message' => 'קיים כבר מאמר עם אותו נושא! אנא בחר נושא אחר.']);
            }
        }

        $data = [];
        $data['subject'] = isset($_POST['subject']) ? sanitize_text_field(wp_unslash($_POST['subject'])) : '';

        $technical_desc = isset($_POST['technical_desc']) ? wp_kses_post(wp_unslash($_POST['technical_desc'])) : '';
        $short_from_post = isset($_POST['short_desc']) ? wp_kses_post(wp_unslash($_POST['short_desc'])) : '';
        $technical_desc = $this->merge_short_desc_text($technical_desc, $short_from_post);

        if($article_id) {
            $existing = $wpdb->get_row($wpdb->prepare("SELECT technical_desc, short_desc FROM {$wpdb->prefix}kb_articles WHERE id=%d", $article_id));
            if($existing && $existing->short_desc) {
                $technical_desc = $this->merge_short_desc_text($technical_desc !== '' ? $technical_desc : $existing->technical_desc, $existing->short_desc);
            }
        }

        if($technical_desc !== '') { $data['technical_desc'] = $technical_desc; }
        $data['short_desc'] = null;

        $fields = [
            'technical_solution','solution_script',
            'post_check','check_script','links'
        ];
        foreach ($fields as $f) {
            if (isset($_POST[$f]) && $_POST[$f] != '') {
                $data[$f] = wp_kses_post(wp_unslash($_POST[$f]));
            }
        }

        $main_cat_id = isset($_POST['main_category_id']) ? intval($_POST['main_category_id']) : 0;
        $sub_cat_id = isset($_POST['sub_category_id']) ? intval($_POST['sub_category_id']) : 0;
        $data['category'] = $this->build_category_value($main_cat_id, $sub_cat_id);
        if(empty($data['category'])) {
            wp_send_json_error(['message' => 'יש לבחור קטגוריה למאמר']);
        }

        $data['review_status'] = $status;
        $data['user_rating'] = $user_rating;
        $data['vulnerability_level'] = $vulnerability_level;
        
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

    public function save_bulk_articles() {
        check_ajax_referer('kb_bulk_save', 'nonce');
        global $wpdb;

        if(!$this->user_can_edit_article()) {
            wp_send_json_error(['message' => 'אין לך הרשאות לשמור מאמרים']);
        }

        $raw = isset($_POST['articles']) ? wp_unslash($_POST['articles']) : '';
        $articles = $raw ? json_decode($raw, true) : [];
        if(!is_array($articles)) {
            wp_send_json_error(['message' => 'לא התקבלו נתונים תקינים']);
        }

        $created = 0;
        $updated = 0;
        $errors = [];
        $status_labels = $this->get_status_labels();
        $row_num = 0;

        foreach($articles as $item) {
            $row_num++;
            $article_id = isset($item['article_id']) ? intval($item['article_id']) : 0;
            $subject = isset($item['subject']) ? sanitize_text_field($item['subject']) : '';
            $tech_solution = isset($item['technical_solution']) ? wp_kses_post($item['technical_solution']) : '';
            if(!$subject) {
                $errors[] = "שורה {$row_num}: חסר נושא";
                continue;
            }
            if(!$tech_solution || $tech_solution === '<p>&nbsp;</p>' || $tech_solution === '<p></p>') {
                $errors[] = "שורה {$row_num}: יש למלא פתרון טכני";
                continue;
            }

            $main_cat_id = isset($item['main_category_id']) ? intval($item['main_category_id']) : 0;
            $sub_cat_id = isset($item['sub_category_id']) ? intval($item['sub_category_id']) : 0;
            $category = $this->build_category_value($main_cat_id, $sub_cat_id);
            if(!$category) {
                $errors[] = "שורה {$row_num}: יש לבחור קטגוריה";
                continue;
            }

            $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}kb_articles WHERE subject = %s", $subject));
            if($existing && (!$article_id || intval($existing) !== $article_id)) {
                $errors[] = "שורה {$row_num}: נושא קיים כבר";
                continue;
            }

            $status = isset($item['review_status']) ? intval($item['review_status']) : 0;
            if($status < 0 || $status > 2) { $status = 0; }

            $user_rating = isset($item['user_rating']) && $item['user_rating'] !== '' ? intval($item['user_rating']) : null;
            if(!is_null($user_rating) && ($user_rating < 1 || $user_rating > 100)) { $user_rating = null; }

            $vulnerability_level = isset($item['vulnerability_level']) ? $this->sanitize_vulnerability_level($item['vulnerability_level']) : null;

            $data = [
                'subject' => $subject,
                'category' => $category,
                'technical_solution' => $tech_solution,
                'review_status' => $status,
                'user_rating' => $user_rating,
                'vulnerability_level' => $vulnerability_level,
            ];

            $tech_desc = isset($item['technical_desc']) ? wp_kses_post($item['technical_desc']) : '';
            $short_desc = isset($item['short_desc']) ? wp_kses_post($item['short_desc']) : '';
            $tech_desc = $this->merge_short_desc_text($tech_desc, $short_desc);
            if($tech_desc !== '') { $data['technical_desc'] = $tech_desc; }
            $data['short_desc'] = null;

            $text_fields = [
                'solution_script','post_check','check_script','links'
            ];
            foreach($text_fields as $f) {
                if(isset($item[$f]) && $item[$f] !== '') {
                    $data[$f] = wp_kses_post($item[$f]);
                }
            }

            if($article_id) {
                $wpdb->update($wpdb->prefix.'kb_articles', $data, ['id'=>$article_id]);
                $updated++;
            } else {
                $wpdb->insert($wpdb->prefix.'kb_articles', $data);
                $created++;
            }
        }

        wp_send_json_success(['created' => $created, 'updated' => $updated, 'errors' => $errors]);
    }

    public function upload_image() {
        check_ajax_referer('kbnonce', 'nonce');

        $raw = isset($_POST['imagedata']) ? wp_unslash($_POST['imagedata']) : '';
        if(!$raw) {
            wp_send_json_error(['message' => 'Missing image data']);
        }

        $ext = 'png';
        if(preg_match('#^data:image/(\w+);base64,#', $raw, $m)) {
            $ext = strtolower($m[1]);
            $raw = substr($raw, strpos($raw, ',') + 1);
        }

        $data = base64_decode($raw, true);
        if($data === false) {
            wp_send_json_error(['message' => 'Invalid image data']);
        }

        $dir = wp_upload_dir();
        if(!empty($dir['error'])) {
            wp_send_json_error(['message' => $dir['error']]);
        }

        wp_mkdir_p($dir['path']);

        $allowed_ext = ['png','jpg','jpeg','gif','webp'];
        if(!in_array($ext, $allowed_ext, true)) {
            $ext = 'png';
        }

        $name = 'kb-paste-' . time() . '-' . wp_generate_password(6, false, false) . '.' . $ext;
        $path = trailingslashit($dir['path']) . $name;
        $url  = trailingslashit($dir['url']) . $name;

        if (file_put_contents($path, $data)) {
            wp_send_json_success(['url'=>$url]);
        }

        wp_send_json_error(['message' => 'Upload failed']);
    }

    public function handle_import_word_ajax() {
        check_ajax_referer('kbnonce', 'nonce');

        if(!$this->user_can_edit_article()) {
            wp_send_json_error(['message' => 'אין הרשאות לייבוא']);
        }

        if(!isset($_FILES['word_file'])) {
            wp_send_json_error(['message' => 'לא הועלה קובץ']);
        }

        $uploaded = wp_handle_upload($_FILES['word_file'], [
            'test_form' => false,
            'mimes' => [ 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' ],
        ]);

        if(isset($uploaded['error'])) {
            wp_send_json_error(['message' => $uploaded['error']]);
        }

        $result = $this->import_articles_from_docx($uploaded['file']);
        if(isset($uploaded['file']) && file_exists($uploaded['file'])) {
            @unlink($uploaded['file']);
        }

        wp_send_json_success($result);
    }

    public function download_word_template() {
        if(headers_sent()) {
            exit;
        }

        $file = $this->generate_word_template_docx();
        if(!$file || !file_exists($file)) {
            wp_die('יצירת התבנית נכשלה');
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="kb-import-template.docx"');
        header('Content-Length: '.filesize($file));
        readfile($file);
        @unlink($file);
        exit;
    }

    public function public_form_shortcode($atts = []) {
        $atts = shortcode_atts([
            'mode' => 'single'
        ], $atts, 'kb_public_form');

        if(isset($atts['mode']) && $atts['mode'] === 'grid') {
            return $this->bulk_public_grid_shortcode();
        }

        global $wpdb;
        list($main_cats, $sub_cats_map, $cats_by_name) = $this->get_category_hierarchy();

        $edit_id = isset($_GET['edit_article']) ? intval($_GET['edit_article']) : 0;
        $article = $edit_id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}kb_articles WHERE id=%d", $edit_id)) : null;
        $article = $this->merge_short_desc_into_technical($article);
        $status_labels = $this->get_status_labels();
        $current_status = $article ? intval($article->review_status) : 0;
        $current_rating = $article ? intval($article->user_rating) : 0;
        $current_vuln = $article ? intval($article->vulnerability_level) : 0;
        list($selected_main, $selected_sub) = $this->split_category_parts($article ? $article->category : '');
        $selected_main_id = 0; $selected_sub_id = 0;
        foreach($main_cats as $cat) { if($cat->category_name === $selected_main) { $selected_main_id = intval($cat->id); break; } }
        if($selected_main_id && isset($sub_cats_map[$selected_main_id])) {
            foreach($sub_cats_map[$selected_main_id] as $cat) {
                if($cat->category_name === $selected_sub) { $selected_sub_id = intval($cat->id); break; }
            }
        }

        $kb_home_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : get_permalink(get_the_ID());
        $kb_home_url = remove_query_arg('edit_article', $kb_home_url);
        
        ob_start();
        wp_nonce_field('save_article_nonce','article_nonce');
        ?>
        <div class="kb-container">
        <div class="kb-public-form-container">
        <h2><?php echo $article ? 'ערוך מאמר' : 'הוסף מאמר חדש'; ?></h2>

        <div class="kb-public-form-header">
            <button type="button" id="pub-save-btn" class="kb-btn kb-btn-primary">💾 שמירה</button>
            <button type="button" id="pub-save-new-btn" class="kb-btn kb-btn-warning">💾 שמור והוסף חדש</button>
            <a href="<?php echo esc_url($kb_home_url); ?>" class="kb-btn kb-btn-secondary">← חזור לרשימה</a>
        </div>
        
        <form id="kb-public-form" class="kb-form" enctype="multipart/form-data">
            <?php if($article): ?><input type="hidden" name="article_id" value="<?php echo $article->id; ?>"><?php endif; ?>
            
            <fieldset class="kb-fieldset">
                <legend>נתונים כלליים</legend>
                <div class="kb-row"><label class="kb-label">נושא: <span style="color:red;">*</span></label>
                    <input type="text" name="subject" class="kb-input" value="<?php echo $article ? esc_attr($article->subject) : ''; ?>" required>
                </div>
                <div class="kb-row"><label class="kb-label">קטגוריה:</label>
                    <select name="main_category_id" class="kb-input" required>
                        <option value="">בחר קטגוריה</option>
                        <?php foreach($main_cats as $cat) echo '<option value="'.intval($cat->id).'" '.selected($selected_main_id, $cat->id, false).'>'.esc_html($cat->category_name).'</option>'; ?>
                    </select>
                </div>
                <div class="kb-row"><label class="kb-label">תת קטגוריה:</label>
                    <select name="sub_category_id" class="kb-input">
                        <option value="">בחר תת קטגוריה</option>
                        <?php if($selected_main_id && isset($sub_cats_map[$selected_main_id])) { foreach($sub_cats_map[$selected_main_id] as $cat) { echo '<option value="'.intval($cat->id).'" '.selected($selected_sub_id, $cat->id, false).'>'.esc_html($cat->category_name).'</option>'; } } ?>
                    </select>
                </div>
                <div class="kb-row"><label class="kb-label">דירוג (1-100):</label>
                    <input type="number" name="user_rating" class="kb-input" min="1" max="100" value="<?php echo $current_rating ? intval($current_rating) : ''; ?>" placeholder="1-100">
                </div>
                <div class="kb-row"><label class="kb-label">פגיעות: <span class="kb-help-icon" data-tooltip="פגיעות של הארגון לשינוי">?</span></label>
                    <select name="vulnerability_level" class="kb-input">
                        <option value="">בחר דרגת פגיעות</option>
                        <option value="low" <?php selected($current_vuln, 1); ?>>נמוכה</option>
                        <option value="medium" <?php selected($current_vuln, 2); ?>>בינונית</option>
                        <option value="high" <?php selected($current_vuln, 3); ?>>גבוהה</option>
                    </select>
                </div>
                <div class="kb-row"><label class="kb-label">סטטוס בדיקה:</label>
                    <select name="review_status" class="kb-input">
                        <?php foreach($status_labels as $k=>$lbl) echo '<option value="'.intval($k).'" '.selected($current_status, $k, false).'>'.esc_html($lbl).'</option>'; ?>
                    </select>
                </div>
            </fieldset>
            
            <fieldset class="kb-fieldset">
                <legend>פרטים</legend>
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
                <button type="button" id="pub-save-btn2" class="kb-btn kb-btn-primary">💾 שמירה</button>
                <button type="button" id="pub-save-new-btn2" class="kb-btn kb-btn-warning">💾 שמור והוסף חדש</button>
                <a href="<?php echo esc_url($kb_home_url); ?>" class="kb-btn-back">← חזור לרשימה</a>
            </div>
            <div id="save-message"></div>
        </form>
        </div>

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
                            data.append("action", "kb_upload_image");
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

            const catData = {
                main: <?php echo wp_json_encode($main_cats); ?>,
                subs: <?php echo wp_json_encode($sub_cats_map); ?>
            };
            const mainSelect = document.querySelector('[name="main_category_id"]');
            const subSelect = document.querySelector('[name="sub_category_id"]');

            function populateSubs(parentId, selectedValue) {
                if(!subSelect) return;
                subSelect.innerHTML = '<option value="">בחר תת קטגוריה</option>';
                if(parentId && catData.subs[parentId]) {
                    catData.subs[parentId].forEach(function(cat){
                        const opt = document.createElement('option');
                        opt.value = cat.id;
                        opt.textContent = cat.category_name;
                        if(String(selectedValue) === String(cat.id)) opt.selected = true;
                        subSelect.appendChild(opt);
                    });
                }
            }

            if(mainSelect) {
                populateSubs(mainSelect.value, '<?php echo $selected_sub_id; ?>');
                mainSelect.addEventListener('change', function(){
                    populateSubs(this.value, '');
                });
            }

            function saveArticle(openNew) {
                // ⭐ בדיקת שדות חובה
                let subject = document.querySelector("[name=subject]").value.trim();
                let techSolution = document.querySelector("#pub_technical_solution").editorInstance ?
                    document.querySelector("#pub_technical_solution").editorInstance.getData().trim() : "";
                let mainCat = document.querySelector('[name="main_category_id"]') ? document.querySelector('[name="main_category_id"]').value : '';

                if(!subject) {
                    alert("❌ שדה נושא הוא שדה חובה!");
                    return;
                }

                if(!mainCat) {
                    alert("❌ יש לבחור קטגוריה למאמר!");
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

    public function bulk_public_grid_shortcode() {
        list($main_cats, $sub_cats_map, $cats_by_name) = $this->get_category_hierarchy();
        $status_labels = $this->get_status_labels();
        $bulk_nonce = wp_create_nonce('kb_bulk_save');
        global $wpdb;
        $table = $wpdb->prefix . 'kb_articles';
        $articles = $wpdb->get_results("SELECT * FROM $table WHERE (is_deleted IS NULL OR is_deleted=0) ORDER BY created_at DESC");
        foreach($articles as $a){ $this->merge_short_desc_into_technical($a); }
        $grid_rows = [];
        foreach($articles as $a) {
            list($main_cat, $sub_cat) = $this->split_category_parts($a->category);
            $main_id = isset($cats_by_name[$main_cat]) ? intval($cats_by_name[$main_cat]->id) : 0;
            $sub_id = 0;
            if($main_id && isset($sub_cats_map[$main_id])) {
                foreach($sub_cats_map[$main_id] as $sc) {
                    if($sc->category_name === $sub_cat) { $sub_id = intval($sc->id); break; }
                }
            }

            $grid_rows[] = [
                'id' => intval($a->id),
                'subject' => $a->subject,
                'main_category_id' => $main_id,
                'sub_category_id' => $sub_id,
                'user_rating' => $this->get_article_rating($a),
                'vulnerability_level' => $this->sanitize_vulnerability_level($a->vulnerability_level ?? null),
                'review_status' => intval($a->review_status),
                'technical_desc' => $a->technical_desc,
                'technical_solution' => $a->technical_solution,
                'solution_script' => $a->solution_script,
                'post_check' => $a->post_check,
                'check_script' => $a->check_script,
                'links' => $a->links,
                'main_label' => $main_cat,
                'sub_label' => $sub_cat,
                'status_label' => isset($status_labels[$a->review_status]) ? $status_labels[$a->review_status] : '',
                'vulnerability_label' => $this->get_vulnerability_label($a),
            ];
        }

        ob_start();
        ?>
        <div class="kb-container">
            <?php echo $this->render_navigation_bar('bulk'); ?>
            <div class="kb-bulk-header">
                <div>
                    <h2>עריכה קבוצתית</h2>
                    <p class="kb-bulk-subtitle">הזינו כמה מאמרים במקביל. כל השדות נמצאים בשורות גלילה רוחבית ותומכים בהדבקת תמונות ב-CKEditor.</p>
                </div>
                <div class="kb-bulk-actions">
                    <button type="button" id="kb-bulk-add-row" class="kb-btn kb-btn-secondary">➕ הוסף שורה</button>
                    <button type="button" id="kb-bulk-save" class="kb-btn kb-btn-primary">💾 שמור את כל השורות</button>
                </div>
            </div>

            <div class="kb-bulk-grid-wrapper">
                <table class="kb-bulk-grid">
                    <thead>
                        <tr>
                            <th class="kb-sortable" data-sort-key="subject">
                                <div class="kb-th-inner">
                                    <span>נושא*</span>
                                    <button type="button" class="kb-filter-toggle" data-filter-key="subjectLabel" aria-label="סינון נושא"><span class="kb-filter-caret">▼</span></button>
                                </div>
                                <div class="kb-filter-menu" data-filter-menu="subjectLabel"></div>
                            </th>
                            <th class="kb-sortable" data-sort-key="maincat">
                                <div class="kb-th-inner">
                                    <span>קטגוריה</span>
                                    <button type="button" class="kb-filter-toggle" data-filter-key="maincatLabel" aria-label="סינון קטגוריה"><span class="kb-filter-caret">▼</span></button>
                                </div>
                                <div class="kb-filter-menu" data-filter-menu="maincatLabel"></div>
                            </th>
                            <th class="kb-sortable" data-sort-key="subcat">
                                <div class="kb-th-inner">
                                    <span>תת קטגוריה</span>
                                    <button type="button" class="kb-filter-toggle" data-filter-key="subcatLabel" aria-label="סינון תת קטגוריה"><span class="kb-filter-caret">▼</span></button>
                                </div>
                                <div class="kb-filter-menu" data-filter-menu="subcatLabel"></div>
                            </th>
                            <th class="kb-sortable" data-sort-key="rating">
                                <div class="kb-th-inner">
                                    <span>דירוג (1-100)</span>
                                    <button type="button" class="kb-filter-toggle" data-filter-key="rating" aria-label="סינון דירוג"><span class="kb-filter-caret">▼</span></button>
                                </div>
                                <div class="kb-filter-menu" data-filter-menu="rating"></div>
                            </th>
                            <th class="kb-sortable" data-sort-key="vulnerability">
                                <div class="kb-th-inner">
                                    <span>פגיעות</span>
                                    <button type="button" class="kb-filter-toggle" data-filter-key="vulnerabilityLabel" aria-label="סינון פגיעות"><span class="kb-filter-caret">▼</span></button>
                                </div>
                                <div class="kb-filter-menu" data-filter-menu="vulnerabilityLabel"></div>
                            </th>
                            <th class="kb-sortable" data-sort-key="status">
                                <div class="kb-th-inner">
                                    <span>סטטוס</span>
                                    <button type="button" class="kb-filter-toggle" data-filter-key="statusLabel" aria-label="סינון סטטוס"><span class="kb-filter-caret">▼</span></button>
                                </div>
                                <div class="kb-filter-menu" data-filter-menu="statusLabel"></div>
                            </th>
                            <th>תיאור טכני</th>
                            <th>פתרון טכני*</th>
                            <th>סקריפט פתרון</th>
                            <th>בדיקה</th>
                            <th>סקריפט בדיקה</th>
                            <th>קישורים</th>
                        </tr>
                    </thead>
                    <tbody id="kb-bulk-body"></tbody>
                </table>
            </div>
            <div id="kb-bulk-message" class="kb-bulk-message" aria-live="polite"></div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function(){
            const catData = {
                main: <?php echo wp_json_encode($main_cats); ?>,
                subs: <?php echo wp_json_encode($sub_cats_map); ?>
            };
            const statusLabels = <?php echo wp_json_encode($status_labels); ?>;
            const existingArticles = <?php echo wp_json_encode($grid_rows); ?>;
            const bodyEl = document.getElementById('kb-bulk-body');
            let rowIdx = 0;
            let sortState = { field: 'subject', dir: 'asc' };
            const filterState = {};
            const escapeAttr = (val) => {
                if(val === null || val === undefined) return '';
                return String(val).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;');
            };
            const escContent = (val) => {
                if(val === null || val === undefined) return '';
                return String(val).replace(/`/g,'\\`');
            };

            if(!window.kbUploadAdapterDefined){
                class MyUploadAdapter {
                    constructor(loader){ this.loader = loader; }
                    upload(){
                        return this.loader.file.then(file => new Promise((resolve, reject)=>{
                            const reader = new FileReader();
                            reader.onload = () => {
                                const fd = new FormData();
                                fd.append('action','kb_upload_image');
                                fd.append('nonce', kbAjax.nonce);
                                fd.append('imagedata', reader.result);
                                fetch(kbAjax.ajaxurl, { method:'POST', body: fd })
                                    .then(res => res.json())
                                    .then(json => { if(json.success) resolve({default: json.data.url}); else reject('Upload failed'); })
                                    .catch(err => reject(err));
                            };
                            reader.readAsDataURL(file);
                        }));
                    }
                    abort(){}
                }
                function MyCustomUploadAdapterPlugin(editor){
                    editor.plugins.get('FileRepository').createUploadAdapter = (loader) => new MyUploadAdapter(loader);
                }
                window.MyCustomUploadAdapterPlugin = MyCustomUploadAdapterPlugin;
                window.kbUploadAdapterDefined = true;
            }

            function populateSubs(select, parentId) {
                select.innerHTML = '<option value="">בחר תת קטגוריה</option>';
                if(parentId && catData.subs[parentId]) {
                    catData.subs[parentId].forEach(cat => {
                        const opt = document.createElement('option');
                        opt.value = cat.id;
                        opt.textContent = cat.category_name;
                        select.appendChild(opt);
                    });
                }
            }

            function initEditors(scope){
                scope.querySelectorAll('.kb-grid-ckeditor').forEach(el => {
                    if(el.classList.contains('ck-initialized')) return;
                    ClassicEditor.create(el, { extraPlugins: [MyCustomUploadAdapterPlugin] })
                        .then(ed => {
                            el.classList.add('ck-initialized');
                            el.editorInstance = ed;
                            if(el.value){ ed.setData(el.value); }
                            ed.model.document.on('change:data', ()=> updateRowDataset(el.closest('tr')));
                        })
                        .catch(err => console.error(err));
                });
            }

            function updateRowDataset(tr){
                if(!tr) return;
                const subject = tr.querySelector('[name="subject"]').value.trim();
                const mainSel = tr.querySelector('[name="main_category_id"]');
                const subSel = tr.querySelector('[name="sub_category_id"]');
                const statusSel = tr.querySelector('[name="review_status"]');
                const rating = tr.querySelector('[name="user_rating"]').value.trim();
                const vulnSel = tr.querySelector('[name="vulnerability_level"]');

                tr.dataset.subject = subject.toLowerCase();
                tr.dataset.subjectLabel = subject;
                tr.dataset.maincat = mainSel && mainSel.value ? mainSel.options[mainSel.selectedIndex].text.toLowerCase() : '';
                tr.dataset.maincatLabel = mainSel && mainSel.value ? mainSel.options[mainSel.selectedIndex].text : '';
                tr.dataset.subcat = subSel && subSel.value ? subSel.options[subSel.selectedIndex].text.toLowerCase() : '';
                tr.dataset.subcatLabel = subSel && subSel.value ? subSel.options[subSel.selectedIndex].text : '';
                tr.dataset.status = statusSel ? statusSel.value : '';
                tr.dataset.statusLabel = statusSel && statusSel.value !== '' ? statusLabels[statusSel.value] || '' : '';
                tr.dataset.rating = rating;
                tr.dataset.vulnerability = vulnSel && vulnSel.value ? vulnSel.value : '';
                tr.dataset.vulnerabilityLabel = vulnSel && vulnSel.value ? vulnSel.options[vulnSel.selectedIndex].text : '';
            }

            function createRow(data = {}){
                rowIdx++;
                const tr = document.createElement('tr');
                tr.dataset.row = rowIdx;
                const ratingVal = (data.user_rating !== null && data.user_rating !== undefined) ? data.user_rating : '';
                const vulnMap = {1:'low',2:'medium',3:'high','low':'low','medium':'medium','high':'high'};
                const vulnVal = vulnMap[data.vulnerability_level] || '';
                tr.innerHTML = `
                    <td>
                        <input type="hidden" name="article_id" value="${data.id || ''}">
                        <input type="text" name="subject" class="kb-input" placeholder="נושא" value="${escapeAttr(data.subject || '')}" required>
                    </td>
                    <td>
                        <select name="main_category_id" class="kb-input">
                            <option value="">בחר קטגוריה</option>
                            ${catData.main.map(cat=>`<option value="${cat.id}">${cat.category_name}</option>`).join('')}
                        </select>
                    </td>
                    <td>
                        <select name="sub_category_id" class="kb-input"><option value="">בחר תת קטגוריה</option></select>
                    </td>
                    <td><input type="number" name="user_rating" class="kb-input" min="1" max="100" placeholder="1-100" value="${ratingVal === null ? '' : ratingVal}"></td>
                    <td>
                        <select name="vulnerability_level" class="kb-input">
                            <option value="">בחר</option>
                            <option value="low">נמוכה</option>
                            <option value="medium">בינונית</option>
                            <option value="high">גבוהה</option>
                        </select>
                    </td>
                    <td>
                        <select name="review_status" class="kb-input">
                            ${Object.keys(statusLabels).map(key=>`<option value="${key}" ${data.review_status == key ? 'selected' : ''}>${statusLabels[key]}</option>`).join('')}
                        </select>
                    </td>
                    <td><textarea name="technical_desc" class="kb-grid-ckeditor">${escContent(data.technical_desc || '')}</textarea></td>
                    <td><textarea name="technical_solution" class="kb-grid-ckeditor" required>${escContent(data.technical_solution || '')}</textarea></td>
                    <td><textarea name="solution_script" class="kb-input kb-grid-code" dir="ltr">${escContent(data.solution_script || '')}</textarea></td>
                    <td><textarea name="post_check" class="kb-grid-ckeditor">${escContent(data.post_check || '')}</textarea></td>
                    <td><textarea name="check_script" class="kb-input kb-grid-code" dir="ltr">${escContent(data.check_script || '')}</textarea></td>
                    <td><textarea name="links" class="kb-input">${escContent(data.links || '')}</textarea></td>
                `;
                bodyEl.appendChild(tr);

                const mainSel = tr.querySelector('select[name="main_category_id"]');
                const subSel = tr.querySelector('select[name="sub_category_id"]');
                if(data.main_category_id){ mainSel.value = data.main_category_id; populateSubs(subSel, data.main_category_id); }
                if(data.sub_category_id){ subSel.value = data.sub_category_id; }
                const vulnSel = tr.querySelector('select[name="vulnerability_level"]');
                vulnSel.value = vulnVal;

                mainSel.addEventListener('change', ()=>{ populateSubs(subSel, mainSel.value); updateRowDataset(tr); });
                subSel.addEventListener('change', ()=> updateRowDataset(tr));
                tr.querySelector('[name="subject"]').addEventListener('input', ()=> updateRowDataset(tr));
                tr.querySelectorAll('input[name="user_rating"], select[name="review_status"], select[name="vulnerability_level"]').forEach(el=>{
                    el.addEventListener('change', ()=> updateRowDataset(tr));
                });

                initEditors(tr);
                updateRowDataset(tr);
            }

            function getRows(){ return Array.from(bodyEl.querySelectorAll('tr')); }

            function matchesFilters(row){
                return Object.keys(filterState).every(function(key){
                    if(!filterState[key] || filterState[key].size === 0) return true;
                    const val = row.dataset[key] || '';
                    return filterState[key].has(val);
                });
            }

            function applyFilters(){
                getRows().forEach(row => {
                    row.style.display = matchesFilters(row) ? '' : 'none';
                });
            }

            function getSortValue(row, field){
                const val = row.dataset[field] || '';
                if(field === 'rating'){ return val === '' ? -Infinity : parseInt(val,10); }
                return val;
            }

            function applySort(){
                const rows = getRows();
                const dir = sortState.dir === 'desc' ? -1 : 1;
                rows.sort((a,b)=>{
                    const av = getSortValue(a, sortState.field);
                    const bv = getSortValue(b, sortState.field);
                    if(av === bv) return 0;
                    return av > bv ? dir : -dir;
                });
                rows.forEach(r => bodyEl.appendChild(r));
                document.querySelectorAll('.kb-sortable').forEach(th => {
                    th.dataset.sortDir = th.dataset.sortKey === sortState.field ? sortState.dir : '';
                });
            }

            function renderMenuOptions(menu, key){
                menu.innerHTML = '';
                const values = [];
                getRows().forEach(row => {
                    const val = row.dataset[key] || '';
                    if(val && !values.includes(val)) values.push(val);
                });
                values.sort((a,b)=> a.localeCompare(b, 'he'));
                values.forEach(val => {
                    const option = document.createElement('label');
                    option.className = 'kb-filter-option';
                    const input = document.createElement('input');
                    input.type = 'checkbox';
                    input.value = val;
                    if(!filterState[key]) filterState[key] = new Set();
                    input.checked = filterState[key].has(val);
                    input.addEventListener('change', function(){
                        if(this.checked) filterState[key].add(val); else filterState[key].delete(val);
                        applyFilters();
                    });
                    const text = document.createElement('span');
                    text.textContent = val;
                    option.appendChild(input);
                    option.appendChild(text);
                    menu.appendChild(option);
                });

                const actions = document.createElement('div');
                actions.className = 'kb-filter-actions';
                const clearBtn = document.createElement('button');
                clearBtn.type = 'button';
                clearBtn.textContent = 'נקה';
                clearBtn.addEventListener('click', function(){
                    filterState[key] = new Set();
                    renderMenuOptions(menu, key);
                    applyFilters();
                });
                actions.appendChild(clearBtn);
                menu.appendChild(actions);
            }

            let activeMenu = null;
            function closeMenus(except){
                document.querySelectorAll('.kb-filter-menu').forEach(menu=>{
                    if(except && menu.dataset.filterMenu === except) return;
                    menu.classList.remove('is-open');
                });
                activeMenu = except || null;
            }

            document.querySelectorAll('.kb-filter-toggle').forEach(btn => {
                btn.addEventListener('click', function(e){
                    e.stopPropagation();
                    const key = this.dataset.filterKey;
                    const menu = document.querySelector('.kb-filter-menu[data-filter-menu="'+key+'"]');
                    if(!menu) return;
                    if(activeMenu === key){ closeMenus(); return; }
                    closeMenus(key);
                    renderMenuOptions(menu, key);
                    menu.classList.add('is-open');
                });
            });

            document.addEventListener('click', function(e){
                if(e.target.closest('.kb-filter-menu') || e.target.closest('.kb-filter-toggle')) return;
                closeMenus();
            });

            document.querySelectorAll('.kb-sortable').forEach(th => {
                th.addEventListener('click', function(e){
                    if(e.target.closest('.kb-filter-toggle')) return;
                    const key = this.dataset.sortKey;
                    if(sortState.field === key){ sortState.dir = sortState.dir === 'asc' ? 'desc' : 'asc'; }
                    else { sortState.field = key; sortState.dir = 'asc'; }
                    applySort();
                });
            });

            existingArticles.forEach(data => createRow(data));
            if(!existingArticles.length){ createRow(); }

            applyFilters();
            applySort();

            document.getElementById('kb-bulk-add-row').addEventListener('click', ()=> createRow());

            document.getElementById('kb-bulk-save').addEventListener('click', function(){
                const rows = getRows();
                const payload = rows.map(tr => {
                    const item = {};
                    tr.querySelectorAll('input, select, textarea').forEach(el => {
                        if(el.classList.contains('kb-grid-ckeditor')) {
                            item[el.name] = el.editorInstance ? el.editorInstance.getData() : el.value;
                        } else {
                            item[el.name] = el.value;
                        }
                    });
                    return item;
                });

                const fd = new FormData();
                fd.append('action', 'kb_save_bulk_articles');
                fd.append('nonce', '<?php echo esc_js($bulk_nonce); ?>');
                fd.append('articles', JSON.stringify(payload));

                fetch(kbAjax.ajaxurl, { method:'POST', body: fd })
                    .then(res => res.json())
                    .then(json => {
                        if(json.success) {
                            const created = json.data.created || 0;
                            const updated = json.data.updated || 0;
                            const errs = json.data.errors || [];
                            let msg = `✓ נשמרו ${created} חדשים, עודכנו ${updated}.`;
                            if(errs.length) { msg += '<br>' + errs.join('<br>'); }
                            document.getElementById('kb-bulk-message').innerHTML = msg;
                            document.getElementById('kb-bulk-message').classList.add('kb-bulk-message--success');
                        } else {
                            const errMsg = (json.data && json.data.message) ? json.data.message : 'שמירה נכשלה';
                            document.getElementById('kb-bulk-message').innerHTML = '❌ ' + errMsg;
                            document.getElementById('kb-bulk-message').classList.remove('kb-bulk-message--success');
                        }
                    })
                    .catch(()=>{
                        document.getElementById('kb-bulk-message').innerHTML = '❌ שגיאה בבקשה';
                        document.getElementById('kb-bulk-message').classList.remove('kb-bulk-message--success');
                    });
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }

    public function articles_table_shortcode($atts = []) {
        global $wpdb;
        $table = $wpdb->prefix . 'kb_articles';

        $atts = shortcode_atts([
            'back_url' => '',
            'source_page' => ''
        ], $atts, 'kb_articles_table');

        $page_id = get_the_ID();
        $page_url = $atts['source_page'] ? $atts['source_page'] : get_permalink($page_id);
        $back_url = $atts['back_url'] ? $atts['back_url'] : (isset($_GET['kb_back']) ? esc_url($_GET['kb_back']) : '');

        $this->handle_public_article_action($page_url);
        $this->process_table_bulk_actions($page_url);

        $add_article_page = get_page_by_path('add-article');
        $add_article_url = $add_article_page ? get_permalink($add_article_page->ID) : '';
        $trash_page = get_page_by_path('trash-bin');
        $trash_url = $trash_page ? get_permalink($trash_page->ID) : '';
        $archive_page = get_page_by_path('archive-bin');
        $archive_url = $archive_page ? get_permalink($archive_page->ID) : 'https://kb.macomp.co.il/?page_id=14311';

        $articles = $wpdb->get_results("SELECT * FROM $table WHERE (is_deleted IS NULL OR is_deleted=0) AND (is_archived IS NULL OR is_archived=0) ORDER BY created_at DESC");
        foreach($articles as $a){ $this->merge_short_desc_into_technical($a); }
        $total_articles = count($articles);
        $status_labels = $this->get_status_labels();
        ob_start();
        ?>
        <div class="kb-container">
        <div class="kb-table-view-container">
            <?php echo $this->render_navigation_bar('table'); ?>
            <div class="kb-table-view-header">
                <h1>טבלת מאמרים</h1>
                <div class="kb-table-view-actions">
                    <?php if($back_url): ?><a class="kb-btn kb-btn-grey" href="<?php echo esc_url($back_url); ?>">← חזרה לתצוגת כרטיסים</a><?php endif; ?>
                    <?php if($add_article_url): ?><a class="kb-btn kb-btn-primary" href="<?php echo esc_url($add_article_url); ?>">➕ הוסף מאמר חדש</a><?php endif; ?>
                    <?php if($archive_url): ?><a class="kb-btn kb-btn-secondary" href="<?php echo esc_url($archive_url); ?>">📦 ארכיון</a><?php endif; ?>
                    <?php if($trash_url): ?><a class="kb-btn kb-btn-danger" href="<?php echo esc_url($trash_url); ?>">🗑️ סל מחזור</a><?php endif; ?>
                </div>
            </div>

            <form method="post" class="kb-bulk-form">
            <?php wp_nonce_field('kb_bulk_action'); ?>
            <div class="kb-table-search">
                <input type="text" id="kb-table-search" placeholder="חיפוש לפי נושא..." aria-label="חיפוש לפי נושא">
                <button type="button" id="kb-table-search-clear">נקה</button>
                <?php if(current_user_can('manage_options')): ?>
                    <div class="kb-bulk-actions">
                        <button type="submit" class="kb-btn kb-btn-danger" name="kb_bulk_action" value="trash_bulk">🗑️ מחיקה קבוצתית</button>
                        <button type="submit" class="kb-btn kb-btn-secondary" name="kb_bulk_action" value="archive_bulk">📦 העברה לארכיון</button>
                    </div>
                <?php endif; ?>
            </div>

            <table class="kb-table-view-table">
                <thead>
                    <tr>
                        <th class="kb-select-col"><input type="checkbox" id="kb-select-all"></th>
                        <th class="kb-sortable" data-sort-key="subject">
                            <div class="kb-th-inner">
                                <span>נושא</span>
                                <button type="button" class="kb-filter-toggle" data-filter-key="subjectLabel" aria-label="סינון נושא"><span class="kb-filter-caret">▼</span></button>
                            </div>
                            <div class="kb-filter-menu" data-filter-menu="subjectLabel"></div>
                        </th>
                        <th class="kb-sortable" data-sort-key="maincat">
                            <div class="kb-th-inner">
                                <span>קטגוריה</span>
                                <button type="button" class="kb-filter-toggle" data-filter-key="maincatLabel" aria-label="סינון קטגוריה"><span class="kb-filter-caret">▼</span></button>
                            </div>
                            <div class="kb-filter-menu" data-filter-menu="maincatLabel"></div>
                        </th>
                        <th class="kb-sortable" data-sort-key="subcat">
                            <div class="kb-th-inner">
                                <span>תת קטגוריה</span>
                                <button type="button" class="kb-filter-toggle" data-filter-key="subcatLabel" aria-label="סינון תת קטגוריה"><span class="kb-filter-caret">▼</span></button>
                            </div>
                            <div class="kb-filter-menu" data-filter-menu="subcatLabel"></div>
                        </th>
                        <th class="kb-sortable" data-sort-key="status">
                            <div class="kb-th-inner">
                                <span>נבדק</span>
                                <button type="button" class="kb-filter-toggle" data-filter-key="statusLabel" aria-label="סינון סטטוס"><span class="kb-filter-caret">▼</span></button>
                            </div>
                            <div class="kb-filter-menu" data-filter-menu="statusLabel"></div>
                        </th>
                        <th class="kb-sortable" data-sort-key="rating">
                            <div class="kb-th-inner">
                                <span>דירוג</span>
                                <button type="button" class="kb-filter-toggle" data-filter-key="rating" aria-label="סינון דירוג"><span class="kb-filter-caret">▼</span></button>
                            </div>
                            <div class="kb-filter-menu" data-filter-menu="rating"></div>
                        </th>
                        <th class="kb-sortable" data-sort-key="execution">
                            <div class="kb-th-inner">
                                <span>ביצוע</span>
                                <button type="button" class="kb-filter-toggle" data-filter-key="executionLabel" aria-label="סינון סוג ביצוע"><span class="kb-filter-caret">▼</span></button>
                            </div>
                            <div class="kb-filter-menu" data-filter-menu="executionLabel"></div>
                        </th>
                        <th class="kb-sortable" data-sort-key="vulnerability">
                            <div class="kb-th-inner">
                                <span>פגיעות <span class="kb-help-icon" data-tooltip="פגיעות של הארגון לשינוי">?</span></span>
                                <button type="button" class="kb-filter-toggle" data-filter-key="vulnerabilityLabel" aria-label="סינון פגיעות"><span class="kb-filter-caret">▼</span></button>
                            </div>
                            <div class="kb-filter-menu" data-filter-menu="vulnerabilityLabel"></div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($articles as $article):
                    list($main_cat, $sub_cat) = $this->split_category_parts($article->category);
                    $article_url = add_query_arg(['page_id'=>$page_id,'kb_article'=>$article->id], home_url('/'));
                    $edit_url = $add_article_page ? add_query_arg('edit_article', $article->id, get_permalink($add_article_page->ID)) : '';
                    $trash_link = current_user_can('manage_options') ? wp_nonce_url(add_query_arg(['page_id'=>$page_id,'kb_pub_action'=>'trash','article_id'=>$article->id], $page_url), 'kb_pub_action_'.$article->id) : '';
                    $rating_value = $this->get_article_rating($article);
                    $execution_mode = $this->get_execution_mode($article);
                    $vulnerability_label = $this->get_vulnerability_label($article);
                    $vulnerability_level = $this->sanitize_vulnerability_level($article->vulnerability_level ?? null);
                    $status_label = isset($status_labels[$article->review_status]) ? $status_labels[$article->review_status] : '';
                ?>
                    <tr class="kb-table-row" data-article-id="<?php echo intval($article->id); ?>" data-subject="<?php echo esc_attr(mb_strtolower($article->subject)); ?>" data-subject-label="<?php echo esc_attr($article->subject); ?>" data-maincat="<?php echo esc_attr(mb_strtolower($main_cat)); ?>" data-maincat-label="<?php echo esc_attr($main_cat); ?>" data-subcat="<?php echo esc_attr(mb_strtolower($sub_cat)); ?>" data-subcat-label="<?php echo esc_attr($sub_cat); ?>" data-status="<?php echo intval($article->review_status); ?>" data-status-label="<?php echo esc_attr($status_label); ?>" data-rating="<?php echo is_null($rating_value) ? '' : intval($rating_value); ?>" data-execution="<?php echo $execution_mode==='אוטומטי' ? 'auto' : 'manual'; ?>" data-execution-label="<?php echo esc_attr($execution_mode); ?>" data-vulnerability="<?php echo $vulnerability_level ? intval($vulnerability_level) : ''; ?>" data-vulnerability-label="<?php echo esc_attr($vulnerability_label); ?>">
                        <td><?php if(current_user_can('manage_options')): ?><input type="checkbox" name="kb_selected[]" value="<?php echo intval($article->id); ?>" class="kb-row-select"><?php endif; ?></td>
                        <td><?php echo esc_html($article->subject); ?></td>
                        <td><?php echo esc_html($main_cat); ?></td>
                        <td><?php echo esc_html($sub_cat); ?></td>
                        <td><?php echo $this->render_status_badge($article->review_status); ?></td>
                        <td><?php echo $this->render_rating_badge($article); ?></td>
                        <td><span class="kb-execution-chip <?php echo $execution_mode==='אוטומטי' ? 'kb-execution-auto' : 'kb-execution-manual'; ?>"><?php echo esc_html($execution_mode); ?></span></td>
                        <td><?php echo $vulnerability_label ? esc_html($vulnerability_label) : ''; ?></td>
                    </tr>
                    <tr class="kb-table-row-detail" data-article-id="<?php echo intval($article->id); ?>" style="display:none;">
                        <td colspan="8">
                            <div class="kb-detail-row-content">
                                <div class="kb-detail-row-header">
                                    <h3><?php echo esc_html($article->subject); ?></h3>
                                </div>
                                <div class="kb-detail-row-buttons">
                                    <?php if($edit_url): ?><a class="kb-btn kb-btn-secondary" href="<?php echo esc_url($edit_url); ?>">✏️ עריכה</a><?php endif; ?>
                                    <?php if($trash_link): ?><a class="kb-btn kb-btn-danger" href="<?php echo esc_url($trash_link); ?>" onclick="return confirm('להעביר את המאמר לסל מחזור?');">🗑️ מחיקה</a><?php endif; ?>
                                    <?php if(current_user_can('manage_options')): $archive_link = wp_nonce_url(add_query_arg(['page_id'=>$page_id,'kb_pub_action'=>'archive','article_id'=>$article->id], $page_url), 'kb_pub_action_'.$article->id); ?>
                                        <a class="kb-btn kb-btn-secondary" href="<?php echo esc_url($archive_link); ?>">📦 ארכיון</a>
                                    <?php endif; ?>
                                    <a class="kb-btn kb-btn-secondary" href="<?php echo esc_url($article_url); ?>">פתח מאמר</a>
                                </div>
                                <div class="kb-detail-row-meta">
                                    <?php echo $this->render_article_meta($article); ?>
                                </div>
                                <?php echo $this->render_article_body($article, false); ?>
                                <div class="kb-detail-row-close">
                                    <button type="button" class="kb-btn kb-btn-close" data-close-article="<?php echo intval($article->id); ?>">✖ סגור</button>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </form>
            <div class="kb-table-count">סה"כ מאמרים: <strong id="kb-table-count-number"><?php echo intval($total_articles); ?></strong></div>
        </div>
        </div>
        <style>
        .kb-table-view-container { width:100%; max-width:100%; margin:20px auto; padding:10px; box-sizing:border-box; font-family:Arial,sans-serif; }
        .kb-table-view-container .kb-btn { padding:6px 12px; border-radius:16px; border:1.4px solid #d5dbe5; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:6px; font-size:15px; font-weight:600; transition:all 0.18s; color:#111827; background:#f7f9fc; box-shadow:0 4px 10px rgba(15,23,42,0.08); }
        .kb-table-view-container .kb-btn-primary { background:#2f6df6; color:#fff; border-color:#2f6df6; box-shadow:0 10px 20px rgba(47,109,246,0.20); }
        .kb-table-view-container .kb-btn-secondary { background:#fff; color:#111827; border-color:#d5dbe5; box-shadow:0 5px 12px rgba(15,23,42,0.08); }
        .kb-table-view-container .kb-btn-danger { background:#fef2f2; color:#111827; border-color:#fca5a5; box-shadow:0 8px 16px rgba(220,38,38,0.15); }
        .kb-table-view-header { display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:15px; }
        .kb-table-view-header h1 { margin:0; color:#2c3e50; }
        .kb-table-view-actions { display:flex; gap:8px; flex-wrap:wrap; }
        .kb-table-search { display:flex; gap:8px; align-items:center; margin:0 0 10px 0; flex-wrap:wrap; }
        .kb-table-search input { padding:8px 10px; border:1px solid #cbd5e1; border-radius:8px; min-width:220px; font-size:15px; }
        .kb-table-search button { padding:6px 11px; border:1.3px solid #d5dbe5; border-radius:12px; background:#f7f9fc; cursor:pointer; font-weight:600; color:#111827; box-shadow:0 3px 8px rgba(15,23,42,0.06); }
        .kb-table-search button:hover { background:#eef2f7; border-color:#c4cddc; }
        .kb-bulk-actions { display:flex; gap:8px; flex-wrap:wrap; }
        .kb-btn-grey { background:#fff; color:#1f2937; border-color:#d1d5db; box-shadow:0 8px 18px rgba(15,23,42,0.08); }
        .kb-btn-grey:hover { background:#1f2937; color:#fff; border-color:#1f2937; }
        .kb-table-view-table { width:100%; border-collapse:collapse; background:#fff; box-shadow:0 2px 6px rgba(0,0,0,0.08); }
        .kb-table-view-table th, .kb-table-view-table td { padding:12px 10px; border-bottom:1px solid #e6e6e6; text-align:right; }
        .kb-table-view-table th { background:#f4f6f7; color:#2c3e50; font-weight:700; position:relative; }
        .kb-sortable { cursor:pointer; position:relative; }
        .kb-sortable[data-sort-dir="asc"]::after { content:"▲"; font-size:0.75em; margin-right:6px; color:#475569; }
        .kb-sortable[data-sort-dir="desc"]::after { content:"▼"; font-size:0.75em; margin-right:6px; color:#475569; }
        .kb-th-inner { display:flex; align-items:center; gap:6px; }
        .kb-filter-toggle { border:1.3px solid #d5dbe5; background:#f7f9fc; border-radius:10px; padding:1px 5px; cursor:pointer; font-weight:600; color:#111827; box-shadow:0 3px 8px rgba(15,23,42,.08); }
        .kb-filter-toggle:hover { background:#eef2f7; }
        .kb-filter-caret { font-size:6px; line-height:1; }
        .kb-filter-menu { position:absolute; top:100%; right:0; min-width:180px; background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:10px; box-shadow:0 14px 28px rgba(15,23,42,.18); display:none; z-index:25; text-align:right; }
        .kb-filter-menu.is-open { display:block; }
        .kb-filter-option { display:flex; align-items:center; gap:8px; margin-bottom:6px; color:#0f172a; font-weight:500; }
        .kb-filter-option input { accent-color:#2563eb; }
        .kb-filter-actions { text-align:left; margin-top:4px; }
        .kb-filter-actions button { background:#f1f5f9; border:1px solid #cbd5e1; border-radius:8px; padding:6px 10px; cursor:pointer; font-weight:700; }
        .kb-filter-actions button:hover { background:#e2e8f0; }
        .kb-table-row { cursor:pointer; }
        .kb-table-row:hover { background:#f9fbff; }
        .kb-table-row-detail td { background:#f7f9fa; }
        .kb-select-col { width:42px; }
        .kb-detail-row-content { padding:12px; }
        .kb-detail-row-header { display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:6px; }
        .kb-detail-row-header h3 { margin:0; color:#2c3e50; }
        .kb-detail-row-buttons { display:flex; gap:8px; flex-wrap:wrap; margin:0 0 10px 0; }
        .kb-detail-row-meta { margin:0 0 10px 0; }
        .kb-detail-row-close { margin-top:14px; text-align:right; }
        .kb-article-body-block .kb-section { margin:18px 0; padding:16px; background:#ececec; border-right:5px solid #3498db; border-radius:7px; }
        .kb-article-body-block .kb-section h3 { margin-top:0; color:#34495e; }
        .kb-article-body-block pre { background:transparent; padding:12px 0; border:none; white-space:pre-wrap; direction:ltr; text-align:left; font-family:"Courier New",Consolas,monospace; font-size:14px; line-height:1.5; }
        .kb-meta-inline { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:10px; }
        .kb-meta-chip { background:#f5f7fb; padding:6px 10px; border-radius:6px; color:#34495e; font-weight:500; }
        .kb-meta-status-chip .kb-status-badge { margin:0; }
        .kb-execution-chip { padding:0; border-radius:0; font-weight:500; background:transparent; color:#0f172a; border:none; }
        .kb-execution-auto { }
        .kb-execution-manual { }
        .kb-rating-badge { display:inline-block; padding:0; background:transparent; color:#0f172a; border:none; border-radius:0; font-weight:500; }
        .kb-btn-close { background:#34495e; }
        .kb-btn-close:hover { background:#2c3e50; }
        .kb-table-count { margin-top:10px; font-weight:600; color:#0f172a; text-align:right; }
        </style>
        <script>
        document.addEventListener('DOMContentLoaded', function(){
            var tableRows = Array.from(document.querySelectorAll('.kb-table-row'));
            var rowPairs = tableRows.map(function(row){
                return {
                    row: row,
                    detail: document.querySelector('.kb-table-row-detail[data-article-id="'+row.getAttribute('data-article-id')+'"]')
                };
            });

            var selectAll = document.getElementById('kb-select-all');
            var rowChecks = Array.from(document.querySelectorAll('.kb-row-select'));
            var bulkButtons = Array.from(document.querySelectorAll('.kb-bulk-actions button[type="submit"]'));

            function updateBulkState(){
                var anyChecked = rowChecks.some(function(cbx){ return cbx.checked; });
                bulkButtons.forEach(function(btn){ btn.disabled = !anyChecked; });
                if(selectAll){
                    var visibleChecks = rowChecks.filter(function(cbx){ return cbx.closest('tr') && cbx.closest('tr').style.display !== 'none'; });
                    var allChecked = visibleChecks.length && visibleChecks.every(function(cbx){ return cbx.checked; });
                    selectAll.checked = allChecked;
                }
            }

            if(selectAll){
                selectAll.addEventListener('change', function(){
                    rowChecks.forEach(function(cbx){
                        if(cbx.closest('tr') && cbx.closest('tr').style.display !== 'none'){
                            cbx.checked = selectAll.checked;
                        }
                    });
                    updateBulkState();
                });
            }

            rowChecks.forEach(function(cbx){
                cbx.addEventListener('change', updateBulkState);
            });
            updateBulkState();

            var filterState = {
                subjectLabel: new Set(),
                maincatLabel: new Set(),
                subcatLabel: new Set(),
                statusLabel: new Set(),
                rating: new Set(),
                executionLabel: new Set(),
                vulnerabilityLabel: new Set()
            };

            var activeMenu = null;

            function toggleDetail(row){
                var id = row.getAttribute('data-article-id');
                var detail = document.querySelector('.kb-table-row-detail[data-article-id="'+id+'"]');
                if(detail){
                    var open = detail.dataset.open === '1';
                    detail.dataset.open = open ? '0' : '1';
                    detail.style.display = open ? 'none' : 'table-row';
                }
            }

            function matchesSelectedFilters(row){
                var subjectQuery = (document.getElementById('kb-table-search').value || '').trim().toLowerCase();
                var subjectValue = (row.dataset.subject || '').toLowerCase();
                if(subjectQuery && subjectValue.indexOf(subjectQuery) === -1) return false;

                return Object.keys(filterState).every(function(key){
                    var selected = filterState[key];
                    if(!selected || selected.size === 0) return true;
                    var val = row.dataset[key] || '';
                    return val && selected.has(val);
                });
            }

            var countEl = document.getElementById('kb-table-count-number');

            function updateCount(){
                if(!countEl) return;
                var visibleRows = rowPairs.filter(function(pair){
                    return pair.row.style.display !== 'none';
                });
                countEl.textContent = visibleRows.length;
            }

            var countEl = document.getElementById('kb-trash-count-number');

            function updateCount(){
                if(!countEl) return;
                var visibleRows = rowPairs.filter(function(pair){ return pair.row.style.display !== 'none'; });
                countEl.textContent = visibleRows.length;
            }

            var countEl = document.getElementById('kb-archive-count-number');

            function updateCount(){
                if(!countEl) return;
                var visibleRows = rowPairs.filter(function(pair){ return pair.row.style.display !== 'none'; });
                countEl.textContent = visibleRows.length;
            }

            function applyFilters(){
                rowPairs.forEach(function(pair){
                    var visible = matchesSelectedFilters(pair.row);
                    pair.row.style.display = visible ? '' : 'none';
                    if(pair.detail){
                        if(!visible){
                            pair.detail.style.display = 'none';
                            pair.detail.dataset.open = '0';
                        } else if(pair.detail.dataset.open === '1'){
                            pair.detail.style.display = 'table-row';
                        }
                    }
                });
                updateBulkState();
                updateCount();
            }

            function closeMenus(exceptKey){
                document.querySelectorAll('.kb-filter-menu').forEach(function(menu){
                    if(exceptKey && menu.dataset.filterMenu === exceptKey){
                        return;
                    }
                    menu.classList.remove('is-open');
                });
                activeMenu = exceptKey || null;
            }

            function renderMenuOptions(menu, key){
                menu.innerHTML = '';
                var values = [];
                rowPairs.forEach(function(pair){
                    var value = pair.row.dataset[key] || '';
                    if(value && values.indexOf(value) === -1){
                        values.push(value);
                    }
                });
                if(key === 'rating'){
                    values.sort(function(a,b){ return parseInt(a,10) - parseInt(b,10); });
                } else {
                    values.sort(function(a,b){ return a.localeCompare(b, 'he'); });
                }

                values.forEach(function(val){
                    var option = document.createElement('label');
                    option.className = 'kb-filter-option';
                    var input = document.createElement('input');
                    input.type = 'checkbox';
                    input.value = val;
                    input.checked = filterState[key] && filterState[key].has(val);
                    input.addEventListener('change', function(){
                        if(!filterState[key]) filterState[key] = new Set();
                        if(this.checked){
                            filterState[key].add(val);
                        } else {
                            filterState[key].delete(val);
                        }
                        applyFilters();
                    });
                    var text = document.createElement('span');
                    text.textContent = val;
                    option.appendChild(input);
                    option.appendChild(text);
                    menu.appendChild(option);
                });

                var actions = document.createElement('div');
                actions.className = 'kb-filter-actions';
                var clearBtn = document.createElement('button');
                clearBtn.type = 'button';
                clearBtn.textContent = 'נקה';
                clearBtn.addEventListener('click', function(){
                    filterState[key] = new Set();
                    renderMenuOptions(menu, key);
                    applyFilters();
                });
                actions.appendChild(clearBtn);
                menu.appendChild(actions);
            }

            var sortState = { field: 'subject', dir: 'asc' };

            function getSortValue(row, field){
                var val = row.dataset[field] || '';
                if(field === 'rating'){
                    return val === '' ? -Infinity : parseInt(val, 10);
                }
                if(field === 'status' || field === 'vulnerability'){
                    return val === '' ? -Infinity : parseInt(val, 10);
                }
                if(field === 'execution'){
                    return val === 'auto' ? 1 : 0;
                }
                return val;
            }

            function updateSortIndicators(){
                document.querySelectorAll('.kb-sortable').forEach(function(th){
                    th.dataset.sortDir = '';
                    if(th.dataset.sortKey === sortState.field){
                        th.dataset.sortDir = sortState.dir;
                    }
                });
            }

            function applySort(){
                var tbody = document.querySelector('.kb-table-view-table tbody');
                var dir = sortState.dir === 'desc' ? -1 : 1;
                var field = sortState.field;
                rowPairs.sort(function(a,b){
                    var av = getSortValue(a.row, field);
                    var bv = getSortValue(b.row, field);
                    if(av === bv) return 0;
                    return av > bv ? dir : -dir;
                });
                rowPairs.forEach(function(pair){
                    tbody.appendChild(pair.row);
                    if(pair.detail) tbody.appendChild(pair.detail);
                });
                updateSortIndicators();
            }

            rowPairs.forEach(function(pair){
                pair.row.addEventListener('click', function(e){
                    if(e.target.closest('a, button, input, select, textarea')) return;
                    toggleDetail(pair.row);
                });
            });

            document.querySelectorAll('.kb-btn-close').forEach(function(btn){
                btn.addEventListener('click', function(e){
                    e.stopPropagation();
                    var id = this.getAttribute('data-close-article');
                    var detail = document.querySelector('.kb-table-row-detail[data-article-id="'+id+'"]');
                    if(detail){ detail.style.display = 'none'; detail.dataset.open = '0'; }
                });
            });

            document.querySelectorAll('.kb-filter-toggle').forEach(function(btn){
                btn.addEventListener('click', function(e){
                    e.stopPropagation();
                    var key = this.dataset.filterKey;
                    var menu = document.querySelector('.kb-filter-menu[data-filter-menu="'+key+'"][data-filter-menu]');
                    if(!menu) return;
                    if(activeMenu === key){
                        closeMenus();
                        return;
                    }
                    closeMenus(key);
                    renderMenuOptions(menu, key);
                    menu.classList.add('is-open');
                });
            });

            document.addEventListener('click', function(e){
                if(e.target.closest('.kb-filter-menu') || e.target.closest('.kb-filter-toggle')) return;
                closeMenus();
            });

            var searchInput = document.getElementById('kb-table-search');
            var clearBtn = document.getElementById('kb-table-search-clear');
            if(searchInput){
                searchInput.addEventListener('input', function(){ applyFilters(); });
            }
            if(clearBtn){
                clearBtn.addEventListener('click', function(){
                    if(searchInput){
                        searchInput.value = '';
                        applyFilters();
                        searchInput.focus();
                    }
                });
            }

            document.querySelectorAll('.kb-sortable').forEach(function(th){
                th.addEventListener('click', function(){
                    var key = this.dataset.sortKey;
                    if(!key) return;
                    if(sortState.field === key){
                        sortState.dir = sortState.dir === 'asc' ? 'desc' : 'asc';
                    } else {
                        sortState.field = key;
                        sortState.dir = 'asc';
                    }
                    applySort();
                });
            });

            applyFilters();
            applySort();
        });
        </script>
        <?php
        return ob_get_clean();
    }

    public function trash_bin_shortcode($atts = []) {
        global $wpdb; $table = $wpdb->prefix . 'kb_articles';

        $atts = shortcode_atts([
            'back_url' => '',
            'table_url' => ''
        ], $atts, 'kb_trash_bin');

        $page_id = get_the_ID();
        $page_url = get_permalink($page_id);

        $this->handle_public_article_action($page_url);
        $this->process_table_bulk_actions($page_url);

        $table_page = get_page_by_path('kb-table');
        $table_url = $atts['table_url'] ? $atts['table_url'] : ($table_page ? get_permalink($table_page->ID) : 'https://kb.macomp.co.il/?page_id=14307');
        $back_url = $atts['back_url'];

        $articles = $wpdb->get_results("SELECT * FROM $table WHERE is_deleted=1 ORDER BY created_at DESC");
        foreach($articles as $a){ $this->merge_short_desc_into_technical($a); }
        $status_labels = $this->get_status_labels();
        $total_articles = count($articles);

        ob_start();
        ?>
        <div class="kb-container kb-trash-view">
        <div class="kb-table-view-container">
            <?php echo $this->render_navigation_bar('trash'); ?>
            <div class="kb-table-view-header">
                <h1>סל מחזור</h1>
                <div class="kb-table-view-actions">
                    <?php if($table_url): ?><a class="kb-btn kb-btn-secondary" href="<?php echo esc_url($table_url); ?>">📄 חזרה לטבלה</a><?php endif; ?>
                    <?php if(current_user_can('manage_options') && count($articles)>0): ?>
                        <?php $empty_url = wp_nonce_url(add_query_arg(['page_id'=>$page_id,'kb_pub_action'=>'empty'], $page_url), 'kb_pub_action_empty'); ?>
                        <a class="kb-btn kb-btn-danger" href="<?php echo esc_url($empty_url); ?>" onclick="return confirm('לנקות את סל המחזור לצמיתות?');">🧹 נקה סל</a>
                    <?php endif; ?>
                </div>
            </div>

            <form method="post" class="kb-bulk-form">
            <?php wp_nonce_field('kb_bulk_action'); ?>
            <div class="kb-table-search">
                <input type="text" id="kb-trash-search" placeholder="חיפוש לפי נושא..." aria-label="חיפוש לפי נושא">
                <button type="button" id="kb-trash-search-clear">נקה</button>
                <?php if(current_user_can('manage_options')): ?>
                    <div class="kb-bulk-actions">
                        <button type="submit" class="kb-btn kb-btn-secondary" name="kb_bulk_action" value="restore_bulk">↩ שחזור קבוצתי</button>
                        <button type="submit" class="kb-btn kb-btn-danger" name="kb_bulk_action" value="delete_bulk">❌ מחיקה לצמיתות</button>
                    </div>
                <?php endif; ?>
            </div>

            <table class="kb-table-view-table">
                <thead>
                    <tr>
                        <th class="kb-select-col"><input type="checkbox" id="kb-trash-select-all"></th>
                        <th class="kb-sortable" data-sort-key="subject">
                            <div class="kb-th-inner">
                                <span>נושא</span>
                                <button type="button" class="kb-filter-toggle" data-filter-key="subjectLabel" aria-label="סינון נושא"><span class="kb-filter-caret">▼</span></button>
                            </div>
                            <div class="kb-filter-menu" data-filter-menu="subjectLabel"></div>
                        </th>
                        <th class="kb-sortable" data-sort-key="maincat">
                            <div class="kb-th-inner">
                                <span>קטגוריה</span>
                                <button type="button" class="kb-filter-toggle" data-filter-key="maincatLabel" aria-label="סינון קטגוריה"><span class="kb-filter-caret">▼</span></button>
                            </div>
                            <div class="kb-filter-menu" data-filter-menu="maincatLabel"></div>
                        </th>
                        <th class="kb-sortable" data-sort-key="subcat">
                            <div class="kb-th-inner">
                                <span>תת קטגוריה</span>
                                <button type="button" class="kb-filter-toggle" data-filter-key="subcatLabel" aria-label="סינון תת קטגוריה"><span class="kb-filter-caret">▼</span></button>
                            </div>
                            <div class="kb-filter-menu" data-filter-menu="subcatLabel"></div>
                        </th>
                        <th class="kb-sortable" data-sort-key="status">
                            <div class="kb-th-inner">
                                <span>נבדק</span>
                                <button type="button" class="kb-filter-toggle" data-filter-key="statusLabel" aria-label="סינון סטטוס"><span class="kb-filter-caret">▼</span></button>
                            </div>
                            <div class="kb-filter-menu" data-filter-menu="statusLabel"></div>
                        </th>
                        <th class="kb-sortable" data-sort-key="rating">
                            <div class="kb-th-inner">
                                <span>דירוג</span>
                                <button type="button" class="kb-filter-toggle" data-filter-key="rating" aria-label="סינון דירוג"><span class="kb-filter-caret">▼</span></button>
                            </div>
                            <div class="kb-filter-menu" data-filter-menu="rating"></div>
                        </th>
                        <th class="kb-sortable" data-sort-key="execution">
                            <div class="kb-th-inner">
                                <span>ביצוע</span>
                                <button type="button" class="kb-filter-toggle" data-filter-key="executionLabel" aria-label="סינון סוג ביצוע"><span class="kb-filter-caret">▼</span></button>
                            </div>
                            <div class="kb-filter-menu" data-filter-menu="executionLabel"></div>
                        </th>
                        <th class="kb-sortable" data-sort-key="vulnerability">
                            <div class="kb-th-inner">
                                <span>פגיעות <span class="kb-help-icon" data-tooltip="פגיעות של הארגון לשינוי">?</span></span>
                                <button type="button" class="kb-filter-toggle" data-filter-key="vulnerabilityLabel" aria-label="סינון פגיעות"><span class="kb-filter-caret">▼</span></button>
                            </div>
                            <div class="kb-filter-menu" data-filter-menu="vulnerabilityLabel"></div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                <?php $add_article_page = get_page_by_path('add-article'); foreach($articles as $article):
                    list($main_cat, $sub_cat) = $this->split_category_parts($article->category);
                    $article_url = add_query_arg(['page_id'=>$page_id,'kb_article'=>$article->id], home_url('/'));
                    $edit_url = ($this->user_can_edit_article() && $add_article_page) ? add_query_arg('edit_article', $article->id, get_permalink($add_article_page->ID)) : '';
                    $restore = current_user_can('manage_options') ? wp_nonce_url(add_query_arg(['page_id'=>$page_id,'kb_pub_action'=>'restore','article_id'=>$article->id], $page_url), 'kb_pub_action_'.$article->id) : '';
                    $delete = current_user_can('manage_options') ? wp_nonce_url(add_query_arg(['page_id'=>$page_id,'kb_pub_action'=>'delete','article_id'=>$article->id], $page_url), 'kb_pub_action_'.$article->id) : '';
                    $rating_value = $this->get_article_rating($article);
                    $execution_mode = $this->get_execution_mode($article);
                    $vulnerability_label = $this->get_vulnerability_label($article);
                    $vulnerability_level = $this->sanitize_vulnerability_level($article->vulnerability_level ?? null);
                    $status_label = isset($status_labels[$article->review_status]) ? $status_labels[$article->review_status] : '';
                ?>
                    <tr class="kb-table-row" data-article-id="<?php echo intval($article->id); ?>" data-subject="<?php echo esc_attr(mb_strtolower($article->subject)); ?>" data-subject-label="<?php echo esc_attr($article->subject); ?>" data-maincat="<?php echo esc_attr(mb_strtolower($main_cat)); ?>" data-maincat-label="<?php echo esc_attr($main_cat); ?>" data-subcat="<?php echo esc_attr(mb_strtolower($sub_cat)); ?>" data-subcat-label="<?php echo esc_attr($sub_cat); ?>" data-status="<?php echo intval($article->review_status); ?>" data-status-label="<?php echo esc_attr($status_label); ?>" data-rating="<?php echo is_null($rating_value) ? '' : intval($rating_value); ?>" data-execution="<?php echo $execution_mode==='אוטומטי' ? 'auto' : 'manual'; ?>" data-execution-label="<?php echo esc_attr($execution_mode); ?>" data-vulnerability="<?php echo $vulnerability_level ? intval($vulnerability_level) : ''; ?>" data-vulnerability-label="<?php echo esc_attr($vulnerability_label); ?>">
                        <td><?php if(current_user_can('manage_options')): ?><input type="checkbox" name="kb_selected[]" value="<?php echo intval($article->id); ?>" class="kb-row-select"><?php endif; ?></td>
                        <td><?php echo esc_html($article->subject); ?></td>
                        <td><?php echo esc_html($main_cat); ?></td>
                        <td><?php echo esc_html($sub_cat); ?></td>
                        <td><?php echo $this->render_status_badge($article->review_status); ?></td>
                        <td><?php echo $this->render_rating_badge($article); ?></td>
                        <td><span class="kb-execution-chip <?php echo $execution_mode==='אוטומטי' ? 'kb-execution-auto' : 'kb-execution-manual'; ?>"><?php echo esc_html($execution_mode); ?></span></td>
                        <td><?php echo $vulnerability_label ? esc_html($vulnerability_label) : ''; ?></td>
                    </tr>
                    <tr class="kb-table-row-detail" data-article-id="<?php echo intval($article->id); ?>" style="display:none;">
                        <td colspan="8">
                            <div class="kb-detail-row-content">
                                <div class="kb-detail-row-header">
                                    <h3><?php echo esc_html($article->subject); ?></h3>
                                </div>
                                <div class="kb-detail-row-buttons">
                                    <?php if($edit_url): ?><a class="kb-btn kb-btn-secondary" href="<?php echo esc_url($edit_url); ?>">✏️ עריכה</a><?php endif; ?>
                                    <?php if($restore): ?><a class="kb-btn kb-btn-secondary" href="<?php echo esc_url($restore); ?>">↩ שחזור</a><?php endif; ?>
                                    <?php if($delete): ?><a class="kb-btn kb-btn-danger" href="<?php echo esc_url($delete); ?>" onclick="return confirm('למחוק לצמיתות?');">❌ מחיקה</a><?php endif; ?>
                                    <a class="kb-btn kb-btn-secondary" href="<?php echo esc_url($article_url); ?>">פתח מאמר</a>
                                </div>
                                <div class="kb-detail-row-meta">
                                    <?php echo $this->render_article_meta($article); ?>
                                </div>
                                <?php echo $this->render_article_body($article, false); ?>
                                <div class="kb-detail-row-close">
                                    <button type="button" class="kb-btn kb-btn-close" data-close-article="<?php echo intval($article->id); ?>">✖ סגור</button>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </form>
            <div class="kb-table-count">סה"כ מאמרים: <strong id="kb-trash-count-number"><?php echo intval($total_articles); ?></strong></div>
        </div>
        </div>
        <style>
        .kb-table-view-container { width:100%; max-width:100%; margin:20px auto; padding:10px; box-sizing:border-box; font-family:Arial,sans-serif; }
        .kb-table-view-container .kb-btn { padding:6px 12px; border-radius:16px; border:1.4px solid #d5dbe5; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:6px; font-size:15px; font-weight:600; transition:all 0.18s; color:#111827; background:#f7f9fc; box-shadow:0 4px 10px rgba(15,23,42,0.08); }
        .kb-table-view-container .kb-btn-primary { background:#2f6df6; color:#fff; border-color:#2f6df6; box-shadow:0 10px 20px rgba(47,109,246,0.20); }
        .kb-table-view-container .kb-btn-secondary { background:#fff; color:#111827; border-color:#d5dbe5; box-shadow:0 5px 12px rgba(15,23,42,0.08); }
        .kb-table-view-container .kb-btn-danger { background:#fef2f2; color:#111827; border-color:#fca5a5; box-shadow:0 8px 16px rgba(220,38,38,0.15); }
        .kb-table-view-header { display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:15px; }
        .kb-table-view-header h1 { margin:0; color:#2c3e50; }
        .kb-table-view-actions { display:flex; gap:8px; flex-wrap:wrap; }
        .kb-table-search { display:flex; gap:8px; align-items:center; margin:0 0 10px 0; flex-wrap:wrap; }
        .kb-table-search input { padding:8px 10px; border:1px solid #cbd5e1; border-radius:8px; min-width:220px; font-size:15px; }
        .kb-table-search button { padding:6px 11px; border:1.3px solid #d5dbe5; border-radius:12px; background:#f7f9fc; cursor:pointer; font-weight:600; color:#111827; box-shadow:0 3px 8px rgba(15,23,42,0.06); }
        .kb-table-search button:hover { background:#eef2f7; border-color:#c4cddc; }
        .kb-bulk-actions { display:flex; gap:8px; flex-wrap:wrap; }
        .kb-table-view-table { width:100%; border-collapse:collapse; background:#fff; box-shadow:0 2px 6px rgba(0,0,0,0.08); }
        .kb-table-view-table th, .kb-table-view-table td { padding:12px 10px; border-bottom:1px solid #e6e6e6; text-align:right; }
        .kb-table-view-table th { background:#f4f6f7; color:#2c3e50; font-weight:700; position:relative; }
        .kb-sortable { cursor:pointer; position:relative; }
        .kb-sortable[data-sort-dir="asc"]::after { content:"▲"; font-size:0.75em; margin-right:6px; color:#475569; }
        .kb-sortable[data-sort-dir="desc"]::after { content:"▼"; font-size:0.75em; margin-right:6px; color:#475569; }
        .kb-th-inner { display:flex; align-items:center; gap:6px; }
        .kb-filter-toggle { border:1.3px solid #d5dbe5; background:#f7f9fc; border-radius:10px; padding:1px 5px; cursor:pointer; font-weight:600; color:#111827; box-shadow:0 3px 8px rgba(15,23,42,.08); }
        .kb-filter-toggle:hover { background:#eef2f7; }
        .kb-filter-caret { font-size:6px; line-height:1; }
        .kb-filter-menu { position:absolute; top:100%; right:0; min-width:180px; background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:10px; box-shadow:0 14px 28px rgba(15,23,42,.18); display:none; z-index:25; text-align:right; }
        .kb-filter-menu.is-open { display:block; }
        .kb-filter-option { display:flex; align-items:center; gap:8px; margin-bottom:6px; color:#0f172a; font-weight:500; }
        .kb-filter-option input { accent-color:#2563eb; }
        .kb-filter-actions { text-align:left; margin-top:4px; }
        .kb-filter-actions button { background:#f1f5f9; border:1px solid #cbd5e1; border-radius:8px; padding:6px 10px; cursor:pointer; font-weight:700; color:#0f172a; }
        .kb-filter-actions button:hover { background:#e2e8f0; }
        .kb-table-row { cursor:pointer; }
        .kb-table-row:hover { background:#f9fbff; }
        .kb-table-row-detail td { background:#f7f9fa; }
        .kb-select-col { width:42px; }
        .kb-detail-row-content { padding:12px; }
        .kb-detail-row-header { display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:6px; }
        .kb-detail-row-header h3 { margin:0; color:#2c3e50; }
        .kb-detail-row-buttons { display:flex; gap:8px; flex-wrap:wrap; margin:0 0 10px 0; }
        .kb-detail-row-meta { margin:0 0 10px 0; }
        .kb-detail-row-close { margin-top:14px; text-align:right; }
        .kb-article-body-block .kb-section { margin:18px 0; padding:16px; background:#ececec; border-right:5px solid #3498db; border-radius:7px; }
        .kb-article-body-block .kb-section h3 { margin-top:0; color:#34495e; }
        .kb-article-body-block pre { background:transparent; padding:12px 0; border:none; white-space:pre-wrap; direction:ltr; text-align:left; font-family:"Courier New",Consolas,monospace; font-size:14px; line-height:1.5; }
        .kb-meta-inline { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:10px; }
        .kb-meta-chip { background:#f5f7fb; padding:6px 10px; border-radius:6px; color:#34495e; font-weight:500; }
        .kb-meta-status-chip .kb-status-badge { margin:0; }
        .kb-execution-chip { padding:0; border-radius:0; font-weight:500; background:transparent; color:#0f172a; border:none; }
        .kb-rating-badge { display:inline-block; padding:0; background:transparent; color:#0f172a; border:none; border-radius:0; font-weight:500; }
        .kb-btn-close { background:#e2e8f0; color:#0f172a; }
        .kb-btn-close:hover { background:#cbd5e1; }
        .kb-table-count { margin-top:10px; font-weight:600; color:#0f172a; text-align:right; }
        </style>
        <script>
        document.addEventListener('DOMContentLoaded', function(){
            var container = document.querySelector('.kb-trash-view');
            if(!container) return;

            var tableRows = Array.from(container.querySelectorAll('.kb-table-row'));
            var rowPairs = tableRows.map(function(row){
                return {
                    row: row,
                    detail: container.querySelector('.kb-table-row-detail[data-article-id="'+row.getAttribute('data-article-id')+'"]')
                };
            });

            var selectAll = container.querySelector('#kb-trash-select-all');
            var rowChecks = Array.from(container.querySelectorAll('.kb-row-select'));
            var bulkButtons = Array.from(container.querySelectorAll('.kb-bulk-actions button[type="submit"]'));

            function updateBulkState(){
                var anyChecked = rowChecks.some(function(cbx){ return cbx.checked; });
                bulkButtons.forEach(function(btn){ btn.disabled = !anyChecked; });
                if(selectAll){
                    var visibleChecks = rowChecks.filter(function(cbx){ return cbx.closest('tr') && cbx.closest('tr').style.display !== 'none'; });
                    var allChecked = visibleChecks.length && visibleChecks.every(function(cbx){ return cbx.checked; });
                    selectAll.checked = allChecked;
                }
            }

            if(selectAll){
                selectAll.addEventListener('change', function(){
                    rowChecks.forEach(function(cbx){
                        if(cbx.closest('tr') && cbx.closest('tr').style.display !== 'none'){
                            cbx.checked = selectAll.checked;
                        }
                    });
                    updateBulkState();
                });
            }

            rowChecks.forEach(function(cbx){ cbx.addEventListener('change', updateBulkState); });
            updateBulkState();

            var filterState = {
                subjectLabel: new Set(),
                maincatLabel: new Set(),
                subcatLabel: new Set(),
                statusLabel: new Set(),
                rating: new Set(),
                executionLabel: new Set(),
                vulnerabilityLabel: new Set()
            };

            var activeMenu = null;

            function toggleDetail(row){
                var id = row.getAttribute('data-article-id');
                var detail = container.querySelector('.kb-table-row-detail[data-article-id="'+id+'"]');
                if(detail){
                    var open = detail.dataset.open === '1';
                    detail.dataset.open = open ? '0' : '1';
                    detail.style.display = open ? 'none' : 'table-row';
                }
            }

            function matchesSelectedFilters(row){
                var searchInput = container.querySelector('#kb-trash-search');
                var subjectQuery = searchInput ? (searchInput.value || '').trim().toLowerCase() : '';
                var subjectValue = (row.dataset.subject || '').toLowerCase();
                if(subjectQuery && subjectValue.indexOf(subjectQuery) === -1) return false;

                return Object.keys(filterState).every(function(key){
                    var selected = filterState[key];
                    if(!selected || selected.size === 0) return true;
                    var val = row.dataset[key] || '';
                    return val && selected.has(val);
                });
            }

            function applyFilters(){
                rowPairs.forEach(function(pair){
                    var show = matchesSelectedFilters(pair.row);
                    pair.row.style.display = show ? '' : 'none';
                    if(pair.detail) pair.detail.style.display = show && pair.detail.dataset.open === '1' ? 'table-row' : 'none';
                });
                updateBulkState();
                updateCount();
            }

            function getSortValue(row, key){
                if(key === 'rating') return parseInt(row.dataset.rating || 0, 10) || 0;
                if(key === 'status') return parseInt(row.dataset.status || 0, 10) || 0;
                if(key === 'execution') return row.dataset.execution === 'auto' ? 2 : 1;
                if(key === 'vulnerability') return parseInt(row.dataset.vulnerability || 0, 10) || 0;
                return (row.dataset[key] || '').toString();
            }

            var sortState = { field: 'subject', dir: 'asc' };

            function updateSortIndicators(){
                container.querySelectorAll('.kb-sortable').forEach(function(th){
                    th.dataset.sortDir = '';
                    if(th.dataset.sortKey === sortState.field){ th.dataset.sortDir = sortState.dir; }
                });
            }

            function applySort(){
                var tbody = container.querySelector('.kb-table-view-table tbody');
                var dir = sortState.dir === 'desc' ? -1 : 1;
                var field = sortState.field;
                rowPairs.sort(function(a,b){
                    var av = getSortValue(a.row, field);
                    var bv = getSortValue(b.row, field);
                    if(av === bv) return 0;
                    return av > bv ? dir : -dir;
                });
                rowPairs.forEach(function(pair){
                    tbody.appendChild(pair.row);
                    if(pair.detail) tbody.appendChild(pair.detail);
                });
                updateSortIndicators();
            }

            rowPairs.forEach(function(pair){
                pair.row.addEventListener('click', function(e){
                    if(e.target.closest('a, button, input, select, textarea')) return;
                    toggleDetail(pair.row);
                });
            });

            container.querySelectorAll('.kb-btn-close').forEach(function(btn){
                btn.addEventListener('click', function(e){
                    e.stopPropagation();
                    var id = this.getAttribute('data-close-article');
                    var detail = container.querySelector('.kb-table-row-detail[data-article-id="'+id+'"]');
                    if(detail){ detail.style.display = 'none'; detail.dataset.open = '0'; }
                });
            });

            function collectOptions(key){
                var values = new Set();
                rowPairs.forEach(function(pair){
                    var label = pair.row.dataset[key] || '';
                    if(label) values.add(label);
                });
                return Array.from(values).sort();
            }

            function renderMenuOptions(menu, key){
                var opts = collectOptions(key);
                var html = '';
                opts.forEach(function(val){
                    var checked = filterState[key].has(val) ? 'checked' : '';
                    html += '<label class="kb-filter-option"><input type="checkbox" data-filter-option="'+key+'" value="'+val+'" '+checked+'> '+val+'</label>';
                });
                html += '<div class="kb-filter-actions"><button type="button" data-filter-clear="'+key+'">נקה</button></div>';
                menu.innerHTML = html;
                menu.querySelectorAll('input[data-filter-option]').forEach(function(inp){
                    inp.addEventListener('change', function(){
                        var set = filterState[key];
                        if(this.checked) set.add(this.value); else set.delete(this.value);
                        applyFilters();
                    });
                });
                var clearBtn = menu.querySelector('button[data-filter-clear]');
                if(clearBtn){
                    clearBtn.addEventListener('click', function(){
                        filterState[key].clear();
                        renderMenuOptions(menu, key);
                        applyFilters();
                    });
                }
            }

            function closeMenus(except){
                container.querySelectorAll('.kb-filter-menu').forEach(function(menu){
                    if(except && menu.dataset.filterMenu === except) return;
                    menu.classList.remove('is-open');
                });
                activeMenu = null;
            }

            container.querySelectorAll('.kb-filter-toggle').forEach(function(btn){
                btn.addEventListener('click', function(e){
                    e.stopPropagation();
                    var key = this.dataset.filterKey;
                    var menu = container.querySelector('.kb-filter-menu[data-filter-menu="'+key+'"]');
                    if(!menu) return;
                    if(activeMenu === key){ closeMenus(); return; }
                    closeMenus(key);
                    renderMenuOptions(menu, key);
                    menu.classList.add('is-open');
                    activeMenu = key;
                });
            });

            document.addEventListener('click', function(e){
                if(e.target.closest('.kb-filter-menu') || e.target.closest('.kb-filter-toggle')) return;
                closeMenus();
            });

            var searchInput = container.querySelector('#kb-trash-search');
            var clearBtn = container.querySelector('#kb-trash-search-clear');
            if(searchInput){ searchInput.addEventListener('input', applyFilters); }
            if(clearBtn){
                clearBtn.addEventListener('click', function(){
                    if(searchInput){ searchInput.value=''; searchInput.focus(); }
                    applyFilters();
                });
            }

            container.querySelectorAll('.kb-sortable').forEach(function(th){
                th.addEventListener('click', function(){
                    var key = this.dataset.sortKey;
                    if(!key) return;
                    if(sortState.field === key){ sortState.dir = sortState.dir === 'asc' ? 'desc' : 'asc'; }
                    else { sortState.field = key; sortState.dir = 'asc'; }
                    applySort();
                });
            });

            applyFilters();
            applySort();
        });
        </script>
        <?php
        return ob_get_clean();
    }

    public function archive_bin_shortcode($atts = []) {
        global $wpdb; $table = $wpdb->prefix . 'kb_articles';

        $atts = shortcode_atts([
            'back_url' => '',
            'table_url' => ''
        ], $atts, 'kb_archive_bin');

        $page_id = get_the_ID();
        $page_url = get_permalink($page_id);

        $this->handle_public_article_action($page_url);
        $this->process_table_bulk_actions($page_url);

        $table_page = get_page_by_path('kb-table');
        $table_url = $atts['table_url'] ? $atts['table_url'] : ($table_page ? get_permalink($table_page->ID) : 'https://kb.macomp.co.il/?page_id=14307');
        $back_url = $atts['back_url'];

        $articles = $wpdb->get_results("SELECT * FROM $table WHERE (is_archived=1) AND (is_deleted IS NULL OR is_deleted=0) ORDER BY subject ASC");
        foreach($articles as $a){ $this->merge_short_desc_into_technical($a); }
        $status_labels = $this->get_status_labels();
        $total_articles = count($articles);

        ob_start();
        ?>
        <div class="kb-container kb-archive-view">
        <div class="kb-table-view-container">
            <?php echo $this->render_navigation_bar('archive'); ?>
            <div class="kb-table-view-header">
                <h1>ארכיון מאמרים</h1>
                <div class="kb-table-view-actions">
                    <?php if($table_url): ?><a class="kb-btn kb-btn-secondary" href="<?php echo esc_url($table_url); ?>">📄 חזרה לטבלה</a><?php endif; ?>
                </div>
            </div>

            <form method="post" class="kb-bulk-form">
            <?php wp_nonce_field('kb_bulk_action'); ?>
            <div class="kb-table-search">
                <input type="text" id="kb-archive-search" placeholder="חיפוש לפי נושא..." aria-label="חיפוש לפי נושא">
                <button type="button" id="kb-archive-search-clear">נקה</button>
                <?php if(current_user_can('manage_options')): ?>
                    <div class="kb-bulk-actions">
                        <button type="submit" class="kb-btn kb-btn-secondary" name="kb_bulk_action" value="unarchive_bulk">↩ החזרה מהארכיון</button>
                        <button type="submit" class="kb-btn kb-btn-danger" name="kb_bulk_action" value="trash_bulk">🗑️ העברה לסל מחזור</button>
                    </div>
                <?php endif; ?>
            </div>

            <table class="kb-table-view-table">
                <thead>
                    <tr>
                        <th class="kb-select-col"><input type="checkbox" id="kb-archive-select-all"></th>
                        <th class="kb-sortable" data-sort-key="subject">
                            <div class="kb-th-inner">
                                <span>נושא</span>
                                <button type="button" class="kb-filter-toggle" data-filter-key="subjectLabel" aria-label="סינון נושא"><span class="kb-filter-caret">▼</span></button>
                            </div>
                            <div class="kb-filter-menu" data-filter-menu="subjectLabel"></div>
                        </th>
                        <th class="kb-sortable" data-sort-key="maincat">
                            <div class="kb-th-inner">
                                <span>קטגוריה</span>
                                <button type="button" class="kb-filter-toggle" data-filter-key="maincatLabel" aria-label="סינון קטגוריה"><span class="kb-filter-caret">▼</span></button>
                            </div>
                            <div class="kb-filter-menu" data-filter-menu="maincatLabel"></div>
                        </th>
                        <th class="kb-sortable" data-sort-key="subcat">
                            <div class="kb-th-inner">
                                <span>תת קטגוריה</span>
                                <button type="button" class="kb-filter-toggle" data-filter-key="subcatLabel" aria-label="סינון תת קטגוריה"><span class="kb-filter-caret">▼</span></button>
                            </div>
                            <div class="kb-filter-menu" data-filter-menu="subcatLabel"></div>
                        </th>
                        <th class="kb-sortable" data-sort-key="status">
                            <div class="kb-th-inner">
                                <span>נבדק</span>
                                <button type="button" class="kb-filter-toggle" data-filter-key="statusLabel" aria-label="סינון סטטוס"><span class="kb-filter-caret">▼</span></button>
                            </div>
                            <div class="kb-filter-menu" data-filter-menu="statusLabel"></div>
                        </th>
                        <th class="kb-sortable" data-sort-key="rating">
                            <div class="kb-th-inner">
                                <span>דירוג</span>
                                <button type="button" class="kb-filter-toggle" data-filter-key="rating" aria-label="סינון דירוג"><span class="kb-filter-caret">▼</span></button>
                            </div>
                            <div class="kb-filter-menu" data-filter-menu="rating"></div>
                        </th>
                        <th class="kb-sortable" data-sort-key="execution">
                            <div class="kb-th-inner">
                                <span>ביצוע</span>
                                <button type="button" class="kb-filter-toggle" data-filter-key="executionLabel" aria-label="סינון סוג ביצוע"><span class="kb-filter-caret">▼</span></button>
                            </div>
                            <div class="kb-filter-menu" data-filter-menu="executionLabel"></div>
                        </th>
                        <th class="kb-sortable" data-sort-key="vulnerability">
                            <div class="kb-th-inner">
                                <span>פגיעות <span class="kb-help-icon" data-tooltip="פגיעות של הארגון לשינוי">?</span></span>
                                <button type="button" class="kb-filter-toggle" data-filter-key="vulnerabilityLabel" aria-label="סינון פגיעות"><span class="kb-filter-caret">▼</span></button>
                            </div>
                            <div class="kb-filter-menu" data-filter-menu="vulnerabilityLabel"></div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                <?php $add_article_page = get_page_by_path('add-article'); foreach($articles as $article):
                    list($main_cat, $sub_cat) = $this->split_category_parts($article->category);
                    $article_url = add_query_arg(['page_id'=>$page_id,'kb_article'=>$article->id], home_url('/'));
                    $edit_url = ($this->user_can_edit_article() && $add_article_page) ? add_query_arg('edit_article', $article->id, get_permalink($add_article_page->ID)) : '';
                    $unarchive = current_user_can('manage_options') ? wp_nonce_url(add_query_arg(['page_id'=>$page_id,'kb_pub_action'=>'unarchive','article_id'=>$article->id], $page_url), 'kb_pub_action_'.$article->id) : '';
                    $trash = current_user_can('manage_options') ? wp_nonce_url(add_query_arg(['page_id'=>$page_id,'kb_pub_action'=>'trash','article_id'=>$article->id], $page_url), 'kb_pub_action_'.$article->id) : '';
                    $delete = current_user_can('manage_options') ? wp_nonce_url(add_query_arg(['page_id'=>$page_id,'kb_pub_action'=>'delete','article_id'=>$article->id], $page_url), 'kb_pub_action_'.$article->id) : '';
                    $rating_value = $this->get_article_rating($article);
                    $execution_mode = $this->get_execution_mode($article);
                    $vulnerability_label = $this->get_vulnerability_label($article);
                    $vulnerability_level = $this->sanitize_vulnerability_level($article->vulnerability_level ?? null);
                    $status_label = isset($status_labels[$article->review_status]) ? $status_labels[$article->review_status] : '';
                ?>
                    <tr class="kb-table-row" data-article-id="<?php echo intval($article->id); ?>" data-subject="<?php echo esc_attr(mb_strtolower($article->subject)); ?>" data-subject-label="<?php echo esc_attr($article->subject); ?>" data-maincat="<?php echo esc_attr(mb_strtolower($main_cat)); ?>" data-maincat-label="<?php echo esc_attr($main_cat); ?>" data-subcat="<?php echo esc_attr(mb_strtolower($sub_cat)); ?>" data-subcat-label="<?php echo esc_attr($sub_cat); ?>" data-status="<?php echo intval($article->review_status); ?>" data-status-label="<?php echo esc_attr($status_label); ?>" data-rating="<?php echo is_null($rating_value) ? '' : intval($rating_value); ?>" data-execution="<?php echo $execution_mode==='אוטומטי' ? 'auto' : 'manual'; ?>" data-execution-label="<?php echo esc_attr($execution_mode); ?>" data-vulnerability="<?php echo $vulnerability_level ? intval($vulnerability_level) : ''; ?>" data-vulnerability-label="<?php echo esc_attr($vulnerability_label); ?>">
                        <td><?php if(current_user_can('manage_options')): ?><input type="checkbox" name="kb_selected[]" value="<?php echo intval($article->id); ?>" class="kb-row-select"><?php endif; ?></td>
                        <td><?php echo esc_html($article->subject); ?></td>
                        <td><?php echo esc_html($main_cat); ?></td>
                        <td><?php echo esc_html($sub_cat); ?></td>
                        <td><?php echo $this->render_status_badge($article->review_status); ?></td>
                        <td><?php echo $this->render_rating_badge($article); ?></td>
                        <td><span class="kb-execution-chip <?php echo $execution_mode==='אוטומטי' ? 'kb-execution-auto' : 'kb-execution-manual'; ?>"><?php echo esc_html($execution_mode); ?></span></td>
                        <td><?php echo $vulnerability_label ? esc_html($vulnerability_label) : ''; ?></td>
                    </tr>
                    <tr class="kb-table-row-detail" data-article-id="<?php echo intval($article->id); ?>" style="display:none;">
                        <td colspan="8">
                            <div class="kb-detail-row-content">
                                <div class="kb-detail-row-header">
                                    <h3><?php echo esc_html($article->subject); ?></h3>
                                </div>
                                <div class="kb-detail-row-buttons">
                                    <?php if($edit_url): ?><a class="kb-btn kb-btn-secondary" href="<?php echo esc_url($edit_url); ?>">✏️ עריכה</a><?php endif; ?>
                                    <?php if($unarchive): ?><a class="kb-btn kb-btn-secondary" href="<?php echo esc_url($unarchive); ?>">↩ החזר</a><?php endif; ?>
                                    <?php if($trash): ?><a class="kb-btn kb-btn-danger" href="<?php echo esc_url($trash); ?>" onclick="return confirm('להעביר לסל מחזור?');">🗑️ סל מחזור</a><?php endif; ?>
                                    <?php if($delete): ?><a class="kb-btn kb-btn-danger" href="<?php echo esc_url($delete); ?>" onclick="return confirm('למחוק לצמיתות?');">❌ מחיקה</a><?php endif; ?>
                                    <a class="kb-btn kb-btn-secondary" href="<?php echo esc_url($article_url); ?>">פתח מאמר</a>
                                </div>
                                <div class="kb-detail-row-meta">
                                    <?php echo $this->render_article_meta($article); ?>
                                </div>
                                <?php echo $this->render_article_body($article, false); ?>
                                <div class="kb-detail-row-close">
                                    <button type="button" class="kb-btn kb-btn-close" data-close-article="<?php echo intval($article->id); ?>">✖ סגור</button>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </form>
            <div class="kb-table-count">סה"כ מאמרים: <strong id="kb-archive-count-number"><?php echo intval($total_articles); ?></strong></div>
        </div>
        </div>
        <style>
        .kb-table-view-container { width:100%; max-width:100%; margin:20px auto; padding:10px; box-sizing:border-box; font-family:Arial,sans-serif; }
        .kb-table-view-container .kb-btn { padding:6px 12px; border-radius:16px; border:1.4px solid #d5dbe5; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:6px; font-size:15px; font-weight:600; transition:all 0.18s; color:#111827; background:#f7f9fc; box-shadow:0 4px 10px rgba(15,23,42,0.08); }
        .kb-table-view-container .kb-btn-primary { background:#2f6df6; color:#fff; border-color:#2f6df6; box-shadow:0 10px 20px rgba(47,109,246,0.20); }
        .kb-table-view-container .kb-btn-secondary { background:#fff; color:#111827; border-color:#d5dbe5; box-shadow:0 5px 12px rgba(15,23,42,0.08); }
        .kb-table-view-container .kb-btn-danger { background:#fef2f2; color:#111827; border-color:#fca5a5; box-shadow:0 8px 16px rgba(220,38,38,0.15); }
        .kb-table-view-header { display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:15px; }
        .kb-table-view-header h1 { margin:0; color:#2c3e50; }
        .kb-table-view-actions { display:flex; gap:8px; flex-wrap:wrap; }
        .kb-table-search { display:flex; gap:8px; align-items:center; margin:0 0 10px 0; flex-wrap:wrap; }
        .kb-table-search input { padding:8px 10px; border:1px solid #cbd5e1; border-radius:8px; min-width:220px; font-size:15px; }
        .kb-table-search button { padding:6px 11px; border:1.3px solid #d5dbe5; border-radius:12px; background:#f7f9fc; cursor:pointer; font-weight:600; color:#111827; box-shadow:0 3px 8px rgba(15,23,42,0.06); }
        .kb-table-search button:hover { background:#eef2f7; border-color:#c4cddc; }
        .kb-bulk-actions { display:flex; gap:8px; flex-wrap:wrap; }
        .kb-table-view-table { width:100%; border-collapse:collapse; background:#fff; box-shadow:0 2px 6px rgba(0,0,0,0.08); }
        .kb-table-view-table th, .kb-table-view-table td { padding:12px 10px; border-bottom:1px solid #e6e6e6; text-align:right; }
        .kb-table-view-table th { background:#f4f6f7; color:#2c3e50; font-weight:700; position:relative; }
        .kb-sortable { cursor:pointer; position:relative; }
        .kb-sortable[data-sort-dir="asc"]::after { content:"▲"; font-size:0.75em; margin-right:6px; color:#475569; }
        .kb-sortable[data-sort-dir="desc"]::after { content:"▼"; font-size:0.75em; margin-right:6px; color:#475569; }
        .kb-th-inner { display:flex; align-items:center; gap:6px; }
        .kb-filter-toggle { border:1.3px solid #d5dbe5; background:#f7f9fc; border-radius:10px; padding:1px 5px; cursor:pointer; font-weight:600; color:#111827; box-shadow:0 3px 8px rgba(15,23,42,.08); }
        .kb-filter-toggle:hover { background:#eef2f7; }
        .kb-filter-caret { font-size:6px; line-height:1; }
        .kb-filter-menu { position:absolute; top:100%; right:0; min-width:180px; background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:10px; box-shadow:0 14px 28px rgba(15,23,42,.18); display:none; z-index:25; text-align:right; }
        .kb-filter-menu.is-open { display:block; }
        .kb-filter-option { display:flex; align-items:center; gap:8px; margin-bottom:6px; color:#0f172a; font-weight:500; }
        .kb-filter-option input { accent-color:#2563eb; }
        .kb-filter-actions { text-align:left; margin-top:4px; }
        .kb-filter-actions button { background:#f1f5f9; border:1px solid #cbd5e1; border-radius:8px; padding:6px 10px; cursor:pointer; font-weight:700; color:#0f172a; }
        .kb-filter-actions button:hover { background:#e2e8f0; }
        .kb-table-row { cursor:pointer; }
        .kb-table-row:hover { background:#f9fbff; }
        .kb-table-row-detail td { background:#f7f9fa; }
        .kb-select-col { width:42px; }
        .kb-detail-row-content { padding:12px; }
        .kb-detail-row-header { display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:6px; }
        .kb-detail-row-header h3 { margin:0; color:#2c3e50; }
        .kb-detail-row-buttons { display:flex; gap:8px; flex-wrap:wrap; margin:0 0 10px 0; }
        .kb-detail-row-meta { margin:0 0 10px 0; }
        .kb-detail-row-close { margin-top:14px; text-align:right; }
        .kb-article-body-block .kb-section { margin:18px 0; padding:16px; background:#ececec; border-right:5px solid #3498db; border-radius:7px; }
        .kb-article-body-block .kb-section h3 { margin-top:0; color:#34495e; }
        .kb-article-body-block pre { background:transparent; padding:12px 0; border:none; white-space:pre-wrap; direction:ltr; text-align:left; font-family:"Courier New",Consolas,monospace; font-size:14px; line-height:1.5; }
        .kb-meta-inline { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:10px; }
        .kb-meta-chip { background:#f5f7fb; padding:6px 10px; border-radius:6px; color:#34495e; font-weight:500; }
        .kb-meta-status-chip .kb-status-badge { margin:0; }
        .kb-execution-chip { padding:0; border-radius:0; font-weight:500; background:transparent; color:#0f172a; border:none; }
        .kb-rating-badge { display:inline-block; padding:0; background:transparent; color:#0f172a; border:none; border-radius:0; font-weight:500; }
        .kb-btn-close { background:#e2e8f0; color:#0f172a; }
        .kb-btn-close:hover { background:#cbd5e1; }
        .kb-table-count { margin-top:10px; font-weight:600; color:#0f172a; text-align:right; }
        </style>
        <script>
        document.addEventListener('DOMContentLoaded', function(){
            var container = document.querySelector('.kb-archive-view');
            if(!container) return;

            var tableRows = Array.from(container.querySelectorAll('.kb-table-row'));
            var rowPairs = tableRows.map(function(row){
                return {
                    row: row,
                    detail: container.querySelector('.kb-table-row-detail[data-article-id="'+row.getAttribute('data-article-id')+'"]')
                };
            });

            var selectAll = container.querySelector('#kb-archive-select-all');
            var rowChecks = Array.from(container.querySelectorAll('.kb-row-select'));
            var bulkButtons = Array.from(container.querySelectorAll('.kb-bulk-actions button[type="submit"]'));

            function updateBulkState(){
                var anyChecked = rowChecks.some(function(cbx){ return cbx.checked; });
                bulkButtons.forEach(function(btn){ btn.disabled = !anyChecked; });
                if(selectAll){
                    var visibleChecks = rowChecks.filter(function(cbx){ return cbx.closest('tr') && cbx.closest('tr').style.display !== 'none'; });
                    var allChecked = visibleChecks.length && visibleChecks.every(function(cbx){ return cbx.checked; });
                    selectAll.checked = allChecked;
                }
            }

            if(selectAll){
                selectAll.addEventListener('change', function(){
                    rowChecks.forEach(function(cbx){
                        if(cbx.closest('tr') && cbx.closest('tr').style.display !== 'none'){
                            cbx.checked = selectAll.checked;
                        }
                    });
                    updateBulkState();
                });
            }

            rowChecks.forEach(function(cbx){ cbx.addEventListener('change', updateBulkState); });
            updateBulkState();

            var filterState = {
                subjectLabel: new Set(),
                maincatLabel: new Set(),
                subcatLabel: new Set(),
                statusLabel: new Set(),
                rating: new Set(),
                executionLabel: new Set(),
                vulnerabilityLabel: new Set()
            };

            var activeMenu = null;

            function toggleDetail(row){
                var id = row.getAttribute('data-article-id');
                var detail = container.querySelector('.kb-table-row-detail[data-article-id="'+id+'"]');
                if(detail){
                    var open = detail.dataset.open === '1';
                    detail.dataset.open = open ? '0' : '1';
                    detail.style.display = open ? 'none' : 'table-row';
                }
            }

            function matchesSelectedFilters(row){
                var searchInput = container.querySelector('#kb-archive-search');
                var subjectQuery = searchInput ? (searchInput.value || '').trim().toLowerCase() : '';
                var subjectValue = (row.dataset.subject || '').toLowerCase();
                if(subjectQuery && subjectValue.indexOf(subjectQuery) === -1) return false;

                return Object.keys(filterState).every(function(key){
                    var selected = filterState[key];
                    if(!selected || selected.size === 0) return true;
                    var val = row.dataset[key] || '';
                    return val && selected.has(val);
                });
            }

            function applyFilters(){
                rowPairs.forEach(function(pair){
                    var show = matchesSelectedFilters(pair.row);
                    pair.row.style.display = show ? '' : 'none';
                    if(pair.detail) pair.detail.style.display = show && pair.detail.dataset.open === '1' ? 'table-row' : 'none';
                });
                updateBulkState();
                updateCount();
            }

            function getSortValue(row, key){
                if(key === 'rating') return parseInt(row.dataset.rating || 0, 10) || 0;
                if(key === 'status') return parseInt(row.dataset.status || 0, 10) || 0;
                if(key === 'execution') return row.dataset.execution === 'auto' ? 2 : 1;
                if(key === 'vulnerability') return parseInt(row.dataset.vulnerability || 0, 10) || 0;
                return (row.dataset[key] || '').toString();
            }

            var sortState = { field: 'subject', dir: 'asc' };

            function updateSortIndicators(){
                container.querySelectorAll('.kb-sortable').forEach(function(th){
                    th.dataset.sortDir = '';
                    if(th.dataset.sortKey === sortState.field){ th.dataset.sortDir = sortState.dir; }
                });
            }

            function applySort(){
                var tbody = container.querySelector('.kb-table-view-table tbody');
                var dir = sortState.dir === 'desc' ? -1 : 1;
                var field = sortState.field;
                rowPairs.sort(function(a,b){
                    var av = getSortValue(a.row, field);
                    var bv = getSortValue(b.row, field);
                    if(av === bv) return 0;
                    return av > bv ? dir : -dir;
                });
                rowPairs.forEach(function(pair){
                    tbody.appendChild(pair.row);
                    if(pair.detail) tbody.appendChild(pair.detail);
                });
                updateSortIndicators();
            }

            rowPairs.forEach(function(pair){
                pair.row.addEventListener('click', function(e){
                    if(e.target.closest('a, button, input, select, textarea')) return;
                    toggleDetail(pair.row);
                });
            });

            container.querySelectorAll('.kb-btn-close').forEach(function(btn){
                btn.addEventListener('click', function(e){
                    e.stopPropagation();
                    var id = this.getAttribute('data-close-article');
                    var detail = container.querySelector('.kb-table-row-detail[data-article-id="'+id+'"]');
                    if(detail){ detail.style.display = 'none'; detail.dataset.open = '0'; }
                });
            });

            function collectOptions(key){
                var values = new Set();
                rowPairs.forEach(function(pair){
                    var label = pair.row.dataset[key] || '';
                    if(label) values.add(label);
                });
                return Array.from(values).sort();
            }

            function renderMenuOptions(menu, key){
                var opts = collectOptions(key);
                var html = '';
                opts.forEach(function(val){
                    var checked = filterState[key].has(val) ? 'checked' : '';
                    html += '<label class="kb-filter-option"><input type="checkbox" data-filter-option="'+key+'" value="'+val+'" '+checked+'> '+val+'</label>';
                });
                html += '<div class="kb-filter-actions"><button type="button" data-filter-clear="'+key+'">נקה</button></div>';
                menu.innerHTML = html;
                menu.querySelectorAll('input[data-filter-option]').forEach(function(inp){
                    inp.addEventListener('change', function(){
                        var set = filterState[key];
                        if(this.checked) set.add(this.value); else set.delete(this.value);
                        applyFilters();
                    });
                });
                var clearBtn = menu.querySelector('button[data-filter-clear]');
                if(clearBtn){
                    clearBtn.addEventListener('click', function(){
                        filterState[key].clear();
                        renderMenuOptions(menu, key);
                        applyFilters();
                    });
                }
            }

            function closeMenus(except){
                container.querySelectorAll('.kb-filter-menu').forEach(function(menu){
                    if(except && menu.dataset.filterMenu === except) return;
                    menu.classList.remove('is-open');
                });
                activeMenu = null;
            }

            container.querySelectorAll('.kb-filter-toggle').forEach(function(btn){
                btn.addEventListener('click', function(e){
                    e.stopPropagation();
                    var key = this.dataset.filterKey;
                    var menu = container.querySelector('.kb-filter-menu[data-filter-menu="'+key+'"]');
                    if(!menu) return;
                    if(activeMenu === key){ closeMenus(); return; }
                    closeMenus(key);
                    renderMenuOptions(menu, key);
                    menu.classList.add('is-open');
                    activeMenu = key;
                });
            });

            document.addEventListener('click', function(e){
                if(e.target.closest('.kb-filter-menu') || e.target.closest('.kb-filter-toggle')) return;
                closeMenus();
            });

            var searchInput = container.querySelector('#kb-archive-search');
            var clearBtn = container.querySelector('#kb-archive-search-clear');
            if(searchInput){ searchInput.addEventListener('input', applyFilters); }
            if(clearBtn){
                clearBtn.addEventListener('click', function(){
                    if(searchInput){ searchInput.value=''; searchInput.focus(); }
                    applyFilters();
                });
            }

            container.querySelectorAll('.kb-sortable').forEach(function(th){
                th.addEventListener('click', function(){
                    var key = this.dataset.sortKey;
                    if(!key) return;
                    if(sortState.field === key){ sortState.dir = sortState.dir === 'asc' ? 'desc' : 'asc'; }
                    else { sortState.field = key; sortState.dir = 'asc'; }
                    applySort();
                });
            });

            applyFilters();
            applySort();
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

        $this->handle_public_article_action($page_url);

        $is_table_view = isset($_GET['kb_table']) && $_GET['kb_table'] == '1';
        if($is_table_view) {
            $back_to_cards = remove_query_arg('kb_table', $page_url);
            return $this->articles_table_shortcode([
                'back_url' => $back_to_cards,
                'source_page' => $page_url
            ]);
        }
        
        if($article_id > 0){
            $article = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE (is_deleted IS NULL OR is_deleted=0) AND (is_archived IS NULL OR is_archived=0) AND id=%d", $article_id));
            if(!$article) return '<div class="kb-notfound">❌ מאמר לא נמצא.</div>';
            $article = $this->merge_short_desc_into_technical($article);

            $add_article_page = get_page_by_path('add-article');
            $edit_url = $add_article_page ? add_query_arg('edit_article', $article->id, get_permalink($add_article_page->ID)) : '';
            $trash_link = current_user_can('manage_options') ? wp_nonce_url(add_query_arg(['page_id'=>$page_id,'kb_pub_action'=>'trash','article_id'=>$article->id], $page_url), 'kb_pub_action_'.$article->id) : '';
            $archive_link = current_user_can('manage_options') ? wp_nonce_url(add_query_arg(['page_id'=>$page_id,'kb_pub_action'=>'archive','article_id'=>$article->id], $page_url), 'kb_pub_action_'.$article->id) : '';
            
            $back_url = add_query_arg('page_id', $page_id, home_url('/'));
            if($search) $back_url = add_query_arg('kbs', $search, $back_url);
            if($cat_filter) $back_url = add_query_arg('kbcat', $cat_filter, $back_url);

            ob_start();
            ?>
            <div class="kb-container">
            <div class="kb-single-article">
                <?php echo $this->render_navigation_bar('home'); ?>
                <div class="kb-article-header">
                    <?php if($edit_url): ?>
                        <a href="<?php echo esc_url($edit_url); ?>" class="kb-btn-edit">✏️ ערוך מאמר</a>
                    <?php endif; ?>
                    <?php if($trash_link): ?>
                        <a href="<?php echo esc_url($trash_link); ?>" class="kb-btn-delete" onclick="return confirm('להעביר את המאמר לסל מחזור?');">🗑️ מחיקה</a>
                    <?php endif; ?>
                    <?php if($archive_link): ?>
                        <a href="<?php echo esc_url($archive_link); ?>" class="kb-btn-archive">📦 ארכיון</a>
                    <?php endif; ?>
                    <a href="<?php echo esc_url($back_url); ?>" class="kb-btn-back">← חזרה לרשימה</a>
                </div>

                <h1><?php echo esc_html($article->subject); ?></h1>
                <?php echo $this->render_article_meta($article); ?>
                <div class="kb-meta kb-meta-status"><?php echo $this->render_status_badge($article->review_status); ?></div>
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
                        <a href="<?php echo esc_url($edit_url); ?>" class="kb-btn kb-btn-secondary">✏️ ערוך מאמר</a>
                    <?php endif; ?>
                    <?php if($trash_link): ?>
                        <a href="<?php echo esc_url($trash_link); ?>" class="kb-btn kb-btn-danger" onclick="return confirm('להעביר את המאמר לסל מחזור?');">🗑️ מחיקה</a>
                    <?php endif; ?>
                    <?php if($archive_link): ?>
                        <a href="<?php echo esc_url($archive_link); ?>" class="kb-btn kb-btn-secondary">📦 ארכיון</a>
                    <?php endif; ?>
                    <a href="<?php echo esc_url($back_url); ?>" class="kb-btn kb-btn-secondary">← חזרה לרשימה</a>
                </div>
            </div>
            </div>
            <style>
            .kb-article-header, .kb-article-footer { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; gap:10px; flex-wrap:wrap; }
            .kb-article-footer { margin-top:30px; margin-bottom:0; }
            .kb-single-article { max-width:100%; width:100%; margin:30px auto; padding:30px; background:#fff; border-radius:8px; box-shadow:0 2px 10px rgba(0,0,0,0.1); box-sizing:border-box; }
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
        $table_view_url = 'https://kb.macomp.co.il/?page_id=14307';

        ob_start();
        ?>
        <div class="kb-container">
        <div class="kb-home-container">
            <?php echo $this->render_navigation_bar('home'); ?>
            <div class="kb-home-header">
                <h1>המאגר</h1>
                <div class="kb-home-actions">
                    <a href="<?php echo esc_url($add_article_url); ?>" class="kb-btn kb-btn-primary">➕ הוסף מאמר חדש</a>
                    <a href="<?php echo esc_url($table_view_url); ?>" class="kb-btn kb-btn-outline">📊 תצוגת טבלה</a>
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
                $sql = "SELECT * FROM $table WHERE (is_deleted IS NULL OR is_deleted=0) AND (is_archived IS NULL OR is_archived=0)";
                if($search !== '') {
                    $like = '%' . $wpdb->esc_like($search) . '%';
                    $sql .= $wpdb->prepare(" AND (subject LIKE %s OR technical_desc LIKE %s OR category LIKE %s)", $like, $like, $like);
                }
                if($cat_filter !== '' && $cat_filter !== 'all') {
                    $sql .= $wpdb->prepare(" AND category LIKE %s", '%'.$wpdb->esc_like($cat_filter).'%');
                }
                $sql .= " ORDER BY created_at DESC LIMIT 50";
                $results = $wpdb->get_results($sql);
                
                if($results): ?>
                    <h2>נמצאו <?php echo count($results); ?> מאמרים</h2>
                    <?php foreach($results as $article):
                        $article = $this->merge_short_desc_into_technical($article);
                        $excerpt = wp_strip_all_tags($article->technical_desc);
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

        </div>

        <style>
        .kb-home-container { max-width:100%; width:100%; margin:30px auto; padding:20px 10px; font-family:Arial,sans-serif; box-sizing:border-box; }
        .kb-home-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:25px; flex-wrap:wrap; gap:15px; }
        .kb-home-header h1 { margin:0; color:#2c3e50; }
        .kb-home-actions { display:flex; gap:10px; flex-wrap:wrap; }
        .kb-btn { padding:10px 20px; border-radius:999px; border:1.4px solid #cbd5f5; cursor:pointer; text-decoration:none; display:inline-block; font-size:15px; font-weight:700; transition:all 0.2s; color:#2563eb; background:#fff; box-shadow:0 8px 18px rgba(37,99,235,0.08); }
        .kb-btn-primary { background:#2563eb; color:#fff; border-color:#2563eb; }
        .kb-btn-outline { background:#fff; color:#2563eb; border-color:#cbd5f5; }
        .kb-btn-outline:hover { background:#2563eb; color:#fff; border-color:#2563eb; }
        .kb-btn-secondary { background:#fff; color:#2563eb; border-color:#cbd5f5; }
        .kb-btn-warning { background:#fffbeb; color:#92400e; border-color:#fcd34d; box-shadow:0 8px 18px rgba(252,211,77,0.25); }
        .kb-btn-search { background:#2563eb; color:#fff; border-color:#2563eb; }
        .kb-btn-clear { background:#fff; color:#dc2626; border-color:#fecdd3; box-shadow:0 8px 18px rgba(220,38,38,0.08); padding:10px 15px; }
        .kb-btn-success { background:#16a34a; color:#fff; border-color:#16a34a; box-shadow:0 8px 18px rgba(22,163,74,0.18); }
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

    public function settings_shortcode() {
        $template_url = add_query_arg('action', 'kb_download_word_template', admin_url('admin-ajax.php'));
        $nonce = wp_create_nonce('kbnonce');

        ob_start();
        ?>
        <div class="kb-container kb-settings-page">
            <?php echo $this->render_navigation_bar('settings'); ?>
            <h2 style="margin-top:0;">הגדרות וייבוא מאמרים</h2>
            <p class="kb-settings-lead">ייבוא מאמרים מקובץ וורד בפורמט מובנה, כולל הדבקת תמונות ישירות מתוך המסמך.</p>

            <div class="kb-settings-actions">
                <a class="kb-btn kb-btn-secondary" href="<?php echo esc_url($template_url); ?>">📥 הורדת תבנית וורד</a>
            </div>

            <div class="kb-settings-card">
                <h3>ייבוא וורד</h3>
                <form id="kb-word-import-form" enctype="multipart/form-data">
                    <label class="kb-input-label">בחר קובץ DOCX</label>
                    <input type="file" name="word_file" accept=".docx" required>
                    <button type="submit" class="kb-btn kb-btn-primary">ייבא מאמרים</button>
                </form>
                <div id="kb-import-results" class="kb-alert" style="display:none;"></div>
            </div>

            <div class="kb-settings-help">
                <h3>איך נראה הקובץ?</h3>
                <ul>
                    <li>כל מאמר מתחיל בשורה <strong>[[ARTICLE]]</strong>.</li>
                    <li>כותרות שדות: Subject, Category, Subcategory, Review Status, Vulnerability, Rating, Links.</li>
                    <li>קטעי תוכן מתחילים בכותרת עם נקודתיים (למשל <strong>Short Description:</strong>) והטקסט/תמונה אחריה.</li>
                    <li>ניתן להדביק תמונות בתוך כל קטע; הן יועלו אוטומטית וייכנסו לגוף המאמר.</li>
                </ul>
            </div>
        </div>
        <style>
            .kb-settings-page input[type="file"] { width:100%; padding:10px; border:1px solid #e2e8f0; border-radius:10px; }
            .kb-settings-card { background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:16px; margin:18px 0; }
            .kb-settings-actions { margin:10px 0 0 0; display:flex; gap:10px; flex-wrap:wrap; }
            .kb-settings-help { background:#fff7ed; border:1px solid #fed7aa; border-radius:12px; padding:14px; }
            .kb-settings-help ul { margin:0 18px 0 0; }
            .kb-settings-lead { color:#475569; margin:6px 0 14px 0; }
        </style>
        <script>
        (function(){
            if(typeof kbAjax === 'undefined') {
                window.kbAjax = { ajaxurl: '<?php echo esc_js(admin_url('admin-ajax.php')); ?>', nonce: '<?php echo esc_js($nonce); ?>' };
            }
            var form = document.getElementById('kb-word-import-form');
            if(!form) return;
            var results = document.getElementById('kb-import-results');
            form.addEventListener('submit', function(e){
                e.preventDefault();
                var fileInput = form.querySelector('input[type="file"]');
                if(!fileInput || !fileInput.files.length){
                    alert('בחר קובץ DOCX לייבוא');
                    return;
                }
                var fd = new FormData();
                fd.append('action', 'kb_import_word');
                fd.append('nonce', kbAjax.nonce);
                fd.append('word_file', fileInput.files[0]);
                results.style.display='block';
                results.className='kb-alert kb-alert--warning';
                results.textContent='מבצע ייבוא...';

                fetch(kbAjax.ajaxurl, { method:'POST', body: fd })
                    .then(function(res){ return res.json(); })
                    .then(function(json){
                        if(json.success){
                            var data = json.data || {};
                            var msg = 'יובאו ' + (data.created || 0) + ' מאמרים.';
                            if(data.errors && data.errors.length){
                                msg += '\n' + data.errors.join('\n');
                                results.className='kb-alert kb-alert--warning';
                            } else {
                                results.className='kb-alert kb-alert--success';
                            }
                            results.textContent = msg;
                        } else {
                            var err = (json.data && json.data.message) ? json.data.message : 'ייבוא נכשל';
                            results.className='kb-alert kb-alert--critical';
                            results.textContent = err;
                        }
                    })
                    .catch(function(){
                        results.className='kb-alert kb-alert--critical';
                        results.textContent='שגיאה במהלך הייבוא';
                    });
            });
        })();
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
