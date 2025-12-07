# Simple Secure File Share

**Version:** 3.1.0  
**Author:** AI Generator, Direction：Lichiphen  
**Website:** [https://lichiphen.com](https://lichiphen.com) | **X (Twitter):** [@Lichiphen](https://x.com/Lichiphen)  
**License:** Lichiphen Proprietary License v1.0  
**Requires PHP:** 8.4+

---

## 📋 概要 / Overview

### 日本語

「Simple Secure File Share」は、WordPressサイトでファイルを安全に共有するためのプラグインです。

管理者がファイルをアップロードすると、専用の共有URLが発行されます。ファイルは直接アクセスから保護され、パスワード保護、ダウンロード回数のカウント、ZIPダウンロードなどの機能を備えています。

### English

"Simple Secure File Share" is a WordPress plugin for securely sharing files.

When an administrator uploads files, a dedicated sharing URL is generated. Files are protected from direct access and include features such as password protection, download counting, and ZIP downloads.

---

## ✨ 主な機能 / Features

| 機能 / Feature | 説明 / Description |
|----------------|-------------------|
| 🔒 **パスワード保護** | 共有リンクにパスワードを設定可能 |
| 📊 **ダウンロードカウント** | ダウンロード回数を自動でカウント |
| 📦 **ZIPダウンロード** | 複数ファイルをZIPで一括ダウンロード |
| 🛡️ **直リンク防止** | ブラウザでの直叩きを防止 |
| 🔧 **高度な設定** | データベースとファイルの整合性チェック |
| 📱 **レスポンシブ対応** | PC・スマートフォンで快適に使用可能 |

---

## 📥 インストール方法 / Installation

### 日本語

1. このプラグインフォルダ（`simple-file-share`）を `/wp-content/plugins/` にアップロードします
2. WordPress管理画面の「プラグイン」メニューからプラグインを有効化します
3. サイドメニューに「ファイル共有」が追加されます

### English

1. Upload the plugin folder (`simple-file-share`) to `/wp-content/plugins/`
2. Activate the plugin through the "Plugins" menu in WordPress
3. "File Share" will be added to the side menu

---

## 🚀 使い方 / How to Use

### 1. ファイルをアップロードする

1. WordPress管理画面のサイドメニューから **「ファイル共有」** をクリック
2. **「共有タイトル」** を入力（例：「2024年12月の資料」）
3. 必要に応じて **「パスワード保護」** を「あり」に設定
4. **ファイル選択エリア** にファイルをドラッグ＆ドロップ、またはクリックして選択
5. **「アップロードして共有リンクを作成」** ボタンをクリック
6. 完了後、共有URLが表示されます

### 2. 共有リンクを相手に伝える

- 共有リンク一覧から **「コピー」** ボタンでURLをコピー
- メールやチャットで相手に送信

### 3. 相手がダウンロードする

- 相手は共有URLにアクセス
- パスワード保護がある場合はパスワードを入力
- 「ZIPでダウンロード」または個別ファイルの「DL」ボタンでダウンロード

---

## ⚙️ 高度な設定 / Advanced Settings

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

## 📁 ファイル構成 / File Structure

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

## 🔐 セキュリティ機能 / Security Features

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

## 📝 変更履歴 / Changelog

### v3.1.0 (2025-12-07)
- 🔧 PHP 8.4対応
- 🔧 連絡先・著作権情報の更新

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

## 🤝 サポート / Support

問題が発生した場合は、以下をご確認ください：

1. プラグインが最新バージョンであること
2. WordPress 5.0以上、PHP 8.4以上であること
3. 「高度な設定」で整合性チェックを実行

**お問い合わせ:**
- Website: [https://lichiphen.com](https://lichiphen.com)
- X (Twitter): [@Lichiphen](https://x.com/Lichiphen)

---

## ⚖️ ライセンス / License

Lichiphen Proprietary License v1.0

- ✅ 商用利用可
- ✅ 個人利用可
- ✅ 改変可
- ⚠️ 再配布時は著作権表示必須
- ❌ 著作権表示の削除禁止

Copyright (c) 2025 Lichiphen. All rights reserved.
