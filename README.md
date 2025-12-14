# Simple Secure File Share

[![Version](https://img.shields.io/badge/version-3.1.2-blue.svg)](https://gitlab.com/lichiphen/simple-secure-file-share)
[![License](https://img.shields.io/badge/license-Proprietary-orange.svg)](./LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.4%2B-purple.svg)](https://php.net)
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
| 📊 **Download Counter** | Automatically count downloads to track usage |
| 📦 **ZIP Download** | Bulk download multiple files as ZIP (supports Japanese filenames) |
| 🛡️ **Direct Link Prevention** | Prevent unauthorized downloads via direct URL access |
| 🔧 **Advanced Settings** | Check database and file system integrity |
| 📱 **Responsive Design** | Works great on PC and mobile devices |

---

## � Installation

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
5. Click **"Upload and Create Share Link"**
6. Your share URL will be displayed

### Step 2: Share the Link

- Click the **"Copy"** button to copy the share URL
- Send via email or chat
- If password is set, share the password as well

### Step 3: Recipient Downloads

- Recipient accesses the share URL
- Enter password if protected
- Download as ZIP or individual files

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

---

## 📁 File Structure

```
simple-file-share/
├── simple-file-share.php    # Main plugin file
├── README.md                 # This file
├── LICENSE                   # License file
├── languages/                # Translation files
│   ├── simple-secure-file-share.pot
│   ├── simple-secure-file-share-ja.po
│   └── simple-secure-file-share-ja.mo
└── protected-uploads/        # Upload file storage
    └── [token]/              # Folder for each share
        └── [files]           # Actual files
```

---

## 🔐 Security Features

### Direct Access Prevention
- `.htaccess` blocks external access to `protected-uploads/` folder
- Files can only be downloaded through the plugin

### Password Protection
- Passwords are stored hashed (encrypted)
- Cookie authentication valid for 1 hour

### Direct Link Prevention
- Referer check
- Daily token verification
- Prevents downloads via direct URL access

---

## 📝 Changelog

### v3.1.2 (2025-12-15)
- 📦 Added GitLab Releases for distribution
- 📝 Updated README with download links
- 🔧 Improved distribution workflow

### v3.1.1 (2025-12-08)
- 🔧 PHP 8.4 compatibility
- 🔧 Updated contact & copyright information
- 🆕 Full internationalization (admin & frontend)

### v3.0.0 (2025-12-07)
- 🆕 Added Advanced Settings page
- 🆕 Added download counter feature
- 🆕 Added direct link prevention
- 🆕 Added How to Use page
- 🆕 Multi-language support (Japanese/English)
- 🔧 Improved table UI (word wrap, horizontal scroll)
- 🔧 Centered toast notifications

### v2.6.0
- Improved password protection
- Enhanced autocomplete prevention

### v2.3.0
- CSS text-security password mask implementation

---

## 🤝 Support

If you encounter issues, please check:

1. Plugin is up to date
2. WordPress 5.0+ and PHP 8.4+
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

**If you absolutely need to remove the copyright notice, we can arrange this for a fee.**  
Contact: [https://lichiphen.com](https://lichiphen.com) or [X (Twitter)](https://x.com/Lichiphen)

Copyright (c) 2025 Lichiphen. All rights reserved.

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
| 📊 **ダウンロードカウント** | ダウンロード回数を自動でカウント |
| 📦 **ZIPダウンロード** | 複数ファイルをZIPで一括ダウンロード |
| 🛡️ **直リンク防止** | ブラウザでの直叩きを防止 |
| 🔧 **高度な設定** | データベースとファイルの整合性チェック |
| 📱 **レスポンシブ対応** | PC・スマートフォンで快適に使用可能 |

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
5. **「アップロードして共有リンクを作成」** ボタンをクリック
6. 完了後、共有URLが表示されます

### ステップ2：共有リンクを相手に伝える

- 共有リンク一覧から **「コピー」** ボタンでURLをコピー
- メールやチャットで相手に送信
- パスワードを設定した場合は、パスワードも一緒に伝える

### ステップ3：相手がダウンロードする

- 相手は共有URLにアクセス
- パスワード保護がある場合はパスワードを入力
- 「ZIPでダウンロード」または個別ファイルの「DL」ボタンでダウンロード

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

---

## 📦 ダウンロード

**GitLab Releasesから最新版をダウンロードしてお使いください：**

� **[GitLab Releases](https://gitlab.com/lichiphen/simple-secure-file-share/-/releases)**

リリースパッケージには、インストールに必要なすべてのファイルが含まれています：
- `simple-file-share.php` (メインプラグインファイル)
- `README.md` (ドキュメント)
- `LICENSE` (ライセンス情報)
- `languages/` (翻訳ファイル)

---

## 📁 ファイル構成

```
simple-file-share/
├── simple-file-share.php    # メインプラグインファイル
├── README.md                 # このファイル
├── LICENSE                   # ライセンスファイル
├── languages/                # 翻訳ファイル
│   ├── simple-secure-file-share.pot
│   ├── simple-secure-file-share-ja.po
│   └── simple-secure-file-share-ja.mo
└── protected-uploads/        # アップロードファイル保存先
    └── [token]/              # 各共有のフォルダ
        └── [files]           # 実際のファイル
```

---

## 🔐 セキュリティ機能

### 直接アクセス防止
- `protected-uploads/` フォルダには `.htaccess` で外部からのアクセスを遮断
- ファイルはプラグイン経由でのみダウンロード可能

### パスワード保護
- パスワードは暗号化（ハッシュ化）して保存
- Cookie認証により1時間有効

### 直リンク防止
- リファラーチェック
- 日次トークン検証
- ブラウザでURL直叩きによるダウンロードを防止

---

## 📝 変更履歴

### v3.1.1 (2025-12-08)
- 🔧 PHP 8.4対応
- 🔧 連絡先・著作権情報の更新
- 🆕 全ページの国際化対応（管理画面・フロントエンド）

### v3.0.0 (2025-12-07)
- 🆕 高度な設定ページを追加
- 🆕 ダウンロード回数カウント機能を追加
- 🆕 直リンク防止機能を追加
- 🆕 使い方ページを追加
- 🆕 多言語対応（日本語/英語）
- 🔧 テーブルUIの改善（タイトル折り返し、横スクロール対応）
- 🔧 トースト通知を画面中央に変更

### v2.6.0
- パスワード保護機能の改善
- オートコンプリート防止機能の強化

### v2.3.0
- CSS text-securityによるパスワードマスク実装

---

## 🤝 サポート

問題が発生した場合は、以下をご確認ください：

1. プラグインが最新バージョンであること
2. WordPress 5.0以上、PHP 8.4以上であること
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

**著作権表示をどうしても削除したい場合は、有償にて対応いたします。**  
お問い合わせ: [https://lichiphen.com](https://lichiphen.com) または [X (Twitter)](https://x.com/Lichiphen)

Copyright (c) 2025 Lichiphen. All rights reserved.
