# Changelog

All notable changes to this project will be documented in this file.

## [4.0.0] - 2026-01-04

### Added
- 🌙 Dark Mode support with automatic system preference detection
- 🐱 Cute error pages with animated cat mascot (404, access denied, share not found)
- 📸 Image preview support in upload form (thumbnails for jpg, png, gif, webp, svg)
- � List/Grid view toggle for file display (saved to localStorage)
- 🔄 Sortable table columns (Title, Date, Download Count)
- �️ Individual file deletion - remove files one by one after upload
- 🔑 Password reset functionality for existing share links
- 🎨 CSS Custom Properties (variables) for consistent theming
- 📦 Modular assets structure (`assets/css/`, `assets/js/`, `assets/images/`)
- 🌐 Enhanced i18n support (English and Japanese only)
- 📝 Comprehensive documentation with CSS architecture details

### Changed
- 🔧 PHP 7.4+ compatibility (down from PHP 8.4)
- 📁 New file structure with separated CSS/JS files
- 🎨 Migrated from inline styles to external CSS with variables
- 🔒 Improved password handling with bcrypt hashing
- � Enhanced responsive design for all pages

### Improved
- ⚡ Better performance with modular JavaScript
- 🔒 Enhanced security with CSRF protection
- 🎯 More accessible error handling with friendly messages
- 📊 Better UX with toast notifications centered on screen

### Migration Notes
- If upgrading from v3.x, existing shares will continue to work
- New CSS file `assets/css/style.css` replaces inline Tailwind styles
- New JS file `assets/js/app.js` contains all frontend logic
- Translation files updated with new strings

---

## [3.1.2] - 2025-12-15

### Added
- 📦 GitLab Releases for distribution
- 📝 Download links in README pointing to GitLab Releases
- 💡 Tips for using the latest version in README

### Changed
- 🔧 Improved distribution workflow
- 📝 Updated README download section with clearer instructions (English & Japanese)

---

## [3.1.1] - 2025-12-08

### Changed
- 🔧 PHP 8.4 compatibility
- 🔧 Updated contact & copyright information

### Added
- 🆕 Full internationalization (admin & frontend)

---

## [3.0.0] - 2025-12-07

### Added
- 🆕 Advanced Settings page
- 🆕 Download counter feature
- 🆕 Direct link prevention
- 🆕 How to Use page
- 🆕 Multi-language support (Japanese/English)

### Changed
- 🔧 Improved table UI (word wrap, horizontal scroll)
- 🔧 Centered toast notifications

---

## [2.6.0]

### Changed
- Improved password protection
- Enhanced autocomplete prevention

---

## [2.3.0]

### Added
- CSS text-security password mask implementation

---
---

# 変更履歴

このプロジェクトの注目すべき変更は、このファイルに記録されます。

## [4.0.0] - 2026-01-04

### 追加
- 🌙 システム設定自動検出によるダークモード対応
- 🐱 アニメーション付き猫マスコットの可愛いエラーページ（404、アクセス拒否、共有未検出）
- 📸 アップロードフォームでの画像プレビュー対応（jpg, png, gif, webp, svgのサムネイル表示）
- 📋 リスト/グリッド表示切替（localStorageに保存）
- 🔄 テーブルのソート機能（タイトル、日付、ダウンロード数）
- 🗑️ 個別ファイル削除 - アップロード後に1ファイルずつ削除可能
- 🔑 既存共有リンクのパスワード再設定機能
- 🎨 一貫したテーマ設定のためのCSSカスタムプロパティ（変数）
- 📦 モジュラーアセット構成（`assets/css/`, `assets/js/`, `assets/images/`）
- 🌐 i18nサポートの強化（英語・日本語のみ）
- 📝 CSSアーキテクチャ詳細を含む包括的なドキュメント

### 変更
- 🔧 PHP 7.4+互換性（PHP 8.4から引き下げ）
- 📁 CSS/JSファイルを分離した新しいファイル構成
- 🎨 インラインスタイルから変数付き外部CSSへの移行
- � bcryptハッシュによるパスワード処理の改善
- 📱 全ページの強化されたレスポンシブデザイン

### 改善
- ⚡ モジュラーJavaScriptによるパフォーマンス向上
- 🔒 CSRF保護によるセキュリティ強化
- 🎯 フレンドリーなメッセージによるアクセシブルなエラー処理
- 📊 画面中央のトースト通知によるUX改善

### 移行に関する注意事項
- v3.xからアップグレードする場合、既存の共有は引き続き動作します
- 新しいCSSファイル`assets/css/style.css`がインラインのTailwindスタイルを置き換えます
- 新しいJSファイル`assets/js/app.js`に全フロントエンドロジックが含まれます
- 翻訳ファイルは新しい文字列で更新されています

---

## [3.1.2] - 2025-12-15

### 追加
- 📦 GitLab Releasesによる配布対応
- 📝 README に GitLab Releases へのダウンロードリンクを追加
- 💡 README に最新版使用を促すヒントを追加

### 変更
- 🔧 配布ワークフローの改善
- 📝 README のダウンロードセクションをより明確な説明に更新（英語・日本語）

---

## [3.1.1] - 2025-12-08

### 変更
- 🔧 PHP 8.4対応
- 🔧 連絡先・著作権情報の更新

### 追加
- 🆕 全ページの国際化対応（管理画面・フロントエンド）

---

## [3.0.0] - 2025-12-07

### 追加
- 🆕 高度な設定ページを追加
- 🆕 ダウンロード回数カウント機能を追加
- 🆕 直リンク防止機能を追加
- 🆕 使い方ページを追加
- 🆕 多言語対応（日本語/英語）

### 変更
- 🔧 テーブルUIの改善（タイトル折り返し、横スクロール対応）
- 🔧 トースト通知を画面中央に変更

---

## [2.6.0]

### 変更
- パスワード保護機能の改善
- オートコンプリート防止機能の強化

---

## [2.3.0]

### 追加
- CSS text-securityによるパスワードマスク実装
