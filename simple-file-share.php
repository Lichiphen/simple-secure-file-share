<?php
/**
 * Plugin Name: Simple Secure File Share
 * Plugin URI: https://github.com/Lichiphen/simple-secure-file-share
 * Description: 外部共有用にファイルを安全にアップロードし、専用URLを発行するプラグイン。ファイルは直接アクセスから保護され、Zipダウンロードに対応しています。
 * Version: 3.1.1
 * Author: AI Generator, Direction：Lichiphen（X@Lichiphen）
 * Author URI: https://lichiphen.com
 * License: Lichiphen Proprietary License v1.0
 * License URI: https://github.com/Lichiphen/simple-secure-file-share/blob/main/LICENSE
 * Text Domain: simple-secure-file-share
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 8.4
 * 
 * @package SimpleSecureFileShare
 * @copyright 2025 Lichiphen. All rights reserved.
 * 
 * ============================================================================
 * LICHIPHEN PROPRIETARY LICENSE v1.0
 * ============================================================================
 * 
 * Copyright (c) 2025 Lichiphen. All rights reserved.
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

    private $upload_dir;
    private $share_slug = 'sfs-share';

    public function __construct() {
        // アップロードディレクトリのパス定義 (プラグインフォルダ内)
        $this->upload_dir = plugin_dir_path(__FILE__) . 'protected-uploads/';

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
        
        // 多言語対応
        add_action('plugins_loaded', array($this, 'load_textdomain'));
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
        if (!file_exists($this->upload_dir)) {
            mkdir($this->upload_dir, 0755, true);
        }

        $htaccess_path = $this->upload_dir . '.htaccess';
        if (!file_exists($htaccess_path)) {
            $rules = "Order Deny,Allow\nDeny from all";
            file_put_contents($htaccess_path, $rules);
        }

        $index_path = $this->upload_dir . 'index.php';
        if (!file_exists($index_path)) {
            file_put_contents($index_path, '<?php // Silence is golden.');
        }

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
        
        $shares = get_posts(array(
            'post_type' => 'sfs_share',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ));

        ?>
        <div class="wrap">
            <script src="https://cdn.tailwindcss.com"></script>
            <style>
                .sfs-app h1, .sfs-app h2, .sfs-app h3 { margin-bottom: 0.5em; font-weight: bold; }
                .sfs-app input[type=text], .sfs-app input[type=password] { border: 1px solid #ccc; padding: 8px; width: 100%; border-radius: 4px; }
                .sfs-drag-over { background-color: #eff6ff !important; border-color: #3b82f6 !important; }
                
                /* パスワードマスク用CSS (text-security) */
                .sfs-masked {
                    -webkit-text-security: disc;
                    text-security: disc;
                    font-family: text-security-disc, sans-serif; /* Fallback attempt */
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
                            <div id="sfs-file-list-container" class="mt-4 hidden">
                                <div class="flex justify-between items-center mb-2">
                                    <p class="text-sm font-bold text-gray-700"><?php _e('Files waiting to upload:', 'simple-secure-file-share'); ?></p>
                                    <button type="button" id="sfs-clear-all" class="text-xs text-red-500 hover:underline"><?php _e('Clear all', 'simple-secure-file-share'); ?></button>
                                </div>
                                <ul id="sfs-file-list" class="space-y-2 max-h-60 overflow-y-auto pr-2"></ul>
                            </div>
                        </div>

                        <!-- プログレスバー -->
                        <div id="sfs-progress-wrapper" class="hidden mt-4">
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
                    <div class="overflow-x-auto -mx-6 px-6">
                        <table class="w-full text-sm text-left text-gray-500 border-collapse" style="min-width: 800px;">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-100">
                                <tr>
                                    <th class="px-4 py-3 border-b" style="min-width: 120px; max-width: 150px;"><?php _e('Title', 'simple-secure-file-share'); ?></th>
                                    <th class="px-4 py-3 border-b whitespace-nowrap"><?php _e('Status', 'simple-secure-file-share'); ?></th>
                                    <th class="px-4 py-3 border-b" style="min-width: 280px;"><?php _e('Share URL', 'simple-secure-file-share'); ?></th>
                                    <th class="px-4 py-3 border-b whitespace-nowrap text-center"><?php _e('Files', 'simple-secure-file-share'); ?></th>
                                    <th class="px-4 py-3 border-b whitespace-nowrap text-center"><?php _e('DL Count', 'simple-secure-file-share'); ?></th>
                                    <th class="px-4 py-3 border-b whitespace-nowrap"><?php _e('Created', 'simple-secure-file-share'); ?></th>
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
                                    <td class="px-4 py-4 text-center">
                                        <form action="<?php echo admin_url('admin-post.php'); ?>" method="post" onsubmit="return confirm('<?php echo esc_js(__('Are you sure you want to delete? Uploaded files will also be deleted.', 'simple-secure-file-share')); ?>');">
                                            <input type="hidden" name="action" value="sfs_delete_share">
                                            <input type="hidden" name="share_id" value="<?php echo $share->ID; ?>">
                                            <?php wp_nonce_field('sfs_delete_nonce', 'sfs_nonce'); ?>
                                            <button type="submit" class="text-red-600 hover:text-red-900 font-bold whitespace-nowrap"><?php _e('Delete', 'simple-secure-file-share'); ?></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
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
        // 管理画面用トースト表示関数
        function showAdminToast(message) {
            const existingToast = document.querySelector('.sfs-toast');
            if (existingToast) existingToast.remove();

            const toast = document.createElement('div');
            toast.className = 'sfs-toast';
            toast.innerHTML = `<span class="dashicons dashicons-yes" style="color:#4ade80;"></span> ${message}`;
            document.body.appendChild(toast);

            setTimeout(() => {
                toast.classList.add('hiding');
                toast.addEventListener('animationend', () => toast.remove());
            }, 3000);
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

            const ajaxUrl = "<?php echo admin_url('admin-ajax.php'); ?>";
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
                const length = 12;
                const charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+";
                let retVal = "";
                const types = ["abcdefghijklmnopqrstuvwxyz", "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "0123456789", "!@#$%^&*()_+"];
                
                types.forEach(t => {
                    retVal += t.charAt(Math.floor(Math.random() * t.length));
                });
                for (let i = 0, n = charset.length; i < length - types.length; ++i) {
                    retVal += charset.charAt(Math.floor(Math.random() * n));
                }
                retVal = retVal.split('').sort(function(){return 0.5-Math.random()}).join('');

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
                    fileListContainer.classList.add('hidden');
                    return;
                }
                fileListContainer.classList.remove('hidden');

                allFiles.forEach((file, index) => {
                    const li = document.createElement('li');
                    li.className = 'flex items-center justify-between bg-white border border-gray-200 p-2 rounded text-sm shadow-sm';
                    const size = (file.size / 1024).toFixed(1) + ' KB';
                    
                    li.innerHTML = `
                        <div class="flex items-center space-x-2 truncate">
                            <span class="dashicons dashicons-media-default text-gray-400"></span>
                            <span class="font-medium text-gray-700 truncate" title="${file.name}">${file.name}</span>
                            <span class="text-xs text-gray-400">(${size})</span>
                        </div>
                        <button type="button" class="text-gray-400 hover:text-red-500 p-1 rounded hover:bg-red-50 transition" onclick="removeFile(${index})" title="削除">
                            <span class="dashicons dashicons-dismiss"></span>
                        </button>
                    `;
                    fileList.appendChild(li);
                });
            }

            window.removeFile = function(index) {
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
                progressWrapper.classList.remove('hidden');
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
                                // 完了モーダル表示
                                progressBar.style.width = '100%';
                                progressText.innerText = '100%';
                                setTimeout(() => {
                                    successModal.classList.remove('hidden');
                                }, 300);
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
                progressWrapper.classList.add('hidden');
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
        $title = sanitize_text_field($post_data['share_title']);
        $files = $files_data['files'];
        // パスワード取得
        $password = isset($post_data['share_password']) ? $post_data['share_password'] : '';

        if (empty($title) || empty($files['name'][0])) {
            return new WP_Error('missing_data', 'タイトルとファイルは必須です。');
        }

        // トークン生成
        $token = bin2hex(random_bytes(16));
        $target_dir = $this->upload_dir . $token . '/';
        
        if (!mkdir($target_dir, 0755, true)) {
            return new WP_Error('dir_error', 'ディレクトリ作成に失敗しました。');
        }

        $saved_files = array();
        $count = count($files['name']);
        
        for ($i = 0; $i < $count; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $filename = sanitize_file_name($files['name'][$i]);
                $original_name = $files['name'][$i];
                $destination = $target_dir . $filename;
                
                // 重複回避
                $counter = 1;
                $path_info = pathinfo($destination);
                while(file_exists($destination)) {
                    $filename = $path_info['filename'] . '_' . $counter . '.' . $path_info['extension'];
                    $destination = $target_dir . $filename;
                    $counter++;
                }

                if (move_uploaded_file($files['tmp_name'][$i], $destination)) {
                    $saved_files[] = array(
                        'system_name' => $filename,
                        'original_name' => $original_name, // 表示用
                        'size' => $files['size'][$i]
                    );
                }
            }
        }

        if (empty($saved_files)) {
            rmdir($target_dir);
            return new WP_Error('upload_failed', 'ファイルの保存に失敗しました。');
        }

        // 投稿作成
        $post_id = wp_insert_post(array(
            'post_title' => $title,
            'post_type'  => 'sfs_share',
            'post_status' => 'publish'
        ));

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        update_post_meta($post_id, 'sfs_token', $token);
        update_post_meta($post_id, 'sfs_files', $saved_files);
        
        // パスワード保存
        if (!empty($password)) {
            require_once ABSPATH . WPINC . '/class-phpass.php';
            $wp_hasher = new PasswordHash(8, true);
            $hash = $wp_hasher->HashPassword(trim($password));
            update_post_meta($post_id, 'sfs_password_hash', $hash);
        }

        return true;
    }

    /**
     * POST処理: 削除
     */
    public function handle_delete_share() {
        if (!current_user_can('manage_options')) wp_die('権限がありません。');
        check_admin_referer('sfs_delete_nonce', 'sfs_nonce');

        $share_id = intval($_POST['share_id']);
        $token = get_post_meta($share_id, 'sfs_token', true);

        if ($token) {
            $dir = $this->upload_dir . $token . '/';
            if (is_dir($dir)) {
                $files = glob($dir . '*', GLOB_MARK);
                foreach ($files as $file) {
                    unlink($file);
                }
                rmdir($dir);
            }
        }

        wp_delete_post($share_id, true);

        wp_redirect(admin_url('admin.php?page=simple-file-share'));
        exit;
    }

    /**
     * フロントエンド: 共有ページの表示とダウンロード処理
     */
    public function handle_frontend_view() {
        $token = get_query_var('sfs_token');
        if (!$token) return;

        $args = array(
            'post_type' => 'sfs_share',
            'meta_key' => 'sfs_token',
            'meta_value' => $token,
            'posts_per_page' => 1
        );
        $query = new WP_Query($args);

        if (!$query->have_posts()) {
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            return;
        }

        $query->the_post();
        $post_id = get_the_ID();
        $files = get_post_meta($post_id, 'sfs_files', true);
        $title = get_the_title();
        
        // パスワードチェック処理
        $password_hash = get_post_meta($post_id, 'sfs_password_hash', true);
        if ($password_hash) {
            $this->check_password_auth($post_id, $token, $password_hash, $title);
        }

        // ダウンロード処理
        if (isset($_GET['download'])) {
            // 直リンク防止: ダウンロードトークンのチェック
            $dl_token = isset($_GET['dl_token']) ? sanitize_text_field($_GET['dl_token']) : '';
            $expected_token = wp_hash($post_id . $token . date('Y-m-d'));
            
            // リファラーチェック（共有ページからのアクセスかどうか）
            $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
            $share_url = home_url('/' . $this->share_slug . '/' . $token);
            $is_valid_referer = (strpos($referer, $share_url) === 0);
            
            // トークンが正しくない、かつリファラーも不正な場合はエラー
            if ($dl_token !== $expected_token && !$is_valid_referer) {
                wp_die(__('Invalid access. Please download from the share page.', 'simple-secure-file-share'), __('Access Denied', 'simple-secure-file-share'), array('response' => 403));
            }
            
            $target = sanitize_file_name($_GET['download']);
            if ($target === 'zip') {
                // ダウンロードカウントを増加
                $current_count = (int)get_post_meta($post_id, 'sfs_download_count', true);
                update_post_meta($post_id, 'sfs_download_count', $current_count + 1);
                
                $this->download_as_zip($token, $files, $title);
            } else {
                // 個別ダウンロード
                $valid_file = false;
                $original_name = '';
                foreach ($files as $f) {
                    if ($f['system_name'] === $target) {
                        $valid_file = true;
                        $original_name = $f['original_name'];
                        break;
                    }
                }

                if ($valid_file) {
                    $file_path = $this->upload_dir . $token . '/' . $target;
                    if (file_exists($file_path)) {
                        // ダウンロードカウントを増加
                        $current_count = (int)get_post_meta($post_id, 'sfs_download_count', true);
                        update_post_meta($post_id, 'sfs_download_count', $current_count + 1);
                        
                        header('Content-Description: File Transfer');
                        header('Content-Type: application/octet-stream');
                        $encoded_filename = rawurlencode($original_name);
                        header("Content-Disposition: attachment; filename=\"{$encoded_filename}\"; filename*=UTF-8''{$encoded_filename}");
                        header('Content-Transfer-Encoding: binary');
                        header('Expires: 0');
                        header('Cache-Control: must-revalidate');
                        header('Pragma: public');
                        header('Content-Length: ' . filesize($file_path));
                        readfile($file_path);
                        exit;
                    }
                }
            }
        }

        $this->render_frontend_html($title, $files, $token);
        exit;
    }

    /**
     * パスワード認証ロジック
     */
    private function check_password_auth($post_id, $token, $stored_hash, $title) {
        $cookie_name = 'sfs_auth_' . $token;
        $auth_token = wp_hash($post_id . $token . 'sfs_secret'); // 簡易的な認証トークン

        // 1. POST送信時のチェック
        if (isset($_POST['sfs_password'])) {
            require_once ABSPATH . WPINC . '/class-phpass.php';
            $wp_hasher = new PasswordHash(8, true);
            
            if ($wp_hasher->CheckPassword(trim($_POST['sfs_password']), $stored_hash)) {
                // 認証成功: Cookieセット (1時間有効)
                setcookie($cookie_name, $auth_token, time() + 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
                // リロードしてPOSTデータをクリア
                wp_redirect(remove_query_arg('sfs_password_error'));
                exit;
            } else {
                // 認証失敗
                $this->render_password_form($title, true);
                exit;
            }
        }

        // 2. Cookieチェック
        if (isset($_COOKIE[$cookie_name]) && $_COOKIE[$cookie_name] === $auth_token) {
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
            <script src="https://cdn.tailwindcss.com"></script>
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
                            <span class="block sm:inline"><?php _e('Incorrect password.', 'simple-secure-file-share'); ?></span>
                        </div>
                    <?php endif; ?>

                    <form method="post" class="space-y-4" autocomplete="off">
                        <div class="relative">
                            <label for="sfs-frontend-password" class="sr-only"><?php _e('Password', 'simple-secure-file-share'); ?></label>
                            <!-- type="text" で実装し、CSS(.sfs-masked)で隠す -->
                            <!-- autocomplete="off" を指定し、ブラウザの介入を最小限に -->
                            <input type="text" name="sfs_password" id="sfs-frontend-password" required 
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
        $zip = new ZipArchive();
        $zip_filename = tempnam(sys_get_temp_dir(), 'sfs_zip');
        
        if ($zip->open($zip_filename, ZipArchive::CREATE) !== TRUE) {
            die("Could not open archive");
        }

        $base_dir = $this->upload_dir . $token . '/';

        foreach ($files as $file) {
            $file_path = $base_dir . $file['system_name'];
            if (file_exists($file_path)) {
                $zip->addFile($file_path, $file['original_name']);
            }
        }
        $zip->close();

        if (file_exists($zip_filename)) {
            header('Content-Type: application/zip');
            $download_name = rawurlencode($title . '_files.zip');
            header("Content-Disposition: attachment; filename=\"{$download_name}\"; filename*=UTF-8''{$download_name}");
            header('Content-Length: ' . filesize($zip_filename));
            header('Pragma: no-cache'); 
            header('Expires: 0'); 
            readfile($zip_filename);
            unlink($zip_filename);
            exit;
        }
    }

    /**
     * フロントエンド画面のHTML
     */
    private function render_frontend_html($title, $files, $token) {
        $download_base = home_url('/' . $this->share_slug . '/' . $token);
        
        $total_size = 0;
        foreach($files as $f) $total_size += $f['size'];
        $formatted_total_size = size_format($total_size);

        // ダウンロードボタンのHTML生成（共通部分として定義）
        ob_start();
        ?>
        <?php if (count($files) > 1): ?>
        <a href="<?php echo esc_url(add_query_arg('download', 'zip', $download_base)); ?>" class="flex items-center space-x-2 bg-gray-900 hover:bg-black text-white px-6 py-3 rounded-lg shadow-lg transform hover:-translate-y-0.5 transition font-bold whitespace-nowrap">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
            <span><?php _e('Download as ZIP', 'simple-secure-file-share'); ?></span>
        </a>
        <?php else: ?>
        <a href="<?php echo esc_url(add_query_arg('download', $files[0]['system_name'], $download_base)); ?>" class="flex items-center space-x-2 bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg shadow-lg transform hover:-translate-y-0.5 transition font-bold whitespace-nowrap">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
            <span><?php _e('Download File', 'simple-secure-file-share'); ?></span>
        </a>
        <?php endif; ?>
        <?php
        $download_btn_html = ob_get_clean();

        ?>
        <!DOCTYPE html>
        <html lang="ja">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="robots" content="noindex, nofollow">
            <title><?php echo esc_html($title); ?> - <?php _e('File Share', 'simple-secure-file-share'); ?></title>
            <script src="https://cdn.tailwindcss.com"></script>
            <style>
                body { background-color: #f3f4f6; font-family: 'Helvetica Neue', Arial, sans-serif; }
            </style>
        </head>
        <body class="flex items-center justify-center min-h-screen p-4">

            <div class="bg-white w-full max-w-2xl rounded-xl shadow-2xl overflow-hidden">
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

                <!-- 7ファイル以上の場合、上部にもダウンロードボタンを表示 -->
                <?php if (count($files) >= 7): ?>
                <div class="p-4 bg-gray-50 border-b border-gray-200 flex justify-end">
                    <?php echo $download_btn_html; ?>
                </div>
                <?php endif; ?>

                <div class="p-6 bg-gray-50 border-b border-gray-100">
                    <ul class="space-y-3">
                        <?php foreach ($files as $file): 
                            $single_url = add_query_arg('download', $file['system_name'], $download_base);
                            $ext = strtolower(pathinfo($file['system_name'], PATHINFO_EXTENSION));
                            $icon_color = 'text-gray-400';
                            if(in_array($ext, ['jpg','png','gif'])) $icon_color = 'text-purple-500';
                            if(in_array($ext, ['pdf'])) $icon_color = 'text-red-500';
                            if(in_array($ext, ['zip'])) $icon_color = 'text-yellow-500';
                            if(in_array($ext, ['doc','docx'])) $icon_color = 'text-blue-500';
                            if(in_array($ext, ['xls','xlsx'])) $icon_color = 'text-green-500';
                        ?>
                        <li class="bg-white border border-gray-200 rounded-lg p-3 flex items-center justify-between hover:shadow-md transition">
                            <div class="flex items-center space-x-3 overflow-hidden">
                                <svg class="w-8 h-8 flex-shrink-0 <?php echo $icon_color; ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                <div class="min-w-0">
                                    <p class="text-gray-800 font-medium truncate" title="<?php echo esc_attr($file['original_name']); ?>"><?php echo esc_html($file['original_name']); ?></p>
                                    <p class="text-xs text-gray-500"><?php echo size_format($file['size']); ?></p>
                                </div>
                            </div>
                            <a href="<?php echo esc_url($single_url); ?>" class="ml-4 text-blue-600 hover:text-blue-800 bg-blue-50 hover:bg-blue-100 px-3 py-1 rounded text-sm font-medium transition whitespace-nowrap">
                                DL
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div class="p-6 bg-white flex flex-col md:flex-row justify-between items-center gap-4">
                    <div class="text-xs text-gray-400 text-center md:text-left">
                        <?php _e('If you have any questions, please feel free to contact us.', 'simple-secure-file-share'); ?>
                    </div>
                    <?php echo $download_btn_html; ?>
                </div>
            </div>

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
        if (is_dir($this->upload_dir)) {
            $dirs = glob($this->upload_dir . '*', GLOB_ONLYDIR);
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
            <script src="https://cdn.tailwindcss.com"></script>
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
                                    $folder_exists = is_dir($this->upload_dir . $token);
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
                    <code class="text-xs bg-gray-200 p-2 rounded block break-all"><?php echo esc_html($this->upload_dir); ?></code>
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
        if (is_dir($this->upload_dir)) {
            $dirs = glob($this->upload_dir . '*', GLOB_ONLYDIR);
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
        if (is_dir($this->upload_dir)) {
            $dirs = glob($this->upload_dir . '*', GLOB_ONLYDIR);
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
        if (!is_dir($dir)) return;
        
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->delete_directory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
    
    /**
     * 使い方ページ
     */
    public function render_howto_page() {
        if (!current_user_can('manage_options')) return;
        
        ?>
        <div class="wrap">
            <script src="https://cdn.tailwindcss.com"></script>
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
                        <strong><?php _e('Version:', 'simple-secure-file-share'); ?></strong> 3.1.1 | 
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