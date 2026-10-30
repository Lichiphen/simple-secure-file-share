<?php
/**
 * Plugin Name: Simple Secure File Share
 * Plugin URI: https://github.com/Lichiphen/simple-secure-file-share
 * Description: 外部共有用にファイルを安全にアップロードし、専用URLを発行するプラグイン。ファイルは直接アクセスから保護され、Zipダウンロードに対応しています。
 * Version: 4.1.0
 * Author: AI Generator, Direction：Lichiphen（X@Lichiphen）
 * Author URI: https://lichiphen.com
 * License: Lichiphen Proprietary License v1.0
 * License URI: https://github.com/Lichiphen/simple-secure-file-share/blob/main/LICENSE
 * Text Domain: simple-secure-file-share
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * 
 * @package SimpleSecureFileShare
 * @copyright 2025-2026 Lichiphen. All rights reserved.
 * 
 * ============================================================================
 * LICHIPHEN PROPRIETARY LICENSE v1.0
 * ============================================================================
 * 
 * Copyright (c) 2025-2026 Lichiphen. All rights reserved.
 * Website: https://lichiphen.com | X (Twitter): https://x.com/Lichiphen
 * 
 * このソフトウェアおよび関連ドキュメントファイル（以下「本ソフトウェア」）の
 * 使用、コピー、変更、統合、公開、配布、サブライセンス、販売に関して、
 * 以下の条件に従うことを条件として、無償で許可されます：
 * 
 * 1. 上記の著作権表示およびこの許諾表示を、本ソフトウェアのすべてのコピー
 *    または重要な部分に含めること。
 * 
 * 2. 本ソフトウェアを再配布する場合、元の著作権者（Lichiphen）のクレジットを
 *    明確に表示すること。
 * 
 * 3. 商用利用する場合は、元の著作権者への通知は不要ですが、
 *    著作権表示の削除や改変は禁止とします。
 * 
 * 本ソフトウェアは「現状のまま」提供され、明示または黙示を問わず、
 * 商品性、特定目的への適合性、権利侵害の不存在の保証を含め、
 * いかなる保証もありません。
 * 
 * いかなる場合も、著作権者または権利者は、契約行為、不法行為、
 * またはその他の行為であるかを問わず、本ソフトウェアまたは
 * 本ソフトウェアの使用もしくはその他の取引に起因または関連して
 * 生じた請求、損害、その他の責任について責任を負いません。
 * 
 * ============================================================================
 */

if (!defined('ABSPATH')) {
    exit;
}

class Simple_File_Share {

    const VERSION = '4.1.0';

    /** 共有パスワードの試行上限（IP+共有ごと / 15分） */
    const MAX_AUTH_ATTEMPTS = 10;
    const AUTH_LOCKOUT_SECONDS = 900;

    /** 1共有あたりの最大ファイル数 */
    const MAX_FILES_PER_SHARE = 100;

    /** プレビュー配信を許可する画像MIME（SVGはスクリプトを含み得るため除外） */
    const PREVIEW_MIME_TYPES = array('image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp', 'image/avif');

    private $upload_dir;
    private $legacy_upload_dir;
    private $share_slug = 'sfs-share';

    public function __construct() {
        // 旧バージョンの保存先（プラグインフォルダ内。プラグイン更新時に削除されるため移行対象）
        $this->legacy_upload_dir = plugin_dir_path(__FILE__) . 'protected-uploads/';

        // フックの登録
        register_activation_hook(__FILE__, array($this, 'activate'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('init', array($this, 'register_post_type'));
        add_action('init', array($this, 'add_rewrite_rules'));
        add_filter('query_vars', array($this, 'add_query_vars'));
        add_action('template_redirect', array($this, 'handle_frontend_view'));
        
        // 通常のPOST送信ハンドラ (JS無効時等のフォールバック)
        add_action('admin_post_sfs_handle_upload', array($this, 'handle_upload_process'));
        // 削除ハンドラ
        add_action('admin_post_sfs_delete_share', array($this, 'handle_delete_share'));
        
        // AJAXアップロードハンドラ
        add_action('wp_ajax_sfs_async_upload', array($this, 'handle_async_upload'));
        
        // 高度な設定用AJAXハンドラ
        add_action('admin_post_sfs_cleanup_orphan', array($this, 'handle_cleanup_orphan'));
        add_action('admin_post_sfs_cleanup_db', array($this, 'handle_cleanup_db'));
        add_action('admin_post_sfs_update_password', array($this, 'handle_update_password_post'));
        
        // 多言語対応
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // 管理画面用アセット読み込み
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // 個別ファイル削除AJAXハンドラ
        add_action('wp_ajax_sfs_delete_single_file', array($this, 'handle_delete_single_file'));
        
        // パスワード再設定AJAXハンドラ
        add_action('wp_ajax_sfs_update_password', array($this, 'handle_update_password'));

        // 画像取得用AJAXハンドラ（ログイン有無に関わらずアクセス可能だがトークン検証必須）
        add_action('wp_ajax_sfs_get_image', array($this, 'handle_get_image'));
        add_action('wp_ajax_nopriv_sfs_get_image', array($this, 'handle_get_image'));

        // 保存先の保護ファイル確認と旧保存先からの移行
        add_action('admin_init', array($this, 'maybe_prepare_storage'));
    }

    // =========================================================================
    // セキュリティ関連ヘルパー
    // =========================================================================

    /**
     * アップロード保存先 (wp-content/uploads/sfs-protected/)
     * フィルター 'sfs_storage_dir' でWeb公開領域外のパスへ変更可能
     */
    private function get_upload_dir() {
        if ($this->upload_dir === null) {
            $uploads = wp_upload_dir(null, false);
            $dir = trailingslashit($uploads['basedir']) . 'sfs-protected/';
            $this->upload_dir = trailingslashit(apply_filters('sfs_storage_dir', $dir));
        }
        return $this->upload_dir;
    }

    /**
     * 保存先ディレクトリを作成し、直接アクセス拒否ファイルを配置
     */
    private function ensure_storage_protection() {
        $dir = $this->get_upload_dir();
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
            @chmod($dir, 0750);
        }

        $htaccess = implode("\n", array(
            '# Simple Secure File Share: deny all direct access',
            '<IfModule mod_authz_core.c>',
            '    Require all denied',
            '</IfModule>',
            '<IfModule !mod_authz_core.c>',
            '    Order Deny,Allow',
            '    Deny from all',
            '</IfModule>',
            'Options -Indexes -ExecCGI',
            '<IfModule mod_php.c>',
            '    php_flag engine off',
            '</IfModule>',
            '<IfModule mod_php7.c>',
            '    php_flag engine off',
            '</IfModule>',
            'RemoveHandler .php .phtml .php3 .php4 .php5 .php7 .php8 .phar .cgi .pl .py',
            '',
        ));
        $files = array(
            '.htaccess' => $htaccess,
            'index.php' => "<?php\n// Silence is golden.\n",
            'web.config' => '<?xml version="1.0" encoding="UTF-8"?><configuration><system.webServer><authorization><deny users="*" /></authorization></system.webServer></configuration>',
        );
        foreach ($files as $name => $content) {
            $path = $dir . $name;
            if (!file_exists($path) || ($name === '.htaccess' && strpos((string) file_get_contents($path), 'Require all denied') === false)) {
                @file_put_contents($path, $content);
            }
        }
    }

    /**
     * 管理画面表示時: 保存先の保護と旧保存先からの移行
     */
    public function maybe_prepare_storage() {
        if (!current_user_can('manage_options')) {
            return;
        }
        $this->ensure_storage_protection();

        $legacy = $this->legacy_upload_dir;
        if (!is_dir($legacy) || untrailingslashit($legacy) === untrailingslashit($this->get_upload_dir())) {
            return;
        }

        $dirs = glob($legacy . '*', GLOB_ONLYDIR) ?: array();
        foreach ($dirs as $dir) {
            $name = basename($dir);
            $target = $this->get_upload_dir() . $name;
            if ($this->is_valid_token($name) && !file_exists($target) && !is_link($dir)) {
                @rename($dir, $target);
            }
        }

        // 移行完了後、旧フォルダが空なら削除
        $remaining = array_diff(scandir($legacy) ?: array(), array('.', '..', '.htaccess', 'index.php'));
        if (empty($remaining)) {
            @unlink($legacy . '.htaccess');
            @unlink($legacy . 'index.php');
            @rmdir($legacy);
        }
    }

    /**
     * 共有トークン形式 (32桁hex)
     */
    private function is_valid_token($token) {
        return is_string($token) && (bool) preg_match('/^[a-f0-9]{32}$/', $token);
    }

    /**
     * 共有フォルダ内のファイル実パスを安全に解決（パストラバーサル対策）
     */
    private function resolve_share_file($token, $system_name) {
        if (!$this->is_valid_token($token) || !is_string($system_name) || $system_name === ''
            || $system_name[0] === '.' || strpbrk($system_name, "/\\\0") !== false || basename($system_name) !== $system_name) {
            return null;
        }
        $base = realpath($this->get_upload_dir() . $token);
        $path = realpath($this->get_upload_dir() . $token . '/' . $system_name);
        if ($base === false || $path === false || !is_file($path)) {
            return null;
        }
        return strpos($path, trailingslashit($base)) === 0 ? $path : null;
    }

    /**
     * sfs_share 投稿のみ取得（他の投稿IDを操作させない）
     */
    private function get_share($share_id) {
        $post = get_post((int) $share_id);
        return ($post && $post->post_type === 'sfs_share') ? $post : null;
    }

    /**
     * トークンから公開中の共有を取得
     */
    private function get_share_by_token($token) {
        if (!$this->is_valid_token($token)) {
            return null;
        }
        $shares = get_posts(array(
            'post_type' => 'sfs_share',
            'meta_key' => 'sfs_token',
            'meta_value' => $token,
            'posts_per_page' => 1,
            'post_status' => 'publish',
        ));
        return empty($shares) ? null : $shares[0];
    }

    /**
     * 表示用ファイル名・タイトルから制御文字/双方向制御文字を除去
     */
    private function clean_text($text, $max_length = 200) {
        $text = wp_check_invalid_utf8((string) $text, true);
        $text = preg_replace('/[\p{Cc}\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', $text);
        $text = trim((string) $text);
        return function_exists('mb_substr') ? mb_substr($text, 0, $max_length) : substr($text, 0, $max_length);
    }

    private function clean_original_name($name) {
        $name = preg_replace('#^.*[/\\\\]#', '', (string) $name);
        $name = ltrim($this->clean_text($name, 200), '.');
        return $name !== '' ? $name : 'file_' . gmdate('Ymd_His');
    }

    /**
     * 共有パスワードのハッシュ化 (bcrypt / PASSWORD_DEFAULT)
     */
    private function hash_share_password($password) {
        return password_hash(trim((string) $password), PASSWORD_DEFAULT);
    }

    /**
     * 共有パスワードの検証。旧形式(phpass)のハッシュは成功時に再ハッシュする
     */
    private function verify_share_password($post_id, $password, $stored_hash) {
        $password = (string) $password;
        if (strlen($password) > 1024 || !is_string($stored_hash) || $stored_hash === '') {
            return false;
        }

        if (strpos($stored_hash, '$P$') === 0 || strpos($stored_hash, '$H$') === 0) {
            require_once ABSPATH . WPINC . '/class-phpass.php';
            $hasher = new PasswordHash(8, true);
            $candidates = array_unique(array(trim($password), $password));
            foreach ($candidates as $candidate) {
                if ($hasher->CheckPassword($candidate, $stored_hash)) {
                    update_post_meta($post_id, 'sfs_password_hash', $this->hash_share_password($candidate));
                    return true;
                }
            }
            return false;
        }

        if (!password_verify(trim($password), $stored_hash)) {
            return false;
        }
        if (password_needs_rehash($stored_hash, PASSWORD_DEFAULT)) {
            update_post_meta($post_id, 'sfs_password_hash', $this->hash_share_password($password));
        }
        return true;
    }

    /**
     * 有効期限付きHMAC署名 "expires.signature"
     */
    private function sign($purpose, $expires) {
        return $expires . '.' . hash_hmac('sha256', $purpose . '|' . $expires, wp_salt('auth'));
    }

    private function verify_signature($purpose, $value) {
        if (!is_string($value) || !preg_match('/^(\d{1,12})\.([a-f0-9]{64})$/', $value, $m)) {
            return false;
        }
        if ((int) $m[1] < time()) {
            return false;
        }
        return hash_equals(hash_hmac('sha256', $purpose . '|' . $m[1], wp_salt('auth')), $m[2]);
    }

    /**
     * 認証Cookieの署名対象（パスワードハッシュを含め、変更時に失効させる）
     */
    private function auth_purpose($post_id, $token, $password_hash) {
        return 'share-auth|' . (int) $post_id . '|' . $token . '|' . $password_hash;
    }

    private function is_share_authenticated($post_id, $token, $password_hash) {
        $cookie = isset($_COOKIE['sfs_auth_' . $token]) ? wp_unslash($_COOKIE['sfs_auth_' . $token]) : null;
        return $this->verify_signature($this->auth_purpose($post_id, $token, $password_hash), $cookie);
    }

    private function set_share_auth_cookie($post_id, $token, $password_hash) {
        $expires = time() + HOUR_IN_SECONDS;
        setcookie('sfs_auth_' . $token, $this->sign($this->auth_purpose($post_id, $token, $password_hash), $expires), array(
            'expires'  => $expires,
            'path'     => COOKIEPATH ? COOKIEPATH : '/',
            'domain'   => COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ));
    }

    /**
     * 直リンク防止用のダウンロード署名（10分単位で丸めて約1時間有効）
     */
    private function download_token($post_id, $token) {
        $expires = (int) (ceil(time() / 600) * 600) + HOUR_IN_SECONDS;
        return $this->sign('download|' . (int) $post_id . '|' . $token, $expires);
    }

    private function verify_download_token($post_id, $token, $value) {
        return $this->verify_signature('download|' . (int) $post_id . '|' . $token, $value);
    }

    /**
     * 総当たり対策（transientで失敗回数を記録）
     */
    private function rate_limit_keys($token) {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
        return array(
            'share' => 'sfs_rl_' . md5($ip . '|' . $token),
            'ip'    => 'sfs_rl_' . md5($ip),
        );
    }

    private function is_auth_locked($token) {
        $keys = $this->rate_limit_keys($token);
        return (int) get_transient($keys['share']) >= self::MAX_AUTH_ATTEMPTS
            || (int) get_transient($keys['ip']) >= self::MAX_AUTH_ATTEMPTS * 5;
    }

    private function register_auth_failure($token) {
        foreach ($this->rate_limit_keys($token) as $key) {
            set_transient($key, (int) get_transient($key) + 1, self::AUTH_LOCKOUT_SECONDS);
        }
    }

    private function clear_auth_failures($token) {
        $keys = $this->rate_limit_keys($token);
        delete_transient($keys['share']);
    }

    /**
     * 共有ページ（独自HTML）用のセキュリティヘッダー
     */
    private function send_frontend_headers() {
        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true); // ページキャッシュ系プラグインに保存させない
        }
        if (headers_sent()) {
            return;
        }
        nocache_headers();
        header('X-Robots-Tag: noindex, nofollow');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: same-origin');
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self' data:; connect-src 'self'; form-action 'self'; frame-ancestors 'self'; base-uri 'none'; object-src 'none'");
    }

    /**
     * Content-Disposition ヘッダー値
     */
    private function content_disposition($filename, $type = 'attachment') {
        $filename = $this->clean_text($filename, 200);
        $fallback = str_replace(array('"', '\\', ';'), '', (string) preg_replace('/[^\x20-\x7e]/', '_', $filename));
        if ($fallback === '') {
            $fallback = 'download';
        }
        return $type . '; filename="' . $fallback . '"; filename*=UTF-8\'\'' . rawurlencode($filename);
    }

    /**
     * ファイル送信（常にダウンロード扱い・ブラウザ内で実行させない）
     */
    private function send_file($path, $filename, $content_type = 'application/octet-stream', $disposition = 'attachment') {
        while (ob_get_level()) {
            ob_end_clean();
        }
        nocache_headers();
        header('Content-Description: File Transfer');
        header('Content-Type: ' . $content_type);
        header('Content-Disposition: ' . $this->content_disposition($filename, $disposition));
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
        header('X-Robots-Tag: noindex, nofollow');
        header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
        readfile($path);
        exit;
    }

    /**
     * ディレクトリを再帰的に削除（シンボリックリンクは辿らない）
     */
    private function delete_share_directory($token) {
        if (!is_string($token) || $token === '' || strpbrk($token, "/\\\0.") !== false) {
            return;
        }
        $dir = $this->get_upload_dir() . $token;
        if (is_dir($dir) && !is_link($dir)) {
            $this->delete_directory($dir);
        }
    }
    
    /**
     * 管理画面用アセットの読み込み
     */
    public function enqueue_admin_assets($hook) {
        // プラグインページのみで読み込み
        if (strpos($hook, 'simple-file-share') === false) {
            return;
        }
        
        // Tailwind（ローカルビルド。外部CDNのスクリプトは管理画面で実行しない）
        wp_enqueue_style(
            'sfs-tailwind',
            plugin_dir_url(__FILE__) . 'assets/css/tailwind.min.css',
            array(),
            self::VERSION
        );

        // カスタムCSS
        wp_enqueue_style(
            'sfs-admin-style',
            plugin_dir_url(__FILE__) . 'assets/css/style.css',
            array('sfs-tailwind'),
            self::VERSION
        );
        
        // カスタムJS
        wp_enqueue_script(
            'sfs-admin-script',
            plugin_dir_url(__FILE__) . 'assets/js/app.js',
            array(),
            self::VERSION,
            true
        );
        
        // 翻訳文字列をJSに渡す
        wp_localize_script('sfs-admin-script', 'sfsI18n', array(
            'urlCopied' => __('URL copied!', 'simple-secure-file-share'),
            'copyFailed' => __('Copy failed', 'simple-secure-file-share'),
            'passwordCopied' => __('Password copied!', 'simple-secure-file-share'),
            'hidePassword' => __('Hide password', 'simple-secure-file-share'),
            'showPassword' => __('Show password', 'simple-secure-file-share'),
            'delete' => __('Delete', 'simple-secure-file-share'),
            'selectFiles' => __('Please select at least one file.', 'simple-secure-file-share'),
            'enterTitle' => __('Please enter a title.', 'simple-secure-file-share'),
            'enterPassword' => __('Please enter a password.', 'simple-secure-file-share'),
            'uploading' => __('Uploading...', 'simple-secure-file-share'),
            'uploadButton' => __('Upload and Create Share Link', 'simple-secure-file-share'),
            'uploadComplete' => __('Upload complete!', 'simple-secure-file-share'),
            'error' => __('Error: ', 'simple-secure-file-share'),
            'parseError' => __('Failed to parse server response.', 'simple-secure-file-share'),
            'networkError' => __('Network error occurred.', 'simple-secure-file-share'),
            'confirmDelete' => __('Are you sure you want to delete this file?', 'simple-secure-file-share'),
        ));
        
        // AJAX URL
        wp_localize_script('sfs-admin-script', 'sfsAjax', array(
            'url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sfs_ajax_nonce')
        ));
    }
    
    /**
     * 個別ファイル削除ハンドラ
     */
    public function handle_delete_single_file() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'simple-secure-file-share'), 403);
        }
        
        check_ajax_referer('sfs_ajax_nonce', 'nonce');
        
        $share_id = isset($_POST['share_id']) ? absint($_POST['share_id']) : 0;
        $file_name = isset($_POST['file_name']) ? (string) wp_unslash($_POST['file_name']) : '';
        
        $share = $this->get_share($share_id);
        $token = $share ? get_post_meta($share_id, 'sfs_token', true) : '';
        $files = $share ? get_post_meta($share_id, 'sfs_files', true) : array();
        
        if (!$token || !is_array($files) || empty($files)) {
            wp_send_json_error(__('Share not found.', 'simple-secure-file-share'));
        }
        
        // 登録済みファイルと完全一致するもののみ削除
        $new_files = array();
        $file_deleted = false;
        
        foreach ($files as $file) {
            if (!$file_deleted && isset($file['system_name']) && hash_equals((string) $file['system_name'], $file_name)) {
                $file_path = $this->resolve_share_file($token, $file['system_name']);
                if ($file_path) {
                    @unlink($file_path);
                }
                $file_deleted = true;
            } else {
                $new_files[] = $file;
            }
        }
        
        if (!$file_deleted) {
            wp_send_json_error(__('File not found.', 'simple-secure-file-share'));
        }
        
        // ファイルリストを更新
        update_post_meta($share_id, 'sfs_files', $new_files);
        
        // ファイルが0になった場合は共有自体を削除
        if (empty($new_files)) {
            $this->delete_share_directory($token);
            wp_delete_post($share_id, true);
            wp_send_json_success(array('deleted_share' => true));
        }
        
        wp_send_json_success(array('remaining_files' => count($new_files)));
    }
    
    /**
     * パスワード更新ハンドラ
     */
    public function handle_update_password() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied.', 'simple-secure-file-share'), 403);
        }
        
        check_ajax_referer('sfs_ajax_nonce', 'nonce');
        
        $share_id = isset($_POST['share_id']) ? absint($_POST['share_id']) : 0;
        $new_password = isset($_POST['password']) ? (string) wp_unslash($_POST['password']) : '';
        $remove_password = isset($_POST['remove_password']) && $_POST['remove_password'] === 'true';
        
        if (!$this->get_share($share_id) || !get_post_meta($share_id, 'sfs_token', true)) {
            wp_send_json_error(__('Share not found.', 'simple-secure-file-share'));
        }
        
        if ($remove_password) {
            delete_post_meta($share_id, 'sfs_password_hash');
            wp_send_json_success(array('message' => __('Password removed.', 'simple-secure-file-share')));
        } else if (trim($new_password) !== '' && strlen($new_password) <= 1024) {
            update_post_meta($share_id, 'sfs_password_hash', $this->hash_share_password($new_password));
            wp_send_json_success(array('message' => __('Password updated.', 'simple-secure-file-share')));
        } else {
            wp_send_json_error(__('Password is required.', 'simple-secure-file-share'));
        }
    }
    
    /**
     * テキストドメインの読み込み（多言語対応）
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'simple-secure-file-share',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages/'
        );
    }

    /**
     * プラグイン有効化時の処理
     */
    public function activate() {
        // アップロードディレクトリの作成と保護（.htaccess / index.php / web.config）
        $this->ensure_storage_protection();

        $this->add_rewrite_rules();
        flush_rewrite_rules();
    }

    /**
     * カスタム投稿タイプ
     */
    public function register_post_type() {
        register_post_type('sfs_share', array(
            'public' => false,
            'show_ui' => false,
            'label'  => 'File Shares',
            'supports' => array('title', 'custom-fields')
        ));
    }

    /**
     * リライトルール
     */
    public function add_rewrite_rules() {
        add_rewrite_rule(
            '^' . $this->share_slug . '/([a-zA-Z0-9]+)/?$',
            'index.php?sfs_token=$matches[1]',
            'top'
        );
    }

    public function add_query_vars($vars) {
        $vars[] = 'sfs_token';
        return $vars;
    }

    /**
     * 管理メニュー
     */
    public function add_admin_menu() {
        add_menu_page(
            __('File Share', 'simple-secure-file-share'),
            __('File Share', 'simple-secure-file-share'),
            'manage_options',
            'simple-file-share',
            array($this, 'render_admin_page'),
            'dashicons-share',
            25
        );
        
        // サブメニュー: 高度な設定
        add_submenu_page(
            'simple-file-share',
            __('Advanced Settings', 'simple-secure-file-share'),
            __('Advanced Settings', 'simple-secure-file-share'),
            'manage_options',
            'simple-file-share-advanced',
            array($this, 'render_advanced_page')
        );
        
        // サブメニュー: 使い方
        add_submenu_page(
            'simple-file-share',
            __('How to Use', 'simple-secure-file-share'),
            __('How to Use', 'simple-secure-file-share'),
            'manage_options',
            'simple-file-share-howto',
            array($this, 'render_howto_page')
        );
    }

    /**
     * 管理画面レンダリング
     */
    public function render_admin_page() {
        if (!current_user_can('manage_options')) return;
        // ソートパラメータを取得
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'date';
        $order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'desc';
        $valid_orderby = array('title', 'date');
        $valid_order = array('asc', 'desc');
        if (!in_array($orderby, $valid_orderby)) $orderby = 'date';
        if (!in_array($order, $valid_order)) $order = 'desc';
        
        $shares = get_posts(array(
            'post_type' => 'sfs_share',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => $orderby,
            'order' => $order,
        ));
        
        // メタフィールドでソートする場合は手動ソート / Sort by meta field if needed
        $sort_by_meta = isset($_GET['orderby']) && in_array($_GET['orderby'], array('status', 'downloads', 'files'));
        if ($sort_by_meta && !empty($shares)) {
            $sort_field = sanitize_text_field($_GET['orderby']);
            usort($shares, function($a, $b) use ($sort_field, $order) {
                if ($sort_field === 'downloads') {
                    $val_a = intval(get_post_meta($a->ID, 'sfs_download_count', true));
                    $val_b = intval(get_post_meta($b->ID, 'sfs_download_count', true));
                } elseif ($sort_field === 'files') {
                    $files_a = get_post_meta($a->ID, 'sfs_files', true);
                    $files_b = get_post_meta($b->ID, 'sfs_files', true);
                    $val_a = is_array($files_a) ? count($files_a) : 0;
                    $val_b = is_array($files_b) ? count($files_b) : 0;
                } else { // status
                    $val_a = !empty(get_post_meta($a->ID, 'sfs_password_hash', true)) ? 1 : 0;
                    $val_b = !empty(get_post_meta($b->ID, 'sfs_password_hash', true)) ? 1 : 0;
                }
                $result = $val_a <=> $val_b;
                return $order === 'desc' ? -$result : $result;
            });
        }

        ?>
        <div class="wrap">
            <style>
                .sfs-app h1, .sfs-app h2, .sfs-app h3 { margin-bottom: 0.5em; font-weight: bold; }
                .sfs-app input[type=text], .sfs-app input[type=password] { border: 1px solid #ccc; padding: 8px; width: 100%; border-radius: 4px; color: #333; background: #fff; }
                .sfs-drag-over { background-color: #eff6ff !important; border-color: #3b82f6 !important; }
                
                /* パスワードマスク用CSS (text-security) */
                .sfs-masked {
                    -webkit-text-security: disc;
                    text-security: disc;
                    font-family: text-security-disc, sans-serif;
                    color: #333 !important;
                }
                
                /* ファイルリスト表示制御 / File list display control */
                .sfs-hidden { display: none !important; }
                
                /* テーブルラッパー / Table wrapper - 600px未満でのみ横スクロール */
                .sfs-table-wrapper { margin: 0 -1.5rem; padding: 0 1.5rem; }
                @media (max-width: 599px) {
                    .sfs-table-wrapper { overflow-x: auto; }
                    .sfs-table-wrapper table { min-width: 600px; }
                }
                
                /* ファイルリストアイテム */
                .sfs-file-item {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    padding: 0.75rem;
                    background: #f9fafb;
                    border: 1px solid #e5e7eb;
                    border-radius: 0.5rem;
                    margin-bottom: 0.5rem;
                }
                .sfs-file-info {
                    display: flex;
                    align-items: center;
                    gap: 0.75rem;
                    min-width: 0;
                    flex: 1;
                }
                .sfs-file-name {
                    font-weight: 500;
                    color: #374151;
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                }
                .sfs-file-size {
                    font-size: 0.75rem;
                    color: #9ca3af;
                }
                .sfs-file-remove {
                    background: none;
                    border: none;
                    color: #ef4444;
                    cursor: pointer;
                    padding: 0.25rem;
                }
                .sfs-file-remove:hover {
                    color: #dc2626;
                }
                .sfs-truncate {
                    overflow: hidden;
                    text-overflow: ellipsis;
                    white-space: nowrap;
                }
                
                /* 画像プレビュー */
                .sfs-file-preview-img {
                    width: 40px;
                    height: 40px;
                    object-fit: cover;
                    border-radius: 4px;
                    border: 1px solid #e5e7eb;
                }
                
                /* トースト通知アニメーション（画面中央） */
                @keyframes sfs-toast-in { from { transform: translate(-50%, -40%); opacity: 0; } to { transform: translate(-50%, -50%); opacity: 1; } }
                @keyframes sfs-toast-out { from { transform: translate(-50%, -50%); opacity: 1; } to { transform: translate(-50%, -60%); opacity: 0; } }
                
                .sfs-toast {
                    position: fixed;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    background-color: #333;
                    color: white;
                    padding: 16px 32px;
                    border-radius: 8px;
                    font-weight: bold;
                    font-size: 16px;
                    box-shadow: 0 10px 25px rgba(0,0,0,0.2);
                    z-index: 99999;
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    animation: sfs-toast-in 0.3s ease-out forwards;
                    pointer-events: none;
                }
                .sfs-toast.hiding {
                    animation: sfs-toast-out 0.3s ease-in forwards;
                }
                
                /* ツールチップ的なコピー表示 */
                .copy-hint {
                    opacity: 0;
                    transition: opacity 0.2s;
                }
                .group:hover .copy-hint {
                    opacity: 1;
                }
            </style>

            <div class="sfs-app p-6 max-w-5xl mx-auto bg-white shadow-lg rounded-lg mt-5 relative">
                <h1 class="text-2xl mb-6 text-gray-800 flex items-center gap-2">
                    <span class="dashicons dashicons-share text-blue-500" style="font-size:28px;width:28px;height:28px;"></span> <?php _e('Secure File Sharing System', 'simple-secure-file-share'); ?>
                </h1>

                <!-- アップロードフォーム -->
                <div class="bg-gray-50 p-6 rounded-lg border border-gray-200 mb-8">
                    <h2 class="text-xl mb-4 text-gray-700"><?php _e('Create New Share', 'simple-secure-file-share'); ?></h2>
                    
                    <form action="<?php echo admin_url('admin-post.php'); ?>" method="post" enctype="multipart/form-data" class="space-y-4" id="sfs-upload-form" autocomplete="off">
                        
                        <input type="hidden" name="action" value="sfs_handle_upload">
                        <?php wp_nonce_field('sfs_upload_nonce', 'sfs_nonce'); ?>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1"><?php _e('Share Title (for management)', 'simple-secure-file-share'); ?></label>
                            <input type="text" name="share_title" id="sfs-title-input" required placeholder="<?php esc_attr_e('Example: Documents for Mr./Ms. XX', 'simple-secure-file-share'); ?>" class="w-full">
                        </div>

                        <!-- パスワード設定エリア -->
                        <div class="bg-white p-4 rounded border border-gray-200 shadow-sm">
                            <label class="block text-sm font-medium text-gray-700 mb-2"><?php _e('Password Protection', 'simple-secure-file-share'); ?></label>
                            <div class="flex items-center space-x-4 mb-2">
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="radio" name="enable_password" value="0" class="form-radio text-blue-600" checked onchange="togglePasswordInput(false)">
                                    <span class="ml-2"><?php _e('None', 'simple-secure-file-share'); ?></span>
                                </label>
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="radio" name="enable_password" value="1" class="form-radio text-blue-600" onchange="togglePasswordInput(true)">
                                    <span class="ml-2"><?php _e('Yes', 'simple-secure-file-share'); ?></span>
                                </label>
                            </div>
                            
                            <div id="sfs-password-container" class="hidden mt-3">
                                <div class="flex flex-col md:flex-row md:items-start gap-3">
                                    <div class="relative w-full md:w-1/2">
                                        <!-- type="text" で実装し、CSS(.sfs-masked)で隠す -->
                                        <!-- autocomplete="off" を指定し、ブラウザの介入を最小限に -->
                                        <input type="text" name="share_password" id="sfs-password-input" 
                                            placeholder="<?php esc_attr_e('Enter password', 'simple-secure-file-share'); ?>" 
                                            class="sfs-masked w-full pr-10" 
                                            autocomplete="off"
                                            autocorrect="off" 
                                            autocapitalize="off" 
                                            spellcheck="false">
                                        
                                        <button type="button" id="sfs-toggle-password" class="absolute inset-y-0 right-0 px-3 flex items-center text-gray-400 hover:text-gray-600 cursor-pointer focus:outline-none" title="<?php esc_attr_e('Show password', 'simple-secure-file-share'); ?>">
                                            <span class="dashicons dashicons-visibility" style="margin-top:2px;"></span>
                                        </button>
                                    </div>
                                    
                                    <button type="button" id="sfs-generate-password" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition">
                                        <span class="dashicons dashicons-randomize mr-1" style="line-height:1.5;"></span> <?php _e('Auto Generate', 'simple-secure-file-share'); ?>
                                    </button>
                                </div>
                                <p class="text-xs text-gray-500 mt-2"><?php _e('* Password will be copied to clipboard when you click the auto-generate button.', 'simple-secure-file-share'); ?></p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1"><?php _e('File Selection', 'simple-secure-file-share'); ?></label>
                            <div id="sfs-drop-zone" class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center bg-white hover:bg-gray-50 transition cursor-pointer relative group">
                                <div class="pointer-events-none">
                                    <span class="dashicons dashicons-cloud-upload text-gray-400 mb-2" style="font-size: 48px; width: 48px; height: 48px;"></span>
                                    <p class="text-gray-600 font-medium text-lg mt-2"><?php _e('Drag and drop files here', 'simple-secure-file-share'); ?></p>
                                    <p class="text-sm text-gray-500 mt-1"><?php _e('or', 'simple-secure-file-share'); ?> <span class="text-blue-600 font-bold underline decoration-blue-300 decoration-2 underline-offset-2"><?php _e('click to select files', 'simple-secure-file-share'); ?></span></p>
                                </div>
                                <input type="file" id="sfs-file-trigger" multiple class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
                            </div>
                            <input type="file" name="files[]" id="sfs-submission-input" multiple class="hidden">

                            <!-- ファイルリスト -->
                            <div id="sfs-file-list-container" class="mt-4 sfs-hidden">
                                <div class="flex justify-between items-center mb-2">
                                    <p class="text-sm font-bold text-gray-700"><?php _e('Files waiting to upload:', 'simple-secure-file-share'); ?></p>
                                    <button type="button" id="sfs-clear-all" class="text-xs text-red-500 hover:underline"><?php _e('Clear all', 'simple-secure-file-share'); ?></button>
                                </div>
                                <ul id="sfs-file-list" class="space-y-2 max-h-60 overflow-y-auto pr-2"></ul>
                            </div>
                        </div>

                        <!-- プログレスバー -->
                        <div id="sfs-progress-wrapper" class="sfs-hidden mt-4">
                            <div class="flex justify-between text-xs font-semibold text-gray-600 mb-1">
                                <span><?php _e('Uploading...', 'simple-secure-file-share'); ?></span>
                                <span id="sfs-progress-text">0%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2.5 overflow-hidden">
                                <div id="sfs-progress-bar" class="bg-blue-600 h-2.5 rounded-full transition-all duration-200" style="width: 0%"></div>
                            </div>
                        </div>

                        <button type="submit" id="sfs-submit-btn" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded shadow transition w-full md:w-auto disabled:opacity-50 disabled:cursor-not-allowed">
                            <?php _e('Upload and Create Share Link', 'simple-secure-file-share'); ?>
                        </button>
                    </form>
                </div>

                <!-- 共有リスト -->
                <h2 class="text-xl mb-4 text-gray-700"><?php _e('Active Share Links', 'simple-secure-file-share'); ?></h2>
                <?php if (empty($shares)): ?>
                    <p class="text-gray-500"><?php _e('No shared files yet.', 'simple-secure-file-share'); ?></p>
                <?php else: ?>
                    <div class="sfs-table-wrapper">
                        <table class="w-full text-sm text-left text-gray-500 border-collapse">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-100">
                                <tr>
                                    <?php 
                                    $current_url = admin_url('admin.php?page=simple-file-share');
                                    ?>
                                    <th class="px-4 py-3 border-b" style="min-width: 100px;">
                                        <a href="<?php echo esc_url(add_query_arg(array('orderby' => 'title', 'order' => ($orderby === 'title' && $order === 'asc') ? 'desc' : 'asc'), $current_url)); ?>" class="flex items-center hover:text-blue-600">
                                            <?php _e('Title', 'simple-secure-file-share'); ?>
                                            <?php if ($orderby === 'title'): ?>
                                                <span class="ml-1"><?php echo $order === 'asc' ? '▲' : '▼'; ?></span>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th class="px-4 py-3 border-b whitespace-nowrap">
                                        <a href="<?php echo esc_url(add_query_arg(array('orderby' => 'status', 'order' => ($orderby === 'status' && $order === 'asc') ? 'desc' : 'asc'), $current_url)); ?>" class="flex items-center hover:text-blue-600">
                                            <?php _e('Status', 'simple-secure-file-share'); ?>
                                            <?php if ($orderby === 'status'): ?>
                                                <span class="ml-1"><?php echo $order === 'asc' ? '▲' : '▼'; ?></span>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th class="px-4 py-3 border-b"><?php _e('Share URL', 'simple-secure-file-share'); ?></th>
                                    <th class="px-4 py-3 border-b whitespace-nowrap text-center">
                                        <a href="<?php echo esc_url(add_query_arg(array('orderby' => 'files', 'order' => ($orderby === 'files' && $order === 'desc') ? 'asc' : 'desc'), $current_url)); ?>" class="flex items-center justify-center hover:text-blue-600">
                                            <?php _e('Files', 'simple-secure-file-share'); ?>
                                            <?php if ($orderby === 'files'): ?>
                                                <span class="ml-1"><?php echo $order === 'asc' ? '▲' : '▼'; ?></span>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th class="px-4 py-3 border-b whitespace-nowrap text-center">
                                        <a href="<?php echo esc_url(add_query_arg(array('orderby' => 'downloads', 'order' => ($orderby === 'downloads' && $order === 'desc') ? 'asc' : 'desc'), $current_url)); ?>" class="flex items-center justify-center hover:text-blue-600">
                                            <?php _e('DL Count', 'simple-secure-file-share'); ?>
                                            <?php if ($orderby === 'downloads'): ?>
                                                <span class="ml-1"><?php echo $order === 'asc' ? '▲' : '▼'; ?></span>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th class="px-4 py-3 border-b whitespace-nowrap">
                                        <a href="<?php echo esc_url(add_query_arg(array('orderby' => 'date', 'order' => ($orderby === 'date' && $order === 'desc') ? 'asc' : 'desc'), $current_url)); ?>" class="flex items-center hover:text-blue-600">
                                            <?php _e('Created', 'simple-secure-file-share'); ?>
                                            <?php if ($orderby === 'date'): ?>
                                                <span class="ml-1"><?php echo $order === 'asc' ? '▲' : '▼'; ?></span>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th class="px-4 py-3 border-b whitespace-nowrap text-center"><?php _e('Actions', 'simple-secure-file-share'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($shares as $share): 
                                    $token = get_post_meta($share->ID, 'sfs_token', true);
                                    $files = get_post_meta($share->ID, 'sfs_files', true);
                                    $has_password = get_post_meta($share->ID, 'sfs_password_hash', true);
                                    $download_count = (int)get_post_meta($share->ID, 'sfs_download_count', true);
                                    $file_count = is_array($files) ? count($files) : 0;
                                    $url = home_url('/' . $this->share_slug . '/' . $token);
                                    
                                    // タイトルを10文字で折り返し表示用に処理
                                    $title_display = esc_html($share->post_title);
                                ?>
                                <tr class="bg-white border-b hover:bg-gray-50">
                                    <td class="px-4 py-4 font-medium text-gray-900" style="max-width: 150px;">
                                        <div class="break-words leading-tight" title="<?php echo esc_attr($share->post_title); ?>">
                                            <?php echo $title_display; ?>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <?php if($has_password): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                <span class="dashicons dashicons-lock mr-1" style="font-size:12px;width:12px;height:12px;"></span><?php _e('Locked', 'simple-secure-file-share'); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800"><?php _e('Public', 'simple-secure-file-share'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="flex items-center gap-2 group">
                                            <!-- クリックでコピー機能付きURL表示 -->
                                            <div class="relative cursor-pointer" onclick="copyToClipboard('<?php echo esc_url($url); ?>')" title="クリックしてURLをコピー">
                                                <input type="text" value="<?php echo esc_url($url); ?>" class="text-xs bg-gray-50 border border-gray-300 rounded p-2 w-48 text-gray-600 cursor-pointer hover:bg-blue-50 hover:border-blue-300 hover:text-blue-700 transition" readonly>
                                                <!-- アイコンバッジ -->
                                                <div class="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-400">
                                                    <span class="dashicons dashicons-clipboard"></span>
                                                </div>
                                            </div>
                                            <!-- コピーボタン -->
                                            <button type="button" onclick="copyToClipboard('<?php echo esc_url($url); ?>')" class="bg-blue-100 hover:bg-blue-200 text-blue-800 text-xs px-3 py-2 rounded border border-blue-200 transition font-bold flex items-center gap-1 whitespace-nowrap">
                                                <span class="dashicons dashicons-admin-links" style="font-size:14px; width:14px; height:14px;"></span>
                                                <?php _e('Copy', 'simple-secure-file-share'); ?>
                                            </button>
                                            
                                            <a href="<?php echo esc_url($url); ?>" target="_blank" class="text-gray-400 hover:text-blue-600 p-2 rounded hover:bg-gray-100 transition" title="リンクを開く">
                                                <span class="dashicons dashicons-external"></span>
                                            </a>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-center"><?php echo $file_count; ?></td>
                                    <td class="px-4 py-4 text-center">
                                        <span class="inline-flex items-center px-2 py-1 rounded bg-blue-50 text-blue-700 font-bold text-xs">
                                            <span class="dashicons dashicons-download mr-1" style="font-size:12px;width:12px;height:12px;"></span>
                                            <?php echo $download_count; ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap"><?php echo get_the_date('Y/m/d H:i', $share->ID); ?></td>
                                    <td class="px-4 py-4">
                                        <div class="flex items-center justify-center gap-3">
                                            <button type="button" onclick="openEditModal(<?php echo $share->ID; ?>, '<?php echo esc_js($share->post_title); ?>')" class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-blue-700 bg-blue-50 hover:bg-blue-100 rounded-md border border-blue-200 transition" title="<?php esc_attr_e('Edit', 'simple-secure-file-share'); ?>">
                                                <span class="dashicons dashicons-edit" style="font-size:14px;width:14px;height:14px;margin-right:4px;"></span>
                                                <?php _e('Edit', 'simple-secure-file-share'); ?>
                                            </button>
                                            <form action="<?php echo admin_url('admin-post.php'); ?>" method="post" onsubmit="return confirm('<?php echo esc_js(__('Are you sure you want to delete? Uploaded files will also be deleted.', 'simple-secure-file-share')); ?>');" class="inline">
                                                <input type="hidden" name="action" value="sfs_delete_share">
                                                <input type="hidden" name="share_id" value="<?php echo $share->ID; ?>">
                                                <?php wp_nonce_field('sfs_delete_nonce', 'sfs_nonce'); ?>
                                                <button type="submit" class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-red-700 bg-red-50 hover:bg-red-100 rounded-md border border-red-200 transition" title="<?php esc_attr_e('Delete', 'simple-secure-file-share'); ?>">
                                                    <span class="dashicons dashicons-trash" style="font-size:14px;width:14px;height:14px;margin-right:4px;"></span>
                                                    <?php _e('Delete', 'simple-secure-file-share'); ?>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- 編集モーダル / Edit Modal -->
            <div id="sfs-edit-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
                <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full">
                    <!-- ヘッダー / Header -->
                    <div class="flex justify-between items-center px-6 py-4 border-b border-gray-200">
                        <h3 class="text-xl font-bold text-gray-900"><?php _e('Edit Share', 'simple-secure-file-share'); ?></h3>
                        <button type="button" onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600 p-1 hover:bg-gray-100 rounded-full transition">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                    
                    <!-- タイトル表示 / Title Display -->
                    <div class="px-6 py-3 bg-gray-50 border-b border-gray-100">
                        <div id="sfs-edit-title" class="text-sm text-gray-600 font-medium"></div>
                    </div>
                    
                    <!-- フォーム / Form -->
                    <form id="sfs-edit-form" action="<?php echo admin_url('admin-post.php'); ?>" method="post">
                        <input type="hidden" name="action" value="sfs_update_password">
                        <input type="hidden" name="share_id" id="sfs-edit-share-id" value="">
                        <?php wp_nonce_field('sfs_update_password_nonce', 'sfs_nonce'); ?>
                        
                        <div class="px-6 py-6 space-y-6">
                            <!-- パスワード設定 / Password Setting -->
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2"><?php _e('Password Setting', 'simple-secure-file-share'); ?></label>
                                <select id="sfs-password-action" name="password_action" onchange="togglePasswordField()" class="w-full border border-gray-300 rounded-lg px-4 py-3 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                                    <option value="keep"><?php _e('Keep current setting', 'simple-secure-file-share'); ?></option>
                                    <option value="set"><?php _e('Set/Change password', 'simple-secure-file-share'); ?></option>
                                    <option value="remove"><?php _e('Remove password', 'simple-secure-file-share'); ?></option>
                                </select>
                            </div>
                            
                            <!-- 新しいパスワード / New Password -->
                            <div id="sfs-new-password-container" class="hidden space-y-3">
                                <label class="block text-sm font-semibold text-gray-700"><?php _e('New Password', 'simple-secure-file-share'); ?></label>
                                <div class="flex gap-2">
                                    <input type="text" name="new_password" id="sfs-new-password" autocomplete="off" class="flex-1 border border-gray-300 rounded-lg px-4 py-3 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition font-mono" placeholder="<?php esc_attr_e('Enter new password', 'simple-secure-file-share'); ?>">
                                    <button type="button" onclick="generateRandomPassword()" class="px-4 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg border border-gray-300 transition whitespace-nowrap" title="<?php esc_attr_e('Generate random password', 'simple-secure-file-share'); ?>">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                    </button>
                                </div>
                                <p class="text-xs text-gray-500"><?php _e('Click the button to generate a random password', 'simple-secure-file-share'); ?></p>
                            </div>
                        </div>
                        
                        <!-- フッター / Footer -->
                        <div class="flex justify-end gap-3 px-6 py-4 bg-gray-50 border-t border-gray-200 rounded-b-xl">
                            <button type="button" onclick="closeEditModal()" class="px-5 py-2.5 text-sm font-medium text-gray-700 bg-white hover:bg-gray-100 rounded-lg border border-gray-300 transition"><?php _e('Cancel', 'simple-secure-file-share'); ?></button>
                            <button type="submit" class="px-5 py-2.5 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg shadow-sm transition"><?php _e('Save Changes', 'simple-secure-file-share'); ?></button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- 完了モーダル -->
            <div id="sfs-success-modal" class="sfs-modal-overlay fixed inset-0 bg-gray-800 bg-opacity-75 hidden z-50 flex items-center justify-center">
                <div class="sfs-modal-content bg-white rounded-lg shadow-xl p-8 max-w-sm w-full text-center">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100 mb-4">
                        <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    </div>
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-2"><?php _e('Upload Complete!', 'simple-secure-file-share'); ?></h3>
                    <p class="text-sm text-gray-500 mb-6"><?php _e('Files have been saved securely and a share link has been created.', 'simple-secure-file-share'); ?></p>
                    <button type="button" onclick="window.location.reload()" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:text-sm">
                        <?php _e('Refresh List', 'simple-secure-file-share'); ?>
                    </button>
                </div>
            </div>
        </div>

        <script>
        // HTMLエスケープ（属性値でも安全なよう引用符も変換）
        function sfsEscapeHtml(text) {
            return String(text ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        }

        // 暗号学的に安全な乱数（偏りなし）
        function sfsSecureRandomInt(max) {
            const limit = Math.floor(0x100000000 / max) * max;
            const buf = new Uint32Array(1);
            do {
                window.crypto.getRandomValues(buf);
            } while (buf[0] >= limit);
            return buf[0] % max;
        }

        // 管理画面用トースト表示関数
        function showAdminToast(message) {
            const existingToast = document.querySelector('.sfs-toast');
            if (existingToast) existingToast.remove();

            const toast = document.createElement('div');
            toast.className = 'sfs-toast';
            toast.innerHTML = '<span class="dashicons dashicons-yes" style="color:#4ade80;"></span> ';
            toast.appendChild(document.createTextNode(String(message)));
            document.body.appendChild(toast);

            setTimeout(() => {
                toast.classList.add('hiding');
                toast.addEventListener('animationend', () => toast.remove());
            }, 3000);
        }

        // 編集モーダル関連
        function openEditModal(shareId, title) {
            document.getElementById('sfs-edit-share-id').value = shareId;
            document.getElementById('sfs-edit-title').textContent = '<?php echo esc_js(__('Editing: ', 'simple-secure-file-share')); ?>' + title;
            document.getElementById('sfs-password-action').value = 'keep';
            document.getElementById('sfs-new-password').value = '';
            document.getElementById('sfs-new-password-container').classList.add('hidden');
            document.getElementById('sfs-edit-modal').classList.remove('hidden');
        }
        
        function closeEditModal() {
            document.getElementById('sfs-edit-modal').classList.add('hidden');
        }
        
        function togglePasswordField() {
            const action = document.getElementById('sfs-password-action').value;
            const container = document.getElementById('sfs-new-password-container');
            if (action === 'set') {
                container.classList.remove('hidden');
            } else {
                container.classList.add('hidden');
            }
        }
        
        // ランダムパスワード生成 / Generate random password
        function generateRandomPassword() {
            const chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789!@#$%';
            let password = '';
            for (let i = 0; i < 16; i++) {
                password += chars.charAt(sfsSecureRandomInt(chars.length));
            }
            document.getElementById('sfs-new-password').value = password;
            showAdminToast('<?php echo esc_js(__('Random password generated', 'simple-secure-file-share')); ?>');
        }

        // クリップボードコピー関数
        function copyToClipboard(text) {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(function() {
                    showAdminToast('<?php echo esc_js(__('URL copied!', 'simple-secure-file-share')); ?>');
                }, function(err) {
                    const textArea = document.createElement("textarea");
                    textArea.value = text;
                    document.body.appendChild(textArea);
                    textArea.select();
                    try {
                        document.execCommand('copy');
                        showAdminToast('<?php echo esc_js(__('URL copied!', 'simple-secure-file-share')); ?>');
                    } catch (err) {
                        alert('<?php echo esc_js(__('Copy failed', 'simple-secure-file-share')); ?>');
                    }
                    document.body.removeChild(textArea);
                });
            } else {
                const textArea = document.createElement("textarea");
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.select();
                try {
                    document.execCommand('copy');
                    showAdminToast('<?php echo esc_js(__('URL copied!', 'simple-secure-file-share')); ?>');
                } catch (err) {
                    alert('<?php echo esc_js(__('Copy failed', 'simple-secure-file-share')); ?>');
                }
                document.body.removeChild(textArea);
            }
        }

        function togglePasswordInput(show) {
            const container = document.getElementById('sfs-password-container');
            const input = document.getElementById('sfs-password-input');
            if (show) {
                container.classList.remove('hidden');
                input.required = true;
                // リセット
                input.value = '';
                input.classList.add('sfs-masked');
            } else {
                container.classList.add('hidden');
                input.required = false;
                input.value = '';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {

            const dropZone = document.getElementById('sfs-drop-zone');
            const fileTrigger = document.getElementById('sfs-file-trigger');
            const fileListContainer = document.getElementById('sfs-file-list-container');
            const fileList = document.getElementById('sfs-file-list');
            const clearBtn = document.getElementById('sfs-clear-all');
            const form = document.getElementById('sfs-upload-form');
            
            // Progress & Modal Elements
            const progressWrapper = document.getElementById('sfs-progress-wrapper');
            const progressBar = document.getElementById('sfs-progress-bar');
            const progressText = document.getElementById('sfs-progress-text');
            const submitBtn = document.getElementById('sfs-submit-btn');
            const successModal = document.getElementById('sfs-success-modal');
            const titleInput = document.getElementById('sfs-title-input');
            
            // パスワード関連要素
            const passwordInput = document.getElementById('sfs-password-input');
            const enablePasswordRadios = document.getElementsByName('enable_password');
            const togglePasswordBtn = document.getElementById('sfs-toggle-password');
            const generatePasswordBtn = document.getElementById('sfs-generate-password');

            const ajaxUrl = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
            let allFiles = [];

            // --- パスワード関連ロジック ---

            // 1. 表示/非表示トグル (CSSクラスの着脱で制御)
            togglePasswordBtn.addEventListener('click', function() {
                const icon = this.querySelector('.dashicons');
                // sfs-masked クラスがあれば、外す（表示）。なければ付ける（非表示）。
                if (passwordInput.classList.contains('sfs-masked')) {
                    passwordInput.classList.remove('sfs-masked');
                    icon.classList.remove('dashicons-visibility');
                    icon.classList.add('dashicons-hidden');
                    this.title = 'パスワードを隠す';
                } else {
                    passwordInput.classList.add('sfs-masked');
                    icon.classList.remove('dashicons-hidden');
                    icon.classList.add('dashicons-visibility');
                    this.title = 'パスワードを表示';
                }
            });

            // 2. 自動生成 & コピー
            generatePasswordBtn.addEventListener('click', function() {
                const length = 16;
                const charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+";
                let retVal = "";
                const types = ["abcdefghijklmnopqrstuvwxyz", "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "0123456789", "!@#$%^&*()_+"];
                
                types.forEach(t => {
                    retVal += t.charAt(sfsSecureRandomInt(t.length));
                });
                for (let i = 0, n = charset.length; i < length - types.length; ++i) {
                    retVal += charset.charAt(sfsSecureRandomInt(n));
                }
                retVal = retVal.split('');
                for (let i = retVal.length - 1; i > 0; i--) {
                    const j = sfsSecureRandomInt(i + 1);
                    [retVal[i], retVal[j]] = [retVal[j], retVal[i]];
                }
                retVal = retVal.join('');

                // 入力欄にセット (生成時は一時的にマスクを外して表示)
                passwordInput.classList.remove('sfs-masked');
                passwordInput.value = retVal;
                
                const icon = togglePasswordBtn.querySelector('.dashicons');
                icon.classList.remove('dashicons-visibility');
                icon.classList.add('dashicons-hidden');

                navigator.clipboard.writeText(retVal).then(function() {
                    showAdminToast('<?php echo esc_js(__('Password copied!', 'simple-secure-file-share')); ?>');
                }, function(err) {
                    console.error('Copy failed: ', err);
                });
            });

            function updateFileList() {
                fileList.innerHTML = '';
                if (allFiles.length === 0) {
                    fileListContainer.classList.add('sfs-hidden');
                    return;
                }
                fileListContainer.classList.remove('sfs-hidden');

                allFiles.forEach((file, index) => {
                    const li = document.createElement('li');
                    li.className = 'flex items-center justify-between bg-white border border-gray-200 p-2 rounded text-sm shadow-sm';
                    const size = file.size < 1024 * 1024 
                        ? (file.size / 1024).toFixed(1) + ' KB'
                        : (file.size / 1024 / 1024).toFixed(1) + ' MB';
                    
                    // 画像ファイルかどうかチェック
                    const isImage = file.type && file.type.startsWith('image/');
                    let previewHtml = '';
                    
                    if (isImage) {
                        // 画像プレビュー用のURLを生成（まだなければ作成）
                        if (!file._previewUrl) {
                            file._previewUrl = URL.createObjectURL(file);
                        }
                        previewHtml = `<img src="${sfsEscapeHtml(file._previewUrl)}" alt="" class="w-8 h-8 object-cover rounded flex-shrink-0">`;
                    } else {
                        // 非画像ファイルはアイコン表示
                        const ext = file.name.split('.').pop().toLowerCase();
                        let iconColor = 'text-gray-400';
                        if (['pdf'].includes(ext)) iconColor = 'text-red-500';
                        if (['zip','rar','7z'].includes(ext)) iconColor = 'text-yellow-500';
                        if (['doc','docx'].includes(ext)) iconColor = 'text-blue-500';
                        if (['xls','xlsx'].includes(ext)) iconColor = 'text-green-500';
                        previewHtml = `<span class="dashicons dashicons-media-default ${iconColor} flex-shrink-0" style="font-size:24px;width:24px;height:24px;"></span>`;
                    }
                    
                    li.innerHTML = `
                        <div class="flex items-center space-x-3 truncate min-w-0 flex-1">
                            ${previewHtml}
                            <div class="min-w-0 flex-1">
                                <span class="font-medium text-gray-700 truncate block" title="${sfsEscapeHtml(file.name)}">${sfsEscapeHtml(file.name)}</span>
                                <span class="text-xs text-gray-400">${size}</span>
                            </div>
                        </div>
                        <button type="button" class="text-gray-400 hover:text-red-500 p-1 rounded hover:bg-red-50 transition flex-shrink-0 ml-2" onclick="removeFile(${index})" title="削除">
                            <span class="dashicons dashicons-dismiss"></span>
                        </button>
                    `;
                    fileList.appendChild(li);
                });
            }

            window.removeFile = function(index) {
                // プレビューURLを解放
                if (allFiles[index] && allFiles[index]._previewUrl) {
                    URL.revokeObjectURL(allFiles[index]._previewUrl);
                }
                allFiles.splice(index, 1);
                updateFileList();
            };

            function addFiles(files) {
                const newFiles = Array.from(files);
                newFiles.forEach(file => {
                    const exists = allFiles.some(f => f.name === file.name && f.size === file.size);
                    if (!exists) allFiles.push(file);
                });
                updateFileList();
            }

            fileTrigger.addEventListener('change', function(e) {
                if (this.files.length > 0) {
                    addFiles(this.files);
                    this.value = ''; 
                }
            });

            // Drag & Drop
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eName => {
                dropZone.addEventListener(eName, e => { e.preventDefault(); e.stopPropagation(); }, false);
            });
            ['dragenter', 'dragover'].forEach(eName => dropZone.addEventListener(eName, () => dropZone.classList.add('sfs-drag-over'), false));
            ['dragleave', 'drop'].forEach(eName => dropZone.addEventListener(eName, () => dropZone.classList.remove('sfs-drag-over'), false));
            
            dropZone.addEventListener('drop', function(e) {
                const files = e.dataTransfer.files;
                addFiles(files);
            });

            clearBtn.addEventListener('click', function() {
                // すべてのプレビューURLを解放
                allFiles.forEach(file => {
                    if (file._previewUrl) {
                        URL.revokeObjectURL(file._previewUrl);
                    }
                });
                allFiles = [];
                updateFileList();
            });

            // AJAX Upload Logic
            form.addEventListener('submit', function(e) {
                e.preventDefault();

                if (allFiles.length === 0) {
                    alert('ファイルを1つ以上選択してください。');
                    return;
                }
                if (!titleInput.value.trim()) {
                    alert('タイトルを入力してください。');
                    return;
                }
                
                // パスワードチェック
                let isPasswordEnabled = false;
                for(let r of enablePasswordRadios) { if(r.checked && r.value === '1') isPasswordEnabled = true; }
                if (isPasswordEnabled && !passwordInput.value.trim()) {
                    alert('パスワードを入力してください。');
                    return;
                }

                // UI Reset
                submitBtn.disabled = true;
                submitBtn.innerText = 'アップロード中...';
                progressWrapper.classList.remove('sfs-hidden');
                progressBar.style.width = '0%';
                progressText.innerText = '0%';

                const formData = new FormData();
                formData.append('action', 'sfs_async_upload');
                // Nonceを含める (Form内のinputから取得)
                const nonce = form.querySelector('input[name="sfs_nonce"]').value;
                formData.append('sfs_nonce', nonce);
                formData.append('share_title', titleInput.value);
                
                // パスワード送信
                if (isPasswordEnabled) {
                    formData.append('share_password', passwordInput.value);
                }

                // ファイルを追加
                allFiles.forEach(file => {
                    formData.append('files[]', file);
                });

                const xhr = new XMLHttpRequest();
                xhr.open('POST', ajaxUrl, true);

                // Progress Event
                xhr.upload.onprogress = function(e) {
                    if (e.lengthComputable) {
                        const percentComplete = Math.round((e.loaded / e.total) * 100);
                        progressBar.style.width = percentComplete + '%';
                        progressText.innerText = percentComplete + '%';
                    }
                };

                // Load Event
                xhr.onload = function() {
                    if (xhr.status === 200) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                // 完了表示
                                progressBar.style.width = '100%';
                                progressText.innerText = '100%';
                                
                                // トースト通知を表示
                                showAdminToast('アップロードが完了しました！');
                                
                                // 1.5秒後にページをリロード
                                setTimeout(() => {
                                    window.location.reload();
                                }, 1500);
                            } else {
                                alert('エラーが発生しました: ' + (response.data || '不明なエラー'));
                                resetFormState();
                            }
                        } catch (e) {
                            console.error('JSON Parse Error', e);
                            alert('サーバーレスポンスの解析に失敗しました。');
                            resetFormState();
                        }
                    } else {
                        alert('通信エラーが発生しました。ステータス: ' + xhr.status);
                        resetFormState();
                    }
                };

                // Error Event
                xhr.onerror = function() {
                    alert('通信エラーが発生しました。');
                    resetFormState();
                };

                xhr.send(formData);
            });

            function resetFormState() {
                submitBtn.disabled = false;
                submitBtn.innerText = 'アップロードして共有リンクを作成';
                progressWrapper.classList.add('sfs-hidden');
                progressBar.style.width = '0%';
            }
        });
        </script>
        <?php
    }

    /**
     * POST処理: 通常のアップロード (フォールバック用)
     */
    public function handle_upload_process() {
        if (!current_user_can('manage_options')) wp_die('権限がありません。');
        check_admin_referer('sfs_upload_nonce', 'sfs_nonce');

        $result = $this->process_upload_logic($_POST, $_FILES);

        if (is_wp_error($result)) {
            wp_die($result->get_error_message());
        }

        wp_redirect(admin_url('admin.php?page=simple-file-share'));
        exit;
    }

    /**
     * AJAX処理: 非同期アップロード
     */
    public function handle_async_upload() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('権限がありません。');
        }
        check_ajax_referer('sfs_upload_nonce', 'sfs_nonce');

        $result = $this->process_upload_logic($_POST, $_FILES);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(array('message' => 'Upload successful'));
    }

    /**
     * 共通アップロードロジック
     */
    private function process_upload_logic($post_data, $files_data) {
        $post_data = wp_unslash($post_data);
        $title = $this->clean_text(sanitize_text_field(isset($post_data['share_title']) ? (string) $post_data['share_title'] : ''), 200);
        $files = isset($files_data['files']) ? $files_data['files'] : array();
        // パスワード取得
        $password = isset($post_data['share_password']) ? (string) $post_data['share_password'] : '';

        if ($title === '' || !is_array($files) || !isset($files['name']) || !is_array($files['name']) || empty($files['name'][0])) {
            return new WP_Error('missing_data', __('Title and files are required.', 'simple-secure-file-share'));
        }

        $count = count($files['name']);
        if ($count > self::MAX_FILES_PER_SHARE) {
            return new WP_Error('too_many_files', sprintf(__('You can upload up to %d files at once.', 'simple-secure-file-share'), self::MAX_FILES_PER_SHARE));
        }
        if (strlen($password) > 1024) {
            return new WP_Error('invalid_password', __('Password is too long.', 'simple-secure-file-share'));
        }

        $this->ensure_storage_protection();

        // トークン生成
        $token = bin2hex(random_bytes(16));
        $target_dir = $this->get_upload_dir() . $token . '/';
        
        if (!@mkdir($target_dir, 0750, true)) {
            return new WP_Error('dir_error', __('Failed to create directory.', 'simple-secure-file-share'));
        }
        @file_put_contents($target_dir . 'index.php', "<?php\n// Silence is golden.\n");

        $saved_files = array();
        $max_size = wp_max_upload_size();
        
        for ($i = 0; $i < $count; $i++) {
            $error = isset($files['error'][$i]) ? (int) $files['error'][$i] : UPLOAD_ERR_NO_FILE;
            $tmp_name = isset($files['tmp_name'][$i]) ? (string) $files['tmp_name'][$i] : '';
            if ($error !== UPLOAD_ERR_OK || !is_uploaded_file($tmp_name) || filesize($tmp_name) > $max_size) {
                continue;
            }

            $original_name = $this->clean_original_name(isset($files['name'][$i]) ? $files['name'][$i] : '');
            // ディスク上の名前はランダム値 + 固定拡張子（.php / .phtml / .htaccess 等を保存させない）
            $filename = bin2hex(random_bytes(16)) . '.dat';
            $destination = $target_dir . $filename;

            if (move_uploaded_file($tmp_name, $destination)) {
                @chmod($destination, 0640);
                $saved_files[] = array(
                    'system_name' => $filename,
                    'original_name' => $original_name, // 表示用
                    'size' => (int) filesize($destination)
                );
            }
        }

        if (empty($saved_files)) {
            $this->delete_share_directory($token);
            return new WP_Error('upload_failed', __('Failed to save files.', 'simple-secure-file-share'));
        }

        // 投稿作成
        $post_id = wp_insert_post(array(
            'post_title' => $title,
            'post_type'  => 'sfs_share',
            'post_status' => 'publish'
        ), true);

        if (is_wp_error($post_id)) {
            $this->delete_share_directory($token);
            return $post_id;
        }

        update_post_meta($post_id, 'sfs_token', $token);
        update_post_meta($post_id, 'sfs_files', $saved_files);
        
        // パスワード保存
        if (trim($password) !== '') {
            update_post_meta($post_id, 'sfs_password_hash', $this->hash_share_password($password));
        }

        return true;
    }

    /**
     * POST処理: 削除
     */
    public function handle_delete_share() {
        if (!current_user_can('manage_options')) wp_die(__('Permission denied.', 'simple-secure-file-share'), '', array('response' => 403));
        check_admin_referer('sfs_delete_nonce', 'sfs_nonce');

        $share_id = isset($_POST['share_id']) ? absint($_POST['share_id']) : 0;

        // sfs_share 以外の投稿は削除しない
        if ($this->get_share($share_id)) {
            $token = get_post_meta($share_id, 'sfs_token', true);
            if ($token) {
                $this->delete_share_directory($token);
            }
            wp_delete_post($share_id, true);
        }

        wp_safe_redirect(admin_url('admin.php?page=simple-file-share'));
        exit;
    }

    /**
     * POST処理: パスワード更新
     */
    public function handle_update_password_post() {
        if (!current_user_can('manage_options')) wp_die(__('Permission denied', 'simple-secure-file-share'), '', array('response' => 403));
        check_admin_referer('sfs_update_password_nonce', 'sfs_nonce');

        $share_id = isset($_POST['share_id']) ? absint($_POST['share_id']) : 0;
        $action = isset($_POST['password_action']) ? sanitize_key($_POST['password_action']) : 'keep';
        $new_password = isset($_POST['new_password']) ? (string) wp_unslash($_POST['new_password']) : '';

        if (!$share_id || !$this->get_share($share_id)) {
            wp_safe_redirect(admin_url('admin.php?page=simple-file-share'));
            exit;
        }

        if ($action === 'remove') {
            delete_post_meta($share_id, 'sfs_password_hash');
        } elseif ($action === 'set' && trim($new_password) !== '' && strlen($new_password) <= 1024) {
            update_post_meta($share_id, 'sfs_password_hash', $this->hash_share_password($new_password));
        }

        wp_safe_redirect(admin_url('admin.php?page=simple-file-share'));
        exit;
    }

    /**
     * 画像取得ハンドラ
     * セキュリティ: パスワード認証（署名付きCookie）+ 登録済みファイルのみ + 安全な画像形式のみ
     */
    public function handle_get_image() {
        $token = isset($_GET['token']) ? (string) wp_unslash($_GET['token']) : '';
        $file_name = isset($_GET['file']) ? (string) wp_unslash($_GET['file']) : '';
        $is_admin = current_user_can('manage_options');

        if (!$this->is_valid_token($token) || $file_name === '') {
            status_header(404);
            exit;
        }

        // リファラチェック: 自サイトからのアクセスのみ許可（SNS埋め込み・直リンク抑止。認証の代わりではない）
        $referer = isset($_SERVER['HTTP_REFERER']) ? (string) $_SERVER['HTTP_REFERER'] : '';
        if (!$is_admin && ($referer === '' || strpos($referer, trailingslashit(home_url())) !== 0)) {
            status_header(403);
            exit;
        }

        $share = $this->get_share_by_token($token);
        if (!$share) {
            status_header(404);
            exit;
        }
        $share_id = $share->ID;

        // パスワード保護チェック（共有ページと同じ署名付きCookie）
        $password_hash = get_post_meta($share_id, 'sfs_password_hash', true);
        if ($password_hash && !$is_admin && !$this->is_share_authenticated($share_id, $token, $password_hash)) {
            status_header(403);
            exit;
        }

        // 共有に登録されたファイルのみ
        $files = get_post_meta($share_id, 'sfs_files', true);
        $original_name = null;
        foreach ((is_array($files) ? $files : array()) as $f) {
            if (isset($f['system_name']) && hash_equals((string) $f['system_name'], $file_name)) {
                $original_name = (string) $f['original_name'];
                break;
            }
        }
        $file_path = $original_name !== null ? $this->resolve_share_file($token, $file_name) : null;
        if (!$file_path) {
            status_header(404);
            exit;
        }

        // 中身からMIMEを判定し、スクリプトを含み得ない画像形式のみ配信（SVG等は拒否）
        $mime_type = function_exists('finfo_open') ? (string) finfo_file(finfo_open(FILEINFO_MIME_TYPE), $file_path) : (string) mime_content_type($file_path);
        if (!in_array($mime_type, self::PREVIEW_MIME_TYPES, true)) {
            status_header(403);
            exit;
        }

        $this->send_file($file_path, $original_name, $mime_type, 'inline');
    }

    /**
     * フロントエンド: 共有ページの表示とダウンロード処理
     */
    public function handle_frontend_view() {
        $token = get_query_var('sfs_token');
        if (!$token) return;

        $share = $this->get_share_by_token($token);

        if (!$share) {
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            return;
        }

        $this->send_frontend_headers();

        $post_id = $share->ID;
        $files = get_post_meta($post_id, 'sfs_files', true);
        $files = is_array($files) ? $files : array();
        $title = get_the_title($post_id);
        
        // パスワードチェック処理
        $password_hash = get_post_meta($post_id, 'sfs_password_hash', true);
        if ($password_hash) {
            $this->check_password_auth($post_id, $token, $password_hash, $title);
        }

        // ダウンロード処理
        if (isset($_GET['download'])) {
            // 直リンク防止: 共有ページで発行した署名付きトークンのみ許可
            $dl_token = isset($_GET['dl_token']) ? (string) wp_unslash($_GET['dl_token']) : '';
            if (!$this->verify_download_token($post_id, $token, $dl_token)) {
                wp_die(__('Invalid access. Please download from the share page.', 'simple-secure-file-share'), __('Access Denied', 'simple-secure-file-share'), array('response' => 403));
            }
            
            $target = (string) wp_unslash($_GET['download']);
            if ($target === 'zip') {
                // ダウンロードカウントを増加
                $current_count = (int)get_post_meta($post_id, 'sfs_download_count', true);
                update_post_meta($post_id, 'sfs_download_count', $current_count + 1);
                
                $this->download_as_zip($token, $files, $title);
            } else {
                // 個別ダウンロード（共有に登録済みのファイルのみ）
                foreach ($files as $f) {
                    if (isset($f['system_name']) && hash_equals((string) $f['system_name'], $target)) {
                        $file_path = $this->resolve_share_file($token, $f['system_name']);
                        if ($file_path) {
                            // ダウンロードカウントを増加
                            $current_count = (int)get_post_meta($post_id, 'sfs_download_count', true);
                            update_post_meta($post_id, 'sfs_download_count', $current_count + 1);

                            $this->send_file($file_path, (string) $f['original_name']);
                        }
                        break;
                    }
                }
                wp_die(__('File not found.', 'simple-secure-file-share'), '', array('response' => 404));
            }
        }

        $this->render_frontend_html($title, $files, $token, $post_id);
        exit;
    }

    /**
     * パスワード認証ロジック
     */
    private function check_password_auth($post_id, $token, $stored_hash, $title) {
        // 1. POST送信時のチェック
        if (isset($_POST['sfs_password'])) {
            if ($this->is_auth_locked($token)) {
                status_header(429);
                $this->render_password_form($title, 'locked');
                exit;
            }

            $password = (string) wp_unslash($_POST['sfs_password']);
            if ($this->verify_share_password($post_id, $password, $stored_hash)) {
                $this->clear_auth_failures($token);
                // 再ハッシュされた可能性があるため最新のハッシュで署名
                $current_hash = get_post_meta($post_id, 'sfs_password_hash', true);
                $this->set_share_auth_cookie($post_id, $token, $current_hash);
                // リロードしてPOSTデータをクリア
                wp_safe_redirect(home_url('/' . $this->share_slug . '/' . $token), 303);
                exit;
            }

            // 認証失敗
            $this->register_auth_failure($token);
            status_header($this->is_auth_locked($token) ? 429 : 401);
            $this->render_password_form($title, $this->is_auth_locked($token) ? 'locked' : true);
            exit;
        }

        // 2. Cookieチェック
        if ($this->is_share_authenticated($post_id, $token, $stored_hash)) {
            return; // 認証OK
        }

        // 3. 未認証 -> フォーム表示
        $this->render_password_form($title, false);
        exit;
    }

    /**
     * パスワード入力画面のレンダリング
     */
    private function render_password_form($title, $is_error = false) {
        ?>
        <!DOCTYPE html>
        <html lang="ja">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="robots" content="noindex, nofollow">
            <title><?php _e('Password Authentication', 'simple-secure-file-share'); ?> - <?php echo esc_html($title); ?></title>
            <link rel="stylesheet" href="<?php echo esc_url(plugins_url('assets/css/tailwind.min.css', __FILE__) . '?ver=' . self::VERSION); ?>">
            <style>
                body { background-color: #f3f4f6; font-family: 'Helvetica Neue', Arial, sans-serif; }
                /* パスワードマスク用CSS (text-security) */
                .sfs-masked {
                    -webkit-text-security: disc;
                    text-security: disc;
                    font-family: text-security-disc, sans-serif;
                }
            </style>
        </head>
        <body class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white w-full max-w-md rounded-xl shadow-2xl overflow-hidden">
                <div class="bg-gray-800 p-6 text-center text-white">
                    <span class="dashicons dashicons-lock" style="font-size:32px;width:32px;height:32px;color:#93C5FD;"></span>
                    <h1 class="text-xl font-bold mt-2"><?php _e('Password Protected Area', 'simple-secure-file-share'); ?></h1>
                </div>
                
                <div class="p-8">
                    <p class="text-gray-600 text-sm mb-6 text-center">
                        "<strong><?php echo esc_html($title); ?></strong>"<br>
                        <?php _e('A password is required to access this content.', 'simple-secure-file-share'); ?>
                    </p>

                    <?php if($is_error): ?>
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4 text-sm" role="alert">
                            <span class="block sm:inline"><?php
                                if ($is_error === 'locked') {
                                    _e('Too many failed attempts. Please wait 15 minutes and try again.', 'simple-secure-file-share');
                                } else {
                                    _e('Incorrect password.', 'simple-secure-file-share');
                                }
                            ?></span>
                        </div>
                    <?php endif; ?>

                    <form method="post" class="space-y-4" autocomplete="off">
                        <div class="relative">
                            <label for="sfs-frontend-password" class="sr-only"><?php _e('Password', 'simple-secure-file-share'); ?></label>
                            <!-- type="text" で実装し、CSS(.sfs-masked)で隠す -->
                            <!-- autocomplete="off" を指定し、ブラウザの介入を最小限に -->
                            <input type="text" name="sfs_password" id="sfs-frontend-password" required maxlength="1024" 
                                class="sfs-masked appearance-none rounded-lg relative block w-full px-4 py-3 pr-10 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm" 
                                placeholder="<?php esc_attr_e('Enter password', 'simple-secure-file-share'); ?>"
                                autocomplete="off"
                                autocorrect="off" 
                                autocapitalize="off" 
                                spellcheck="false">
                            
                            <!-- 表示/非表示トグルボタン (フロントエンド) -->
                            <button type="button" onclick="toggleFrontPassword()" class="absolute inset-y-0 right-0 px-3 flex items-center text-gray-400 hover:text-gray-600 cursor-pointer focus:outline-none">
                                <span id="front-pass-icon" class="dashicons dashicons-visibility" style="margin-top:2px;"></span>
                            </button>
                        </div>
                        <button type="submit" class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition">
                            <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                                <svg class="h-5 w-5 text-blue-500 group-hover:text-blue-400 transition" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" /></svg>
                            </span>
                            <?php _e('Authenticate and View Files', 'simple-secure-file-share'); ?>
                        </button>
                    </form>
                </div>
                <div class="bg-gray-50 px-6 py-4 border-t border-gray-100 text-center">
                    <p class="text-xs text-gray-400">Secure File Sharing System</p>
                </div>
            </div>
            <script>
                function toggleFrontPassword() {
                    var input = document.getElementById('sfs-frontend-password');
                    var icon = document.getElementById('front-pass-icon');
                    
                    // sfs-masked クラスがあれば、外す（表示）。なければ付ける（非表示）。
                    if (input.classList.contains('sfs-masked')) {
                        input.classList.remove('sfs-masked');
                        icon.classList.remove('dashicons-visibility');
                        icon.classList.add('dashicons-hidden');
                    } else {
                        input.classList.add('sfs-masked');
                        icon.classList.remove('dashicons-hidden');
                        icon.classList.add('dashicons-visibility');
                    }
                }
            </script>
        </body>
        </html>
        <?php
    }

    /**
     * Zipダウンロード処理
     */
    private function download_as_zip($token, $files, $title) {
        if (!class_exists('ZipArchive')) {
            wp_die(__('ZIP is not available on this server.', 'simple-secure-file-share'), '', array('response' => 500));
        }

        $zip = new ZipArchive();
        $zip_filename = tempnam(get_temp_dir(), 'sfs_zip');

        // 途中切断時も一時ファイルを確実に削除
        ignore_user_abort(true);
        register_shutdown_function(function () use ($zip_filename) {
            if (is_file($zip_filename)) {
                @unlink($zip_filename);
            }
        });
        
        if ($zip->open($zip_filename, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            wp_die(__('Could not create archive.', 'simple-secure-file-share'), '', array('response' => 500));
        }

        $used = array();
        foreach ($files as $file) {
            $file_path = isset($file['system_name']) ? $this->resolve_share_file($token, $file['system_name']) : null;
            if (!$file_path) {
                continue;
            }
            // Zip Slip 対策: パスを含まない一意なエントリ名
            $entry = sanitize_file_name($this->clean_original_name(isset($file['original_name']) ? $file['original_name'] : ''));
            $info = pathinfo($entry);
            $base = isset($info['filename']) ? $info['filename'] : 'file';
            $ext = isset($info['extension']) ? '.' . $info['extension'] : '';
            $n = 1;
            while ($entry === '' || isset($used[strtolower($entry)])) {
                $entry = $base . '_' . $n++ . $ext;
            }
            $used[strtolower($entry)] = true;
            $zip->addFile($file_path, $entry);
        }
        $zip->close();

        if (!is_file($zip_filename)) {
            wp_die(__('Could not create archive.', 'simple-secure-file-share'), '', array('response' => 500));
        }

        $this->send_file($zip_filename, $title . '_files.zip', 'application/zip');
    }

    /**
     * フロントエンド画面のHTML
     */
    private function render_frontend_html($title, $files, $token, $post_id) {
        $download_base = add_query_arg('dl_token', $this->download_token($post_id, $token), home_url('/' . $this->share_slug . '/' . $token));
        
        $total_size = 0;
        foreach($files as $f) $total_size += $f['size'];
        $formatted_total_size = size_format($total_size);

        // ダウンロードボタンのHTML生成（共通部分として定義）
        ob_start();
        ?>
        <?php if (count($files) > 1): ?>
        <a href="<?php echo esc_url(add_query_arg('download', 'zip', $download_base)); ?>" class="flex items-center justify-center space-x-2 bg-gray-900 hover:bg-black text-white px-6 py-3 rounded-lg shadow-lg transform hover:-translate-y-0.5 transition font-bold whitespace-nowrap">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
            <span><?php _e('Download as ZIP', 'simple-secure-file-share'); ?></span>
        </a>
        <?php else: ?>
        <a href="<?php echo esc_url(add_query_arg('download', rawurlencode($files[0]['system_name']), $download_base)); ?>" class="flex items-center justify-center space-x-2 bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg shadow-lg transform hover:-translate-y-0.5 transition font-bold whitespace-nowrap">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
            <span><?php _e('Download File', 'simple-secure-file-share'); ?></span>
        </a>
        <?php endif; ?>
        <?php
        $download_btn_html = ob_get_clean();

        // 画像拡張子リスト
        // SVG はスクリプトを含み得るためプレビュー対象外
        $image_extensions = array('jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'avif');

        ?>
        <!DOCTYPE html>
        <html lang="ja">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="robots" content="noindex, nofollow">
            <meta name="color-scheme" content="light dark">
            <title><?php echo esc_html($title); ?> - <?php _e('File Share', 'simple-secure-file-share'); ?></title>
            <link rel="stylesheet" href="<?php echo esc_url(plugins_url('assets/css/tailwind.min.css', __FILE__) . '?ver=' . self::VERSION); ?>">
            <style>
                /* CSS Variables for theming */
                :root {
                    --sfs-bg: #f3f4f6;
                    --sfs-bg-card: #ffffff;
                    --sfs-bg-secondary: #f9fafb;
                    --sfs-bg-tertiary: #f3f4f6;
                    --sfs-border: #e5e7eb;
                    --sfs-border-light: #f3f4f6;
                    --sfs-text: #111827;
                    --sfs-text-secondary: #374151;
                    --sfs-text-muted: #6b7280;
                    --sfs-text-light: #9ca3af;
                    --sfs-shadow: rgba(0,0,0,0.1);
                    --sfs-shadow-lg: rgba(0,0,0,0.15);
                }
                
                @media (prefers-color-scheme: dark) {
                    :root {
                        --sfs-bg: #111827;
                        --sfs-bg-card: #1f2937;
                        --sfs-bg-secondary: #374151;
                        --sfs-bg-tertiary: #1f2937;
                        --sfs-border: #374151;
                        --sfs-border-light: #4b5563;
                        --sfs-text: #f9fafb;
                        --sfs-text-secondary: #e5e7eb;
                        --sfs-text-muted: #9ca3af;
                        --sfs-text-light: #6b7280;
                        --sfs-shadow: rgba(0,0,0,0.3);
                        --sfs-shadow-lg: rgba(0,0,0,0.5);
                    }
                }
                
                body { 
                    background-color: var(--sfs-bg); 
                    font-family: 'Helvetica Neue', Arial, sans-serif; 
                    color: var(--sfs-text);
                    transition: background-color 0.3s, color 0.3s;
                }
                
                /* View Toggle */
                .sfs-view-toggle { display: flex; gap: 0.25rem; background: var(--sfs-border); padding: 0.25rem; border-radius: 0.5rem; }
                .sfs-view-btn { padding: 0.5rem; border-radius: 0.375rem; cursor: pointer; transition: all 0.2s; color: var(--sfs-text-muted); background: transparent; border: none; }
                .sfs-view-btn:hover { color: var(--sfs-text-secondary); }
                .sfs-view-btn.active { background: var(--sfs-bg-card); color: #3b82f6; box-shadow: 0 1px 3px var(--sfs-shadow); }
                
                /* Grid View */
                .sfs-file-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 1rem; }
                @media (min-width: 640px) { .sfs-file-grid { grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); } }
                
                .sfs-grid-item { 
                    background: var(--sfs-bg-card); 
                    border: 1px solid var(--sfs-border); 
                    border-radius: 0.75rem; 
                    overflow: hidden; 
                    transition: all 0.2s; 
                }
                .sfs-grid-item:hover { box-shadow: 0 4px 12px var(--sfs-shadow); transform: translateY(-2px); }
                .sfs-grid-preview { 
                    width: 100%; 
                    aspect-ratio: 1; 
                    background: var(--sfs-bg-secondary); 
                    display: flex; 
                    align-items: center; 
                    justify-content: center; 
                    overflow: hidden;
                    cursor: pointer;
                }
                .sfs-grid-preview img { width: 100%; height: 100%; object-fit: cover; }
                .sfs-grid-info { padding: 0.75rem; border-top: 1px solid var(--sfs-border-light); }
                .sfs-grid-name { font-size: 0.875rem; font-weight: 500; color: var(--sfs-text-secondary); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
                .sfs-grid-meta { display: flex; justify-content: space-between; align-items: center; margin-top: 0.25rem; }
                .sfs-grid-size { font-size: 0.75rem; color: var(--sfs-text-light); }
                .sfs-grid-dl { font-size: 0.75rem; color: #3b82f6; font-weight: 500; }
                .sfs-grid-dl:hover { color: #2563eb; text-decoration: underline; }
                
                /* List View */
                .sfs-file-list { display: flex; flex-direction: column; gap: 0.75rem; }
                .sfs-list-item { 
                    background: var(--sfs-bg-card); 
                    border: 1px solid var(--sfs-border); 
                    border-radius: 0.75rem; 
                    padding: 0.75rem; 
                    display: flex; 
                    align-items: center; 
                    justify-content: space-between; 
                    transition: all 0.2s; 
                }
                .sfs-list-item:hover { box-shadow: 0 4px 12px var(--sfs-shadow); }
                .sfs-list-preview { width: 48px; height: 48px; flex-shrink: 0; border-radius: 0.5rem; overflow: hidden; background: var(--sfs-bg-secondary); display: flex; align-items: center; justify-content: center; cursor: pointer; }
                .sfs-list-preview img { width: 100%; height: 100%; object-fit: cover; }
                .sfs-list-name { font-weight: 500; color: var(--sfs-text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
                .sfs-list-size { font-size: 0.75rem; color: var(--sfs-text-muted); }
                
                /* Footer */
                .sfs-footer { padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem; background: var(--sfs-bg-card); }
                @media (min-width: 768px) { .sfs-footer { flex-direction: row; justify-content: space-between; align-items: center; } }
                .sfs-footer-text { font-size: 0.75rem; color: var(--sfs-text-muted); text-align: center; }
                @media (min-width: 768px) { .sfs-footer-text { text-align: left; } }
                
                /* Icon color for dark mode */
                .sfs-file-icon { color: var(--sfs-text-muted); }
                

                /* Lightbox */
                .sfs-lightbox { 
                    position: fixed; 
                    top: 0; left: 0; 
                    width: 100%; height: 100%; 
                    background: rgba(0,0,0,0.9); 
                    z-index: 9999; 
                    display: none; 
                    align-items: center; 
                    justify-content: center; 
                    padding: 1rem;
                }
                .sfs-lightbox.active { display: flex; }
                .sfs-lightbox img { max-width: 100%; max-height: 90vh; border-radius: 0.5rem; }
                .sfs-lightbox-close { 
                    position: absolute; 
                    top: 1rem; right: 1rem; 
                    width: 40px; height: 40px; 
                    background: white; 
                    border-radius: 50%; 
                    display: flex; 
                    align-items: center; 
                    justify-content: center; 
                    cursor: pointer; 
                    color: #374151; 
                    font-size: 1.25rem; 
                    font-weight: bold;
                    line-height: 1;
                    text-align: center;
                    border: none;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
                    transition: transform 0.2s, box-shadow 0.2s;
                }
                .sfs-lightbox-close:hover {
                    transform: scale(1.1);
                    box-shadow: 0 4px 12px rgba(0,0,0,0.4);
                }
            </style>
        </head>
        <body class="flex items-center justify-center min-h-screen p-4">

            <div class="w-full max-w-2xl rounded-xl shadow-2xl overflow-hidden" style="background: var(--sfs-bg-card);">
                <div class="bg-gradient-to-r from-blue-600 to-indigo-700 p-8 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-200 text-sm font-semibold uppercase tracking-wider"><?php _e('File Sharing', 'simple-secure-file-share'); ?></p>
                            <h1 class="text-3xl font-bold mt-1"><?php echo esc_html($title); ?></h1>
                        </div>
                        <div class="bg-white/20 p-3 rounded-full">
                            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center text-sm text-blue-100 space-x-4">
                        <span><span class="font-bold"><?php echo count($files); ?></span> <?php _e('files', 'simple-secure-file-share'); ?></span>
                        <span><?php _e('Total', 'simple-secure-file-share'); ?> <span class="font-bold"><?php echo $formatted_total_size; ?></span></span>
                    </div>
                </div>

                <!-- View Toggle & Download (Top) -->
                <div class="p-4 flex justify-between items-center" style="background: var(--sfs-bg-tertiary); border-bottom: 1px solid var(--sfs-border);">
                    <div class="sfs-view-toggle">
                        <button type="button" class="sfs-view-btn active" data-view="list" title="<?php esc_attr_e('List View', 'simple-secure-file-share'); ?>">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
                        </button>
                        <button type="button" class="sfs-view-btn" data-view="grid" title="<?php esc_attr_e('Grid View', 'simple-secure-file-share'); ?>">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                        </button>
                    </div>
                    <?php if (count($files) >= 5): ?>
                    <?php echo $download_btn_html; ?>
                    <?php endif; ?>
                </div>

                <div class="p-6" style="background: var(--sfs-bg-tertiary); border-bottom: 1px solid var(--sfs-border-light);">
                    <!-- List View (default) -->
                    <div id="sfs-list-view" class="sfs-file-list">
                        <?php foreach ($files as $file): 
                            $single_url = add_query_arg('download', rawurlencode($file['system_name']), $download_base);
                            $ext = strtolower(pathinfo($file['original_name'], PATHINFO_EXTENSION));
                            $is_image = in_array($ext, $image_extensions);
                            $icon_color = 'text-gray-400';
                            if($is_image) $icon_color = 'text-purple-500';
                            if(in_array($ext, ['pdf'])) $icon_color = 'text-red-500';
                            if(in_array($ext, ['zip','rar','7z'])) $icon_color = 'text-yellow-500';
                            if(in_array($ext, ['doc','docx'])) $icon_color = 'text-blue-500';
                            if(in_array($ext, ['xls','xlsx'])) $icon_color = 'text-green-500';
                            
                            // 画像の場合はプレビューURLを生成 (AJAX経由)
                            $preview_url = '';
                            if ($is_image) {
                                $preview_url = add_query_arg(array('action' => 'sfs_get_image', 'token' => $token, 'file' => rawurlencode($file['system_name'])), admin_url('admin-ajax.php'));
                            }
                        ?>
                        <div class="sfs-list-item">
                            <div class="flex items-center space-x-3 overflow-hidden">
                                <?php if ($is_image && $preview_url): ?>
                                <div class="sfs-list-preview" onclick="openLightbox('<?php echo esc_url($preview_url); ?>')">
                                    <img src="<?php echo esc_url($preview_url); ?>" alt="<?php echo esc_attr($file['original_name']); ?>">
                                </div>
                                <?php else: ?>
                                <div class="sfs-list-preview">
                                    <svg class="w-6 h-6 <?php echo $icon_color; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                </div>
                                <?php endif; ?>
                                <div class="min-w-0">
                                    <p class="sfs-list-name truncate" title="<?php echo esc_attr($file['original_name']); ?>"><?php echo esc_html($file['original_name']); ?></p>
                                    <p class="sfs-list-size"><?php echo size_format($file['size']); ?></p>
                                </div>
                            </div>
                            <a href="<?php echo esc_url($single_url); ?>" class="ml-4 text-blue-600 hover:text-blue-800 bg-blue-50 hover:bg-blue-100 px-3 py-1 rounded text-sm font-medium transition whitespace-nowrap">
                                DL
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Grid View (hidden by default) -->
                    <div id="sfs-grid-view" class="sfs-file-grid" style="display: none;">
                        <?php foreach ($files as $file): 
                            $single_url = add_query_arg('download', rawurlencode($file['system_name']), $download_base);
                            $ext = strtolower(pathinfo($file['original_name'], PATHINFO_EXTENSION));
                            $is_image = in_array($ext, $image_extensions);
                            $icon_color = 'text-gray-400';
                            if($is_image) $icon_color = 'text-purple-500';
                            if(in_array($ext, ['pdf'])) $icon_color = 'text-red-500';
                            if(in_array($ext, ['zip','rar','7z'])) $icon_color = 'text-yellow-500';
                            if(in_array($ext, ['doc','docx'])) $icon_color = 'text-blue-500';
                            if(in_array($ext, ['xls','xlsx'])) $icon_color = 'text-green-500';
                            
                            $preview_url = '';
                            if ($is_image) {
                                $preview_url = add_query_arg(array('action' => 'sfs_get_image', 'token' => $token, 'file' => rawurlencode($file['system_name'])), admin_url('admin-ajax.php'));
                            }
                        ?>
                        <div class="sfs-grid-item">
                            <?php if ($is_image && $preview_url): ?>
                            <div class="sfs-grid-preview" onclick="openLightbox('<?php echo esc_url($preview_url); ?>')">
                                <img src="<?php echo esc_url($preview_url); ?>" alt="<?php echo esc_attr($file['original_name']); ?>">
                            </div>
                            <?php else: ?>
                            <div class="sfs-grid-preview">
                                <svg class="w-12 h-12 <?php echo $icon_color; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                            </div>
                            <?php endif; ?>
                            <div class="sfs-grid-info">
                                <p class="sfs-grid-name" title="<?php echo esc_attr($file['original_name']); ?>"><?php echo esc_html($file['original_name']); ?></p>
                                <div class="sfs-grid-meta">
                                    <span class="sfs-grid-size"><?php echo size_format($file['size']); ?></span>
                                    <a href="<?php echo esc_url($single_url); ?>" class="sfs-grid-dl">DL</a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="sfs-footer">
                    <div class="sfs-footer-text">
                        <?php _e('If you have any questions, please feel free to contact us.', 'simple-secure-file-share'); ?>
                    </div>
                    <?php echo $download_btn_html; ?>
                </div>
            </div>

            <!-- Lightbox Modal -->
            <div id="sfs-lightbox" class="sfs-lightbox" onclick="closeLightbox()">
                <button class="sfs-lightbox-close" onclick="event.stopPropagation(); closeLightbox();">&times;</button>
                <img id="sfs-lightbox-img" src="" alt="Preview" onclick="event.stopPropagation();">
            </div>

            <script>
                // View Toggle
                document.querySelectorAll('.sfs-view-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const view = this.dataset.view;
                        document.querySelectorAll('.sfs-view-btn').forEach(b => b.classList.remove('active'));
                        this.classList.add('active');
                        
                        if (view === 'grid') {
                            document.getElementById('sfs-list-view').style.display = 'none';
                            document.getElementById('sfs-grid-view').style.display = 'grid';
                        } else {
                            document.getElementById('sfs-list-view').style.display = 'flex';
                            document.getElementById('sfs-grid-view').style.display = 'none';
                        }
                        
                        localStorage.setItem('sfs_frontend_view', view);
                    });
                });
                
                // Restore saved view preference
                (function() {
                    const saved = localStorage.getItem('sfs_frontend_view');
                    if (saved === 'grid') {
                        document.querySelector('.sfs-view-btn[data-view="grid"]').click();
                    }
                })();
                
                // Lightbox
                function openLightbox(src) {
                    document.getElementById('sfs-lightbox-img').src = src;
                    document.getElementById('sfs-lightbox').classList.add('active');
                    document.body.style.overflow = 'hidden';
                }
                function closeLightbox() {
                    document.getElementById('sfs-lightbox').classList.remove('active');
                    document.body.style.overflow = '';
                }
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') closeLightbox();
                });
            </script>

        </body>
        </html>
        <?php
    }

    /**
     * 高度な設定ページ
     */
    public function render_advanced_page() {
        if (!current_user_can('manage_options')) return;
        
        // 全ての共有データを取得
        $shares = get_posts(array(
            'post_type' => 'sfs_share',
            'posts_per_page' => -1,
            'post_status' => 'any',
        ));
        
        // アップロードフォルダ内のディレクトリ一覧を取得
        $upload_folders = array();
        if (is_dir($this->get_upload_dir())) {
            $dirs = glob($this->get_upload_dir() . '*', GLOB_ONLYDIR);
            foreach ($dirs as $dir) {
                $upload_folders[] = basename($dir);
            }
        }
        
        // DBに登録されているトークン一覧
        $db_tokens = array();
        foreach ($shares as $share) {
            $token = get_post_meta($share->ID, 'sfs_token', true);
            if ($token) {
                $db_tokens[] = $token;
            }
        }
        
        // 孤立ファイル（DBにないけどフォルダにあるもの）
        $orphan_folders = array_diff($upload_folders, $db_tokens);
        
        // 孤立レコード（DBにあるけどフォルダがないもの）
        $orphan_records = array();
        foreach ($shares as $share) {
            $token = get_post_meta($share->ID, 'sfs_token', true);
            if ($token && !in_array($token, $upload_folders)) {
                $orphan_records[] = $share;
            }
        }
        
        ?>
        <div class="wrap">
            <style>
                .sfs-adv h1, .sfs-adv h2, .sfs-adv h3 { margin-bottom: 0.5em; font-weight: bold; }
            </style>
            
            <div class="sfs-adv p-6 max-w-5xl mx-auto bg-white shadow-lg rounded-lg mt-5">
                <h1 class="text-2xl mb-6 text-gray-800 flex items-center gap-2">
                    <span class="dashicons dashicons-admin-tools text-orange-500" style="font-size:28px;width:28px;height:28px;"></span>
                    <?php _e('Advanced Settings', 'simple-secure-file-share'); ?>
                </h1>
                
                <p class="text-gray-600 mb-6">
                    <?php _e('On this page, you can check and manage the consistency of the plugin database and files.', 'simple-secure-file-share'); ?><br>
                    <span class="text-red-600 font-bold"><?php _e('* Warning: Cleanup operations cannot be undone.', 'simple-secure-file-share'); ?></span>
                </p>
                
                <!-- 統計情報 -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                    <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                        <div class="text-blue-800 font-bold text-2xl"><?php echo count($shares); ?></div>
                        <div class="text-blue-600 text-sm"><?php _e('Registered Shares', 'simple-secure-file-share'); ?></div>
                    </div>
                    <div class="bg-green-50 p-4 rounded-lg border border-green-200">
                        <div class="text-green-800 font-bold text-2xl"><?php echo count($upload_folders); ?></div>
                        <div class="text-green-600 text-sm"><?php _e('Upload Folders', 'simple-secure-file-share'); ?></div>
                    </div>
                    <div class="bg-yellow-50 p-4 rounded-lg border border-yellow-200">
                        <div class="text-yellow-800 font-bold text-2xl"><?php echo count($orphan_folders); ?></div>
                        <div class="text-yellow-600 text-sm"><?php _e('Orphan Folders', 'simple-secure-file-share'); ?></div>
                    </div>
                    <div class="bg-red-50 p-4 rounded-lg border border-red-200">
                        <div class="text-red-800 font-bold text-2xl"><?php echo count($orphan_records); ?></div>
                        <div class="text-red-600 text-sm"><?php _e('Orphan Records', 'simple-secure-file-share'); ?></div>
                    </div>
                </div>
                
                <!-- 孤立ファイルのクリーンアップ -->
                <?php if (!empty($orphan_folders)): ?>
                <div class="bg-yellow-50 p-6 rounded-lg border border-yellow-300 mb-6">
                    <h2 class="text-lg text-yellow-800 mb-2 flex items-center gap-2">
                        <span class="dashicons dashicons-warning"></span>
                        <?php _e('Orphan folders found', 'simple-secure-file-share'); ?>
                    </h2>
                    <p class="text-yellow-700 text-sm mb-4">
                        <?php _e('The following folders are not registered in the database.', 'simple-secure-file-share'); ?><br>
                        <?php _e('They may have been created by an older version or the database was deleted.', 'simple-secure-file-share'); ?>
                    </p>
                    <ul class="text-sm text-yellow-800 mb-4 max-h-40 overflow-y-auto bg-white p-3 rounded border">
                        <?php foreach ($orphan_folders as $folder): ?>
                        <li class="py-1 border-b border-yellow-100 last:border-0">
                            <code><?php echo esc_html($folder); ?></code>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <form action="<?php echo admin_url('admin-post.php'); ?>" method="post" onsubmit="return confirm('<?php echo esc_js(__('Orphan folders will be permanently deleted. This cannot be undone. Continue?', 'simple-secure-file-share')); ?>');">
                        <input type="hidden" name="action" value="sfs_cleanup_orphan">
                        <?php wp_nonce_field('sfs_cleanup_nonce', 'sfs_nonce'); ?>
                        <button type="submit" class="bg-yellow-600 hover:bg-yellow-700 text-white font-bold py-2 px-4 rounded transition">
                            <?php _e('Delete Orphan Folders', 'simple-secure-file-share'); ?>
                        </button>
                    </form>
                </div>
                <?php endif; ?>
                
                <!-- 孤立レコードのクリーンアップ -->
                <?php if (!empty($orphan_records)): ?>
                <div class="bg-red-50 p-6 rounded-lg border border-red-300 mb-6">
                    <h2 class="text-lg text-red-800 mb-2 flex items-center gap-2">
                        <span class="dashicons dashicons-warning"></span>
                        <?php _e('Orphan records found', 'simple-secure-file-share'); ?>
                    </h2>
                    <p class="text-red-700 text-sm mb-4">
                        <?php _e('The following database records have no corresponding file folder.', 'simple-secure-file-share'); ?><br>
                        <?php _e('Files may have been manually deleted or upload failed.', 'simple-secure-file-share'); ?>
                    </p>
                    <ul class="text-sm text-red-800 mb-4 max-h-40 overflow-y-auto bg-white p-3 rounded border">
                        <?php foreach ($orphan_records as $record): ?>
                        <li class="py-1 border-b border-red-100 last:border-0">
                            ID: <?php echo $record->ID; ?> - <strong><?php echo esc_html($record->post_title); ?></strong>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <form action="<?php echo admin_url('admin-post.php'); ?>" method="post" onsubmit="return confirm('<?php echo esc_js(__('Orphan records will be deleted from the database. This cannot be undone. Continue?', 'simple-secure-file-share')); ?>');">
                        <input type="hidden" name="action" value="sfs_cleanup_db">
                        <?php wp_nonce_field('sfs_cleanup_nonce', 'sfs_nonce'); ?>
                        <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded transition">
                            <?php _e('Delete Orphan Records', 'simple-secure-file-share'); ?>
                        </button>
                    </form>
                </div>
                <?php endif; ?>
                
                <?php if (empty($orphan_folders) && empty($orphan_records)): ?>
                <div class="bg-green-50 p-6 rounded-lg border border-green-300 mb-6">
                    <h2 class="text-lg text-green-800 mb-2 flex items-center gap-2">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php _e('No issues found', 'simple-secure-file-share'); ?>
                    </h2>
                    <p class="text-green-700 text-sm">
                        <?php _e('Database and file system are properly synchronized.', 'simple-secure-file-share'); ?>
                    </p>
                </div>
                <?php endif; ?>
                
                <!-- データベース内容一覧 -->
                <h2 class="text-xl mb-4 text-gray-700 flex items-center gap-2">
                    <span class="dashicons dashicons-database"></span>
                    <?php _e('Share Information in Database', 'simple-secure-file-share'); ?>
                </h2>
                
                <?php if (empty($shares)): ?>
                    <p class="text-gray-500"><?php _e('No share information registered.', 'simple-secure-file-share'); ?></p>
                <?php else: ?>
                    <div class="overflow-x-auto -mx-6 px-6">
                        <table class="w-full text-sm text-left text-gray-500 border-collapse" style="min-width: 900px;">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-100">
                                <tr>
                                    <th class="px-3 py-2 border-b">ID</th>
                                    <th class="px-3 py-2 border-b" style="max-width: 150px;"><?php _e('Title', 'simple-secure-file-share'); ?></th>
                                    <th class="px-3 py-2 border-b"><?php _e('Token', 'simple-secure-file-share'); ?></th>
                                    <th class="px-3 py-2 border-b text-center"><?php _e('Files Exist', 'simple-secure-file-share'); ?></th>
                                    <th class="px-3 py-2 border-b text-center"><?php _e('Password', 'simple-secure-file-share'); ?></th>
                                    <th class="px-3 py-2 border-b text-center"><?php _e('DL Count', 'simple-secure-file-share'); ?></th>
                                    <th class="px-3 py-2 border-b"><?php _e('Created', 'simple-secure-file-share'); ?></th>
                                    <th class="px-3 py-2 border-b"><?php _e('Status', 'simple-secure-file-share'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($shares as $share): 
                                    $token = get_post_meta($share->ID, 'sfs_token', true);
                                    $files = get_post_meta($share->ID, 'sfs_files', true);
                                    $has_password = get_post_meta($share->ID, 'sfs_password_hash', true);
                                    $download_count = (int)get_post_meta($share->ID, 'sfs_download_count', true);
                                    $folder_exists = is_dir($this->get_upload_dir() . $token);
                                ?>
                                <tr class="bg-white border-b hover:bg-gray-50">
                                    <td class="px-3 py-2 font-mono text-xs"><?php echo $share->ID; ?></td>
                                    <td class="px-3 py-2" style="max-width: 150px;">
                                        <div class="break-words text-gray-900 font-medium" title="<?php echo esc_attr($share->post_title); ?>">
                                            <?php echo esc_html($share->post_title); ?>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 font-mono text-xs text-gray-500"><?php echo esc_html($token ? substr($token, 0, 16) . '...' : '-'); ?></td>
                                    <td class="px-3 py-2 text-center">
                                        <?php if ($folder_exists): ?>
                                            <span class="text-green-600"><span class="dashicons dashicons-yes"></span></span>
                                        <?php else: ?>
                                            <span class="text-red-600"><span class="dashicons dashicons-no"></span></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <?php if ($has_password): ?>
                                            <span class="text-green-600"><span class="dashicons dashicons-lock"></span></span>
                                        <?php else: ?>
                                            <span class="text-gray-400">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 py-2 text-center font-bold"><?php echo $download_count; ?></td>
                                    <td class="px-3 py-2 text-xs whitespace-nowrap"><?php echo get_the_date('Y/m/d H:i', $share->ID); ?></td>
                                    <td class="px-3 py-2">
                                        <span class="px-2 py-1 rounded text-xs <?php echo $share->post_status === 'publish' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                                            <?php 
                                            if ($share->post_status === 'publish') {
                                                _e('Published', 'simple-secure-file-share');
                                            } elseif ($share->post_status === 'draft') {
                                                _e('Draft', 'simple-secure-file-share');
                                            } else {
                                                echo esc_html($share->post_status);
                                            }
                                            ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                
                <!-- アップロードフォルダのパス表示 -->
                <div class="mt-8 p-4 bg-gray-50 rounded border">
                    <h3 class="text-sm font-bold text-gray-700 mb-2"><?php _e('Upload Folder Path', 'simple-secure-file-share'); ?></h3>
                    <code class="text-xs bg-gray-200 p-2 rounded block break-all"><?php echo esc_html($this->get_upload_dir()); ?></code>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * 孤立フォルダのクリーンアップ
     */
    public function handle_cleanup_orphan() {
        if (!current_user_can('manage_options')) wp_die(__('Permission denied.', 'simple-secure-file-share'));
        check_admin_referer('sfs_cleanup_nonce', 'sfs_nonce');
        
        // DBに登録されているトークン一覧を取得
        $shares = get_posts(array(
            'post_type' => 'sfs_share',
            'posts_per_page' => -1,
            'post_status' => 'any',
        ));
        
        $db_tokens = array();
        foreach ($shares as $share) {
            $token = get_post_meta($share->ID, 'sfs_token', true);
            if ($token) {
                $db_tokens[] = $token;
            }
        }
        
        // アップロードフォルダ内のディレクトリを走査
        if (is_dir($this->get_upload_dir())) {
            $dirs = glob($this->get_upload_dir() . '*', GLOB_ONLYDIR);
            foreach ($dirs as $dir) {
                $folder_name = basename($dir);
                if (!in_array($folder_name, $db_tokens)) {
                    // 孤立フォルダを削除
                    $this->delete_directory($dir);
                }
            }
        }
        
        wp_redirect(admin_url('admin.php?page=simple-file-share-advanced&cleaned=orphan'));
        exit;
    }
    
    /**
     * 孤立レコードのクリーンアップ
     */
    public function handle_cleanup_db() {
        if (!current_user_can('manage_options')) wp_die(__('Permission denied.', 'simple-secure-file-share'));
        check_admin_referer('sfs_cleanup_nonce', 'sfs_nonce');
        
        // アップロードフォルダ内のディレクトリ一覧を取得
        $upload_folders = array();
        if (is_dir($this->get_upload_dir())) {
            $dirs = glob($this->get_upload_dir() . '*', GLOB_ONLYDIR);
            foreach ($dirs as $dir) {
                $upload_folders[] = basename($dir);
            }
        }
        
        // 全ての共有データを取得
        $shares = get_posts(array(
            'post_type' => 'sfs_share',
            'posts_per_page' => -1,
            'post_status' => 'any',
        ));
        
        foreach ($shares as $share) {
            $token = get_post_meta($share->ID, 'sfs_token', true);
            if ($token && !in_array($token, $upload_folders)) {
                // フォルダがない孤立レコードを削除
                wp_delete_post($share->ID, true);
            }
        }
        
        wp_redirect(admin_url('admin.php?page=simple-file-share-advanced&cleaned=db'));
        exit;
    }
    
    /**
     * ディレクトリを再帰的に削除
     */
    private function delete_directory($dir) {
        $dir = untrailingslashit($dir);
        // シンボリックリンク先は辿らずリンク自体のみ削除
        if (is_link($dir)) {
            @unlink($dir);
            return;
        }
        if (!is_dir($dir)) return;
        
        $files = array_diff(scandir($dir) ?: array(), array('.', '..'));
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path) && !is_link($path)) {
                $this->delete_directory($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
    
    /**
     * 使い方ページ
     */
    public function render_howto_page() {
        if (!current_user_can('manage_options')) return;
        
        ?>
        <div class="wrap">
            <style>
                .sfs-howto h1, .sfs-howto h2, .sfs-howto h3 { margin-bottom: 0.5em; font-weight: bold; }
                .sfs-howto ul { list-style: disc; padding-left: 1.5em; }
                .sfs-howto ol { list-style: decimal; padding-left: 1.5em; }
                .sfs-howto li { margin-bottom: 0.5em; }
                .sfs-howto code { background: #f3f4f6; padding: 2px 6px; border-radius: 4px; font-family: monospace; }
            </style>
            
            <div class="sfs-howto p-6 max-w-4xl mx-auto bg-white shadow-lg rounded-lg mt-5">
                <h1 class="text-2xl mb-6 text-gray-800 flex items-center gap-2">
                    <span class="dashicons dashicons-book text-blue-500" style="font-size:28px;width:28px;height:28px;"></span>
                    <?php _e('How to Use', 'simple-secure-file-share'); ?> - Simple Secure File Share
                </h1>
                
                <div class="mb-8 p-4 bg-blue-50 rounded-lg border border-blue-200">
                    <p class="text-blue-800">
                        <strong><?php _e('Version:', 'simple-secure-file-share'); ?></strong> 4.0.0 | 
                        <strong><?php _e('Author:', 'simple-secure-file-share'); ?></strong> AI Generator, Direction：Lichiphen（<a href="https://x.com/Lichiphen" target="_blank" class="text-blue-600 hover:underline">@Lichiphen</a>）
                    </p>
                </div>
                
                <!-- 概要 -->
                <section class="mb-8">
                    <h2 class="text-xl text-gray-700 flex items-center gap-2 border-b pb-2">
                        <span class="dashicons dashicons-info"></span>
                        <?php _e('Overview', 'simple-secure-file-share'); ?>
                    </h2>
                    <p class="text-gray-600 mt-4">
                        <?php _e('"Simple Secure File Share" is a plugin for securely sharing files on WordPress sites.', 'simple-secure-file-share'); ?><br>
                        <?php _e('When an administrator uploads files, a dedicated share URL is generated.', 'simple-secure-file-share'); ?>
                    </p>
                </section>
                
                <!-- 主な機能 -->
                <section class="mb-8">
                    <h2 class="text-xl text-gray-700 flex items-center gap-2 border-b pb-2">
                        <span class="dashicons dashicons-star-filled text-yellow-500"></span>
                        <?php _e('Key Features', 'simple-secure-file-share'); ?>
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div class="p-4 bg-gray-50 rounded border">
                            <h3 class="font-bold text-gray-800 flex items-center gap-2">
                                <span class="dashicons dashicons-lock text-green-600"></span>
                                <?php _e('Password Protection', 'simple-secure-file-share'); ?>
                            </h3>
                            <p class="text-gray-600 text-sm mt-1"><?php _e('Set a password on share links for authorized-only access', 'simple-secure-file-share'); ?></p>
                        </div>
                        <div class="p-4 bg-gray-50 rounded border">
                            <h3 class="font-bold text-gray-800 flex items-center gap-2">
                                <span class="dashicons dashicons-chart-bar text-blue-600"></span>
                                <?php _e('Download Counter', 'simple-secure-file-share'); ?>
                            </h3>
                            <p class="text-gray-600 text-sm mt-1"><?php _e('Automatically count downloads to track usage', 'simple-secure-file-share'); ?></p>
                        </div>
                        <div class="p-4 bg-gray-50 rounded border">
                            <h3 class="font-bold text-gray-800 flex items-center gap-2">
                                <span class="dashicons dashicons-download text-purple-600"></span>
                                <?php _e('ZIP Download', 'simple-secure-file-share'); ?>
                            </h3>
                            <p class="text-gray-600 text-sm mt-1"><?php _e('Bulk download multiple files as ZIP (supports Japanese filenames)', 'simple-secure-file-share'); ?></p>
                        </div>
                        <div class="p-4 bg-gray-50 rounded border">
                            <h3 class="font-bold text-gray-800 flex items-center gap-2">
                                <span class="dashicons dashicons-shield text-red-600"></span>
                                <?php _e('Direct Link Prevention', 'simple-secure-file-share'); ?>
                            </h3>
                            <p class="text-gray-600 text-sm mt-1"><?php _e('Prevent unauthorized downloads via direct URL access', 'simple-secure-file-share'); ?></p>
                        </div>
                    </div>
                </section>
                
                <!-- 使い方 -->
                <section class="mb-8">
                    <h2 class="text-xl text-gray-700 flex items-center gap-2 border-b pb-2">
                        <span class="dashicons dashicons-welcome-learn-more text-indigo-500"></span>
                        <?php _e('Usage Instructions', 'simple-secure-file-share'); ?>
                    </h2>
                    
                    <div class="mt-4 space-y-6">
                        <div class="p-4 bg-green-50 rounded border border-green-200">
                            <h3 class="font-bold text-green-800"><?php _e('Step 1: Upload Files', 'simple-secure-file-share'); ?></h3>
                            <ol class="text-gray-700 mt-2">
                                <li><?php _e('Click "File Share" from the WordPress admin sidebar', 'simple-secure-file-share'); ?></li>
                                <li><?php _e('Enter "Share Title" (e.g., "Documents for December 2024")', 'simple-secure-file-share'); ?></li>
                                <li><?php _e('Optionally enable "Password Protection"', 'simple-secure-file-share'); ?></li>
                                <li><?php _e('Drag & drop files to the file selection area or click to select', 'simple-secure-file-share'); ?></li>
                                <li><?php _e('Click "Upload and Create Share Link"', 'simple-secure-file-share'); ?></li>
                            </ol>
                        </div>
                        
                        <div class="p-4 bg-blue-50 rounded border border-blue-200">
                            <h3 class="font-bold text-blue-800"><?php _e('Step 2: Share the Link', 'simple-secure-file-share'); ?></h3>
                            <ul class="text-gray-700 mt-2">
                                <li><?php _e('Click "Copy" button to copy the share URL', 'simple-secure-file-share'); ?></li>
                                <li><?php _e('Send via email or chat', 'simple-secure-file-share'); ?></li>
                                <li><?php _e('If password is set, share the password as well', 'simple-secure-file-share'); ?></li>
                            </ul>
                        </div>
                        
                        <div class="p-4 bg-purple-50 rounded border border-purple-200">
                            <h3 class="font-bold text-purple-800"><?php _e('Step 3: Recipient Downloads', 'simple-secure-file-share'); ?></h3>
                            <ul class="text-gray-700 mt-2">
                                <li><?php _e('Recipient accesses the share URL', 'simple-secure-file-share'); ?></li>
                                <li><?php _e('Enter password if protected', 'simple-secure-file-share'); ?></li>
                                <li><?php _e('Download as ZIP or individual files', 'simple-secure-file-share'); ?></li>
                            </ul>
                        </div>
                    </div>
                </section>
                
                <!-- 高度な設定について -->
                <section class="mb-8">
                    <h2 class="text-xl text-gray-700 flex items-center gap-2 border-b pb-2">
                        <span class="dashicons dashicons-admin-tools text-orange-500"></span>
                        <?php _e('About Advanced Settings', 'simple-secure-file-share'); ?>
                    </h2>
                    <div class="mt-4 text-gray-600">
                        <p class="mb-4"><?php _e('The "Advanced Settings" page allows you to check data consistency:', 'simple-secure-file-share'); ?></p>
                        
                        <div class="p-4 bg-yellow-50 rounded border border-yellow-200 mb-4">
                            <h3 class="font-bold text-yellow-800"><?php _e('Orphan Folders', 'simple-secure-file-share'); ?></h3>
                            <p class="text-sm mt-1">
                                <?php _e('Files exist on server but are not registered in the database.', 'simple-secure-file-share'); ?><br>
                                <?php _e('May have been created by an older version or database was manually deleted.', 'simple-secure-file-share'); ?>
                            </p>
                        </div>
                        
                        <div class="p-4 bg-red-50 rounded border border-red-200">
                            <h3 class="font-bold text-red-800"><?php _e('Orphan Records', 'simple-secure-file-share'); ?></h3>
                            <p class="text-sm mt-1">
                                <?php _e('Registered in database but actual files do not exist.', 'simple-secure-file-share'); ?><br>
                                <?php _e('Files may have been manually deleted or upload failed.', 'simple-secure-file-share'); ?>
                            </p>
                        </div>
                    </div>
                </section>
                
                <!-- セキュリティ機能 -->
                <section class="mb-8">
                    <h2 class="text-xl text-gray-700 flex items-center gap-2 border-b pb-2">
                        <span class="dashicons dashicons-shield-alt text-green-600"></span>
                        <?php _e('Security Features', 'simple-secure-file-share'); ?>
                    </h2>
                    <ul class="mt-4 text-gray-600">
                        <li><strong><?php _e('Direct Access Prevention:', 'simple-secure-file-share'); ?></strong> <?php _e('The protected-uploads/ folder cannot be accessed externally', 'simple-secure-file-share'); ?></li>
                        <li><strong><?php _e('Password Encryption:', 'simple-secure-file-share'); ?></strong> <?php _e('Passwords are stored hashed', 'simple-secure-file-share'); ?></li>
                        <li><strong><?php _e('Cookie Authentication:', 'simple-secure-file-share'); ?></strong> <?php _e('Password authentication is valid for 1 hour', 'simple-secure-file-share'); ?></li>
                        <li><strong><?php _e('Referer Check:', 'simple-secure-file-share'); ?></strong> <?php _e('Prevents downloads from outside the share page', 'simple-secure-file-share'); ?></li>
                    </ul>
                </section>
                
                <!-- ライセンス -->
                <section class="mb-4">
                    <h2 class="text-xl text-gray-700 flex items-center gap-2 border-b pb-2">
                        <span class="dashicons dashicons-media-text"></span>
                        <?php _e('License', 'simple-secure-file-share'); ?>
                    </h2>
                    <div class="mt-4 p-4 bg-gray-50 rounded border">
                        <p class="text-gray-700 font-bold">Lichiphen Proprietary License v1.0</p>
                        <ul class="text-gray-600 mt-2 text-sm">
                            <li>✅ <?php _e('Commercial use allowed', 'simple-secure-file-share'); ?></li>
                            <li>✅ <?php _e('Personal use allowed', 'simple-secure-file-share'); ?></li>
                            <li>✅ <?php _e('Modification allowed', 'simple-secure-file-share'); ?></li>
                            <li>⚠️ <?php _e('Copyright notice required when redistributing', 'simple-secure-file-share'); ?></li>
                            <li>❌ <?php _e('Removal of copyright notice prohibited', 'simple-secure-file-share'); ?></li>
                        </ul>
                        <p class="text-gray-500 text-xs mt-3">
                            <?php _e('If you absolutely need to remove the copyright notice, we can arrange this for a fee.', 'simple-secure-file-share'); ?><br>
                            <?php _e('Please contact us via X (Twitter) or the website.', 'simple-secure-file-share'); ?>
                        </p>
                        <p class="text-gray-500 text-xs mt-2">Copyright (c) 2025 Lichiphen. All rights reserved.</p>
                    </div>
                </section>
            </div>
        </div>
        <?php
    }
}

new Simple_File_Share();