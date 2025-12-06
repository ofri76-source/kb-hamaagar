<?php
/**
 * Plugin Name: Image Paste Editor with CKEditor
 * Version: 2.2
 */
if (!defined('ABSPATH')) exit;

class ImagePasteCKEditor {
    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_upload_pasted_image', array($this, 'upload_image'));
        add_action('wp_ajax_save_article', array($this, 'save_article'));
        register_activation_hook(__FILE__, array($this, 'create_table'));
    }

    public function create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'paste_articles';
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            content longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function add_menu() {
        add_menu_page('Paste Editor', 'Paste Editor', 'manage_options', 'image-paste-ckeditor', array($this, 'list_page'), 'dashicons-edit');
        add_submenu_page('image-paste-ckeditor', 'חדש', 'חדש', 'manage_options', 'image-paste-ckeditor-new', array($this, 'edit_page'));
    }

    public function enqueue_scripts($hook) {
        if (strpos($hook, 'image-paste-ckeditor') === false) return;
        wp_enqueue_script('ckeditor5', 'https://cdn.ckeditor.com/ckeditor5/40.1.0/classic/ckeditor.js', array(), '40.1.0');
        wp_enqueue_script('paste-js', plugin_dir_url(__FILE__) . 'js/paste-ckeditor.js', array('jquery', 'ckeditor5'), '2.2', true);
        wp_enqueue_style('paste-css', plugin_dir_url(__FILE__) . 'css/style.css');
        wp_localize_script('paste-js', 'pasteData', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('paste_nonce'),
            'adminUrl' => admin_url()
        ));
    }

    public function list_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'paste_articles';
        if (isset($_GET['delete'])) {
            $wpdb->delete($table_name, array('id' => intval($_GET['delete'])));
            echo '<script>location.href="' . admin_url('admin.php?page=image-paste-ckeditor') . '";</script>';
        }
        $articles = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");
        ?>
        <div class="wrap">
            <h1>מאמרים</h1>
            <a href="<?php echo admin_url('admin.php?page=image-paste-ckeditor-new'); ?>" class="button button-primary">חדש</a>
            <table class="wp-list-table widefat" style="margin-top:20px">
                <thead><tr><th>כותרת</th><th>פעולות</th></tr></thead>
                <tbody>
                    <?php if (!empty($articles)): ?>
                        <?php foreach ($articles as $a): ?>
                            <tr>
                                <td><?php echo esc_html($a->title); ?></td>
                                <td>
                                    <a href="?page=image-paste-ckeditor-new&edit=<?php echo $a->id; ?>">ערוך</a> |
                                    <a href="?page=image-paste-ckeditor&delete=<?php echo $a->id; ?>" onclick="return confirm('בטוח?')">מחק</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="2">אין מאמרים</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function edit_page() {
        global $wpdb;
        $article = null;
        if (isset($_GET['edit'])) {
            $article = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}paste_articles WHERE id=%d", intval($_GET['edit'])));
        }
        ?>
        <div class="wrap">
            <h1><?php echo $article ? 'ערוך' : 'חדש'; ?></h1>
            <form id="article-form">
                <?php wp_nonce_field('save_article_nonce', 'article_nonce'); ?>
                <?php if ($article): ?>
                    <input type="hidden" name="article_id" value="<?php echo $article->id; ?>">
                <?php endif; ?>
                <p>
                    <input type="text" id="article-title" value="<?php echo $article ? esc_attr($article->title) : ''; ?>" class="large-text" placeholder="כותרת" required>
                </p>
                <div id="editor-container">
                    <textarea id="editor" style="display:none;"><?php echo $article ? esc_textarea($article->content) : ''; ?></textarea>
                </div>
                <p>
                    <button type="submit" class="button button-primary">שמור</button>
                    <a href="<?php echo admin_url('admin.php?page=image-paste-ckeditor'); ?>" class="button">ביטול</a>
                </p>
            </form>
            <div id="save-message"></div>
        </div>
        <?php
    }

    public function upload_image() {
        check_ajax_referer('paste_nonce', 'nonce');
        if (!current_user_can('upload_files')) {
            wp_send_json_error();
            return;
        }
        $data = preg_replace('#^data:image/[^;]*;base64,#', '', $_POST['image_data']);
        $data = base64_decode($data);
        if (!$data) {
            wp_send_json_error();
            return;
        }
        $dir = wp_upload_dir();
        $name = 'paste-' . time() . '.png';
        $path = $dir['path'] . '/' . $name;
        $url = $dir['url'] . '/' . $name;
        if (file_put_contents($path, $data)) {
            $id = wp_insert_attachment(array('post_mime_type' => 'image/png', 'post_title' => $name, 'post_status' => 'inherit'), $path);
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            wp_update_attachment_metadata($id, wp_generate_attachment_metadata($id, $path));
            wp_send_json_success(array('url' => $url));
        } else {
            wp_send_json_error();
        }
    }

    public function save_article() {
        check_ajax_referer('save_article_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error();
            return;
        }
        global $wpdb;
        $data = array(
            'title' => sanitize_text_field($_POST['title']),
            'content' => wp_kses_post($_POST['content'])
        );
        if (isset($_POST['article_id']) && !empty($_POST['article_id'])) {
            $wpdb->update($wpdb->prefix . 'paste_articles', $data, array('id' => intval($_POST['article_id'])));
        } else {
            $wpdb->insert($wpdb->prefix . 'paste_articles', $data);
        }
        wp_send_json_success();
    }
}

new ImagePasteCKEditor();

// Shortcode - כפונקציה נפרדת (לא anonymous)
function paste_articles_shortcode_handler($atts) {
    global $wpdb;
    $atts = shortcode_atts(array('limit' => -1), $atts);
    $limit_sql = '';
    if ($atts['limit'] != -1) {
        $limit_sql = ' LIMIT ' . intval($atts['limit']);
    }
    $articles = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}paste_articles ORDER BY created_at DESC{$limit_sql}");

    ob_start();
    if (isset($_GET['article_id'])) {
        $article = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}paste_articles WHERE id=%d", intval($_GET['article_id'])));
        if ($article) {
            echo '<h1>' . esc_html($article->title) . '</h1>';
            echo '<div>' . wp_kses_post($article->content) . '</div>';
            echo '<p><a href="' . esc_url(remove_query_arg('article_id')) . '">חזרה</a></p>';
        }
    } else {
        echo '<ul>';
        if (!empty($articles)) {
            foreach ($articles as $art) {
                echo '<li><a href="' . esc_url(add_query_arg('article_id', $art->id)) . '">' . esc_html($art->title) . '</a></li>';
            }
        }
        echo '</ul>';
    }
    return ob_get_clean();
}

add_shortcode('paste_articles', 'paste_articles_shortcode_handler');
?>