# Simple Secure File Share

[![Version](https://img.shields.io/badge/version-4.0.0-blue.svg)](https://gitlab.com/lichiphen/simple-secure-file-share)
[![License](https://img.shields.io/badge/license-Proprietary-orange.svg)](./LICENSE)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net)
[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)](https://wordpress.org)

**Author:** AI Generator, Direction: Lichiphen  
**Website:** [https://lichiphen.com](https://lichiphen.com) | **X (Twitter):** [@Lichiphen](https://x.com/Lichiphen)

---

# 🇬🇧 English

## 📋 Overview

"Simple Secure File Share" is a WordPress plugin for securely sharing files.

When an administrator uploads files, a dedicated sharing URL is generated. Files are protected from direct access and include features such as password protection, download counting, and ZIP downloads.

---

## ✨ Features

| Feature | Description |
|---------|-------------|
| 🔒 **Password Protection** | Set passwords on share links for authorized-only access |
| 🔑 **Password Reset** | Reset passwords for existing share links |
| 📊 **Download Counter** | Automatically count downloads to track usage |
| 📦 **ZIP Download** | Bulk download multiple files as ZIP (supports Japanese filenames) |
| 🛡️ **Direct Link Prevention** | Prevent unauthorized downloads via direct URL access |
| 🌙 **Dark Mode Support** | Automatic theme switching based on system preference |
| 🐱 **Cute Error Pages** | Friendly error pages with cat mascot animations |
| 📸 **Image Preview** | Preview image files before uploading |
| 📋 **List/Grid View** | Toggle between list and grid display modes |
| 🔄 **Sortable Tables** | Sort share lists by title, date, download count |
| 🗑️ **Individual File Deletion** | Delete files one by one after upload |
| 🔧 **Advanced Settings** | Check database and file system integrity |
| 📱 **Responsive Design** | Works great on PC and mobile devices |
| 🌐 **Multi-language** | English and Japanese support |

---

## 📥 Installation

1. Upload the plugin folder (`simple-file-share`) to `/wp-content/plugins/`
2. Activate the plugin through the "Plugins" menu in WordPress
3. "File Share" will be added to the admin side menu

---

## 🚀 How to Use

### Step 1: Upload Files

1. Click **"File Share"** from the WordPress admin sidebar
2. Enter a **"Share Title"** (e.g., "Documents for December 2024")
3. Optionally enable **"Password Protection"**
4. Drag & drop files to the file selection area or click to select
5. **Toggle list/grid view** to preview files (images show thumbnails)
6. Click **"Upload and Create Share Link"**
7. Your share URL will be displayed

### Step 2: Share the Link

- Click the **"Copy"** button to copy the share URL
- Send via email or chat
- If password is set, share the password as well

### Step 3: Recipient Downloads

- Recipient accesses the share URL
- Enter password if protected
- Download as ZIP or individual files

---

## 🔑 Password Management

### Setting a Password
- Enable password protection when creating a new share
- Use the auto-generate button for secure passwords

### Resetting a Password
1. Go to **File Share** → **Active Shares**
2. Click the **"Edit"** button next to the share
3. Enter a new password and save

---

## ⚙️ Advanced Settings

### Access

WordPress Admin → File Share → **"Advanced Settings"**

### Features

#### 📊 Statistics
- Registered Shares
- Upload Folders
- Orphan Folders
- Orphan Records

#### 🧹 Integrity Check

**Orphan Folders:**
- Files exist on server but are not registered in the database
- Can be safely deleted with the "Delete Orphan Folders" button

**Orphan Records:**
- Registered in database but actual files do not exist
- Clean up with the "Delete Orphan Records" button

#### 📋 Database Overview
- View all share information
- Check ID, Title, Token, File Status, Password, DL Count, Created Date, Status
- **Sortable columns** - Click headers to sort

---

## 🌙 Dark Mode Support

The plugin automatically detects the system's dark mode preference and adjusts the theme accordingly:

- **Light Mode**: Clean and bright interface
- **Dark Mode**: Eye-friendly dark interface

The theme switching is seamless and requires no manual configuration.

---

## 🐱 Cute Error Pages

When an error occurs (such as expired link, wrong password, or file not found), users will see a friendly error page featuring:

- 🐾 Animated cat mascot
- 📝 Clear error message in user's language
- 🔗 Helpful navigation options

---

## 📁 File Structure

```
simple-file-share/
├── simple-file-share.php    # Main plugin file
├── README.md                 # This file
├── CHANGELOG.md              # Change history
├── LICENSE                   # License file
├── assets/                   # CSS and JavaScript files
│   ├── css/
│   │   └── style.css         # All styles with CSS variables
│   ├── js/
│   │   └── app.js            # Frontend/Backend scripts
│   └── images/
│       └── cat-mascot.png    # Error page mascot
├── languages/                # Translation files
│   ├── simple-secure-file-share.pot
│   ├── simple-secure-file-share-en_US.po
│   ├── simple-secure-file-share-en_US.mo
│   ├── simple-secure-file-share-ja.po
│   └── simple-secure-file-share-ja.mo
└── protected-uploads/        # Upload file storage
    └── [token]/              # Folder for each share
        └── [files]           # Actual files
```

---

## 🎨 CSS Architecture

The CSS follows a modular architecture using CSS Custom Properties:

### CSS Variables

```css
:root {
  /* Colors */
  --sfs-color-accent: #3b82f6;
  --sfs-color-success: #10b981;
  --sfs-color-error: #ef4444;
  
  /* Backgrounds */
  --sfs-color-bg-primary: #f9fafb;
  --sfs-color-bg-secondary: #ffffff;
  
  /* Text */
  --sfs-color-text-primary: #1f2937;
  --sfs-color-text-secondary: #4b5563;
}

/* Dark Mode Override */
@media (prefers-color-scheme: dark) {
  :root {
    --sfs-color-bg-primary: #111827;
    --sfs-color-text-primary: #f9fafb;
  }
}
```

---

## 🔐 Security Features

### Direct Access Prevention
- `.htaccess` blocks external access to `protected-uploads/` folder
- Files can only be downloaded through the plugin

### Password Protection
- Passwords are stored hashed (bcrypt encryption)
- Cookie authentication valid for 1 hour
- Secure password input with autocomplete prevention

### Direct Link Prevention
- Referer check
- Daily token verification
- Prevents downloads via direct URL access

---

## 🌐 Internationalization (i18n)

The plugin supports full internationalization with the following languages:

| Language | Code | Status |
|----------|------|--------|
| English | `en_US` | ✅ Complete |
| Japanese | `ja` | ✅ Complete |

---

## 📝 Changelog

See [CHANGELOG.md](./CHANGELOG.md) for full version history.

### v4.0.0 (Latest)
- 🌙 Added Dark Mode support
- 🐱 Added cute error pages with cat mascot
- 📸 Added image preview in upload form
- 📋 Added list/grid view toggle
- 🔄 Added sortable table columns
- 🗑️ Added individual file deletion
- 🔑 Added password reset functionality
- 🎨 Restructured CSS architecture with CSS variables
- 📦 Modular assets organization
- 🌐 Enhanced i18n support (English/Japanese only)
- 🔧 PHP 7.4+ compatibility

---

## 🤝 Support

If you encounter issues, please check:

1. Plugin is up to date
2. WordPress 5.0+ and PHP 7.4+
3. Run integrity check in "Advanced Settings"

**Contact:**
- Website: [https://lichiphen.com](https://lichiphen.com)
- X (Twitter): [@Lichiphen](https://x.com/Lichiphen)

---

## ⚖️ License

Lichiphen Proprietary License v1.0

- ✅ Commercial use allowed
- ✅ Personal use allowed
- ✅ Modification allowed
- ⚠️ Copyright notice required when redistributing
- ❌ Removal of copyright notice prohibited

Copyright (c) 2025-2026 Lichiphen. All rights reserved.

---
---

# 🇯🇵 日本語

## 📋 概要

「Simple Secure File Share」は、WordPressサイトでファイルを安全に共有するためのプラグインです。

管理者がファイルをアップロードすると、専用の共有URLが発行されます。ファイルは直接アクセスから保護され、パスワード保護、ダウンロード回数のカウント、ZIPダウンロードなどの機能を備えています。

---

## ✨ 主な機能

| 機能 | 説明 |
|------|------|
| 🔒 **パスワード保護** | 共有リンクにパスワードを設定可能 |
| 🔑 **パスワード再設定** | 既存の共有リンクのパスワードを変更可能 |
| 📊 **ダウンロードカウント** | ダウンロード回数を自動でカウント |
| 📦 **ZIPダウンロード** | 複数ファイルをZIPで一括ダウンロード（日本語ファイル名対応） |
| 🛡️ **直リンク防止** | ブラウザでの直叩きを防止 |
| 🌙 **ダークモード対応** | システム設定に応じた自動テーマ切り替え |
| 🐱 **可愛いエラーページ** | 猫マスコットアニメーション付きのフレンドリーなエラーページ |
| 📸 **画像プレビュー** | アップロード前に画像ファイルをプレビュー表示 |
| 📋 **リスト/グリッド表示** | リスト表示とグリッド表示の切り替え |
| 🔄 **ソート機能** | タイトル、日付、ダウンロード数で並び替え |
| 🗑️ **個別ファイル削除** | アップロード後も1ファイルずつ削除可能 |
| 🔧 **高度な設定** | データベースとファイルの整合性チェック |
| 📱 **レスポンシブ対応** | PC・スマートフォンで快適に使用可能 |
| 🌐 **多言語対応** | 英語・日本語をサポート |

---

## 📥 インストール方法

1. このプラグインフォルダ（`simple-file-share`）を `/wp-content/plugins/` にアップロードします
2. WordPress管理画面の「プラグイン」メニューからプラグインを有効化します
3. サイドメニューに「ファイル共有」が追加されます

---

## 🚀 使い方

### ステップ1：ファイルをアップロードする

1. WordPress管理画面のサイドメニューから **「ファイル共有」** をクリック
2. **「共有タイトル」** を入力（例：「2024年12月の資料」）
3. 必要に応じて **「パスワード保護」** を「あり」に設定
4. **ファイル選択エリア** にファイルをドラッグ＆ドロップ、またはクリックして選択
5. **リスト/グリッド表示を切り替え** てファイルをプレビュー（画像はサムネイル表示）
6. **「アップロードして共有リンクを作成」** ボタンをクリック
7. 完了後、共有URLが表示されます

### ステップ2：共有リンクを相手に伝える

- 共有リンク一覧から **「コピー」** ボタンでURLをコピー
- メールやチャットで相手に送信
- パスワードを設定した場合は、パスワードも一緒に伝える

### ステップ3：相手がダウンロードする

- 相手は共有URLにアクセス
- パスワード保護がある場合はパスワードを入力
- 「ZIPでダウンロード」または個別ファイルの「DL」ボタンでダウンロード

---

## 🔑 パスワード管理

### パスワードの設定
- 新規共有作成時にパスワード保護を有効化
- 自動生成ボタンで安全なパスワードを作成

### パスワードの再設定
1. **ファイル共有** → **有効な共有リンク** に移動
2. 共有の横にある **「編集」** ボタンをクリック
3. 新しいパスワードを入力して保存

---

## ⚙️ 高度な設定

### アクセス方法

WordPress管理画面 → ファイル共有 → **「高度な設定」**

### 機能

#### 📊 統計情報
- 登録済み共有数
- アップロードフォルダ数
- 孤立フォルダ数
- 孤立レコード数

#### 🧹 整合性チェック

**孤立フォルダ** とは：
- ファイルはサーバーに存在するが、データベースに登録がない状態
- 「孤立フォルダを削除」ボタンで安全に削除可能

**孤立レコード** とは：
- データベースには登録があるが、実際のファイルが存在しない状態
- 「孤立レコードを削除」ボタンでデータベースをクリーンアップ可能

#### 📋 データベース内容一覧
- 全ての共有情報を一覧表示
- ID、タイトル、トークン、ファイル有無、パスワード有無、DL数、作成日、ステータスを確認可能
- **ソート可能** - ヘッダーをクリックして並び替え

---

## 🌙 ダークモード対応

プラグインはシステムのダークモード設定を自動検出し、テーマを適用します：

- **ライトモード**: クリーンで明るいインターフェース
- **ダークモード**: 目に優しいダークインターフェース

テーマの切り替えはシームレスで、手動設定は不要です。

---

## 🐱 可愛いエラーページ

エラー発生時（リンク期限切れ、パスワード間違い、ファイル未検出など）には、フレンドリーなエラーページが表示されます：

- 🐾 アニメーション付き猫マスコット
- 📝 ユーザーの言語で明確なエラーメッセージ
- 🔗 便利なナビゲーションオプション

---

## 📦 ダウンロード

**GitLab Releasesから最新版をダウンロードしてお使いください：**

📥 **[GitLab Releases](https://gitlab.com/lichiphen/simple-secure-file-share/-/releases)**

---

## 📁 ファイル構成

```
simple-file-share/
├── simple-file-share.php    # メインプラグインファイル
├── README.md                 # このファイル
├── CHANGELOG.md              # 変更履歴
├── LICENSE                   # ライセンスファイル
├── assets/                   # CSSとJavaScriptファイル
│   ├── css/
│   │   └── style.css         # CSS変数を使用した全スタイル
│   ├── js/
│   │   └── app.js            # フロント/バックエンドスクリプト
│   └── images/
│       └── cat-mascot.png    # エラーページ用猫マスコット
├── languages/                # 翻訳ファイル
│   ├── simple-secure-file-share.pot
│   ├── simple-secure-file-share-en_US.po
│   ├── simple-secure-file-share-en_US.mo
│   ├── simple-secure-file-share-ja.po
│   └── simple-secure-file-share-ja.mo
└── protected-uploads/        # アップロードファイル保存先
    └── [token]/              # 各共有のフォルダ
        └── [files]           # 実際のファイル
```

---

## 🎨 CSSアーキテクチャ

CSSはCSS変数（カスタムプロパティ）を使用したモジュラー構成になっています。

### CSS変数

```css
:root {
  /* カラー */
  --sfs-color-accent: #3b82f6;
  --sfs-color-success: #10b981;
  --sfs-color-error: #ef4444;
  
  /* 背景 */
  --sfs-color-bg-primary: #f9fafb;
  --sfs-color-bg-secondary: #ffffff;
  
  /* テキスト */
  --sfs-color-text-primary: #1f2937;
  --sfs-color-text-secondary: #4b5563;
}

/* ダークモード時のオーバーライド */
@media (prefers-color-scheme: dark) {
  :root {
    --sfs-color-bg-primary: #111827;
    --sfs-color-text-primary: #f9fafb;
  }
}
```

---

## 🔐 セキュリティ機能

### 直接アクセス防止
- `protected-uploads/` フォルダには `.htaccess` で外部からのアクセスを遮断
- ファイルはプラグイン経由でのみダウンロード可能

### パスワード保護
- パスワードはbcryptで暗号化（ハッシュ化）して保存
- Cookie認証により1時間有効
- オートコンプリート防止機能付きの安全なパスワード入力

### 直リンク防止
- リファラーチェック
- 日次トークン検証
- ブラウザでURL直叩きによるダウンロードを防止

---

## 🌐 国際化対応（i18n）

プラグインは完全な国際化対応しており、以下の言語をサポートしています：

| 言語 | コード | ステータス |
|------|--------|-----------|
| English | `en_US` | ✅ 完了 |
| 日本語 | `ja` | ✅ 完了 |

---

## 📝 変更履歴

詳細なバージョン履歴は[CHANGELOG.md](./CHANGELOG.md)をご覧ください。

### v4.0.0（最新）
- 🌙 ダークモード対応を追加
- 🐱 猫マスコット付き可愛いエラーページを追加
- 📸 アップロードフォームに画像プレビュー機能を追加
- 📋 リスト/グリッド表示切り替えを追加
- 🔄 テーブルのソート機能を追加
- 🗑️ 個別ファイル削除機能を追加
- 🔑 パスワード再設定機能を追加
- 🎨 CSS変数を使用したCSSアーキテクチャの再構成
- 📦 アセットのモジュラー構成
- 🌐 i18nサポートの強化（英語・日本語のみ）
- 🔧 PHP 7.4+互換性

---

## 🤝 サポート

問題が発生した場合は、以下をご確認ください：

1. プラグインが最新バージョンであること
2. WordPress 5.0以上、PHP 7.4以上であること
3. 「高度な設定」で整合性チェックを実行

**お問い合わせ:**
- Website: [https://lichiphen.com](https://lichiphen.com)
- X (Twitter): [@Lichiphen](https://x.com/Lichiphen)

---

## ⚖️ ライセンス

Lichiphen Proprietary License v1.0

- ✅ 商用利用可
- ✅ 個人利用可
- ✅ 改変可
- ⚠️ 再配布時は著作権表示必須
- ❌ 著作権表示の削除禁止

Copyright (c) 2025-2026 Lichiphen. All rights reserved.
