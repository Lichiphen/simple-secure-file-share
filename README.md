# Simple Secure File Share

[![GitLab](https://img.shields.io/badge/GitLab-312152?style=for-the-badge&logo=gitlab&logoColor=white)](https://gitlab.com/lichiphen/simple-secure-file-share)
[![GitHub](https://img.shields.io/badge/GitHub-181717?style=for-the-badge&logo=github&logoColor=white)](https://github.com/Lichiphen/simple-secure-file-share)

[![Version](https://img.shields.io/badge/version-4.1.0-blue.svg)](https://gitlab.com/lichiphen/simple-secure-file-share)
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

> [!NOTE]
> **Security status (as of 2026-10-30):** v4.1.0 fixes every security issue identified up to 2026-10-30 and keeps security risks low — signed and revocable share cookies, password attempt limits, random on-disk file names, SVG preview blocking, signed download links and no external scripts. This is not a guarantee that no vulnerabilities exist; keep the plugin, WordPress and PHP up to date.

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

### 🔏 Verifying Downloads

Release ZIPs from v4.1.0 on are signed with Lichiphen's Ed25519 key, and each ZIP has a `.sig` file next to it on the Releases page. A valid signature shows the file was made by Lichiphen — even if the GitHub / GitLab account were ever taken over, a forged ZIP cannot carry it.

1. Download the public key from **https://lichiphen.com/keys/release-signing.txt** and save it as `allowed_signers`. Always take it from lichiphen.com, not from this repository; if they ever differ, trust lichiphen.com.
2. Its fingerprint is `SHA256:4OUsj5/foFsud4sZEnMUIDmxTMH1pqU7kH3WHa6RUp4`.
3. Put the ZIP, its `.sig` and `allowed_signers` in one folder and run (`ssh-keygen` ships with Windows 10+, macOS and Linux):

```bash
# Windows (PowerShell / Command Prompt)
cmd /c "ssh-keygen -Y verify -f allowed_signers -I lichiphen -n file -s simple-secure-file-share-v4.1.0.zip.sig < simple-secure-file-share-v4.1.0.zip"
# macOS / Linux
ssh-keygen -Y verify -f allowed_signers -I lichiphen -n file -s simple-secure-file-share-v4.1.0.zip.sig < simple-secure-file-share-v4.1.0.zip
```

`Good "file" signature for lichiphen` means the file is genuine. If you see anything else, do not use the file and contact Lichiphen. Git tags from v4.1.0 on are signed with the same key.

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
│   │   ├── style.css         # All styles with CSS variables
│   │   └── tailwind.min.css  # Locally built Tailwind (no CDN script)
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
└── tools/tailwind/           # Tailwind build config

wp-content/uploads/sfs-protected/   # Upload file storage (survives plugin updates)
└── [token]/                        # Folder for each share
    └── [random].dat                # Files are stored under random names
```

> Since v4.1.0, files are stored in `wp-content/uploads/sfs-protected/` instead of the plugin folder (which WordPress deletes on every plugin update). Existing shares are migrated automatically the first time they are opened or an administrator opens wp-admin. Use the `sfs_storage_dir` filter to move storage outside the web root.

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
- Files are stored under random names with a `.dat` extension — the uploaded name is never used on disk, so `.php`, `.phtml`, `.htaccess` etc. can never be executed or interpreted
- `.htaccess` (Apache 2.2 / 2.4), `web.config` (IIS) and `index.php` deny access to `wp-content/uploads/sfs-protected/`
- **Nginx**: add the following rule (Nginx ignores `.htaccess`):

```nginx
# Nginx ignores .htaccess — add this to your server block
# Nginx では .htaccess が無効なため server ブロックに追加してください
location ^~ /wp-content/uploads/sfs-protected/ { deny all; return 404; }
```

- Files can only be downloaded through the plugin, always as `attachment` with `X-Content-Type-Options: nosniff` and a sandbox CSP

### Password Protection
- Passwords are hashed with bcrypt (`password_hash`); legacy phpass hashes are upgraded on the next successful login
- Authentication cookie is HMAC-signed (WordPress salts), expires after 1 hour, is `HttpOnly` / `SameSite=Lax` / `Secure` on HTTPS, and is revoked when the password changes
- Brute-force protection: 10 failed attempts per IP per share (50 per IP overall) lock the form for 15 minutes
- Share pages send `no-store` / `DONOTCACHEPAGE` so page-cache plugins never store unlocked content

### Image Preview
- Only registered files of the share, only raster images detected from file content (JPEG/PNG/GIF/WebP/BMP/AVIF). SVG is never served inline (it can contain scripts)

### Direct Link Prevention
- HMAC-signed, time-limited download tokens (no longer relies on the Referer header)

### Other
- No third-party scripts: Tailwind is bundled locally (previously loaded from `cdn.tailwindcss.com`, including in wp-admin)
- Admin handlers only operate on `sfs_share` posts; every file operation is checked against path traversal
- Password generator uses `crypto.getRandomValues`

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

### v4.1.0 (Latest) — Security update
- 🔒 Signed, revocable auth cookies, brute-force protection, bcrypt hashing
- 🔒 Random on-disk file names, storage moved to `wp-content/uploads/sfs-protected/`
- 🔒 SVG preview XSS fixed, admin file-name XSS fixed, Tailwind CDN removed
- 🔒 Signed download links, no-cache headers, safe ZIP entry names

> [!WARNING]
> **Updating from v4.0.0 or earlier:** files of existing shares are still in the plugin folder (`protected-uploads/`). Updating with **Plugins → Add New → Upload Plugin → "Replace current with uploaded"** (or any automatic update) deletes the old plugin folder, and those files with it. Either overwrite the plugin files via FTP/SFTP (the folder is moved to `wp-content/uploads/sfs-protected/` automatically), or back up `protected-uploads/` first and copy its token folders into `wp-content/uploads/sfs-protected/` after the update.

### v4.0.0
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

> [!NOTE]
> **セキュリティ対策の状況（2026-10-30 時点）:** v4.1.0 では、2026-10-30 までに確認したセキュリティ上の問題をすべて修正し、セキュリティリスクを抑えています。共有 Cookie の署名と失効、パスワード試行回数の制限、ランダムな保存ファイル名、SVG プレビューの拒否、署名付きダウンロードリンク、外部スクリプトの不使用などに対応しています。脆弱性が一切ないことを保証するものではないため、プラグイン・WordPress・PHP は最新版でお使いください。

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

### 🔏 ダウンロードしたファイルの確認

v4.1.0 以降の配布 ZIP には Lichiphen の Ed25519 鍵で署名しており、Releases のページで各 ZIP の隣に `.sig` ファイルを置いています。署名が正しければ、Lichiphen が作ったファイルだと確かめられます。万一 GitHub / GitLab のアカウントが乗っ取られても、偽の ZIP に正しい署名は付けられません。

1. 公開鍵を **https://lichiphen.com/keys/release-signing.txt** からダウンロードし、`allowed_signers` という名前で保存します。公開鍵は必ず lichiphen.com から取得してください（このリポジトリの内容と食い違う場合は lichiphen.com が正です）。
2. 公開鍵の指紋は `SHA256:4OUsj5/foFsud4sZEnMUIDmxTMH1pqU7kH3WHa6RUp4` です。
3. ZIP・`.sig`・`allowed_signers` を同じフォルダに置き、次を実行します（`ssh-keygen` は Windows 10 以降・macOS・Linux に標準で入っています）。

```bash
# Windows（PowerShell / コマンドプロンプト）
cmd /c "ssh-keygen -Y verify -f allowed_signers -I lichiphen -n file -s simple-secure-file-share-v4.1.0.zip.sig < simple-secure-file-share-v4.1.0.zip"
# macOS / Linux
ssh-keygen -Y verify -f allowed_signers -I lichiphen -n file -s simple-secure-file-share-v4.1.0.zip.sig < simple-secure-file-share-v4.1.0.zip
```

`Good "file" signature for lichiphen` と表示されれば本物です。それ以外が表示された場合は使わずに、Lichiphen までご連絡ください。v4.1.0 以降の Git タグにも同じ鍵で署名しています。

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
└── tools/tailwind/           # Tailwind ビルド設定

wp-content/uploads/sfs-protected/  # アップロードファイル保存先（プラグイン更新で消えません）
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
- ファイルはランダム名＋`.dat` 拡張子で保存し、アップロード時の名前をディスク上で使わない（`.php` `.phtml` `.htaccess` 等が実行・解釈されることはありません）
- `wp-content/uploads/sfs-protected/` は `.htaccess`（Apache 2.2/2.4）・`web.config`（IIS）・`index.php` でアクセスを遮断
- **Nginx** の場合は以下を追加してください（Nginx は `.htaccess` を読みません）:

```nginx
# Nginx ignores .htaccess — add this to your server block
# Nginx では .htaccess が無効なため server ブロックに追加してください
location ^~ /wp-content/uploads/sfs-protected/ { deny all; return 404; }
```

- ダウンロードはプラグイン経由のみ。常に `attachment`・`X-Content-Type-Options: nosniff`・sandbox CSP 付き

### パスワード保護
- パスワードは bcrypt（`password_hash`）でハッシュ化。旧形式（phpass）は次回ログイン成功時に自動で再ハッシュ
- 認証CookieはWordPressのソルトでHMAC署名、1時間で失効、`HttpOnly` / `SameSite=Lax` / HTTPS時 `Secure`。パスワード変更で即失効
- 総当たり対策: 同一IP・同一共有で10回（IP全体で50回）失敗すると15分間ロック
- 共有ページは `no-store` / `DONOTCACHEPAGE` を送出し、キャッシュ系プラグインに認証後の内容を保存させません

### 画像プレビュー
- 共有に登録されたファイルのみ、中身から判定したラスター画像（JPEG/PNG/GIF/WebP/BMP/AVIF）のみ配信。SVG はスクリプトを含み得るためインライン配信しません

### 直リンク防止
- 有効期限付きのHMAC署名ダウンロードトークン（Referer に依存しません）

### その他
- 外部スクリプトなし: Tailwind をローカルに同梱（従来は管理画面を含め `cdn.tailwindcss.com` から読み込み）
- 管理用ハンドラは `sfs_share` 投稿のみを操作、すべてのファイル操作でパストラバーサルを検査
- パスワード生成は `crypto.getRandomValues` を使用

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

### v4.1.0（最新）— セキュリティアップデート
- 🔒 署名付き・失効可能な認証Cookie、総当たり対策、bcrypt ハッシュ
- 🔒 ランダムな保存ファイル名、保存先を `wp-content/uploads/sfs-protected/` に変更
- 🔒 SVGプレビューのXSS、管理画面のファイル名XSSを修正、Tailwind CDN を廃止
- 🔒 署名付きダウンロードリンク、キャッシュ無効化ヘッダー、安全なZIPエントリ名

> [!WARNING]
> **v4.0.0 以前からの更新について:** 既存の共有ファイルはプラグインフォルダ内（`protected-uploads/`）にあります。**「プラグイン → 新規追加 → プラグインのアップロード →『アップロードしたもので現在のものを置き換える』」**や自動更新で更新すると、旧プラグインフォルダごとファイルが削除されます。FTP/SFTP でプラグインのファイルを上書きするか（フォルダは `wp-content/uploads/sfs-protected/` へ自動で移動します）、更新前に `protected-uploads/` をバックアップし、更新後にその中のトークン名のフォルダを `wp-content/uploads/sfs-protected/` へコピーしてください。

### v4.0.0
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
