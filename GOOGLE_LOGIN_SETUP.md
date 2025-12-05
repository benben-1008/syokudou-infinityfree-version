# Googleアカウントログイン機能のセットアップ

このシステムにGoogleアカウントでログインする機能を追加しました。以下の手順でセットアップしてください。

## 機能

1. **Googleアカウントでログイン** - Google Sign-In APIを使用
2. **予約時の名前自動入力** - ログインしている場合、予約フォームに名前が自動入力されます
3. **購入履歴の確認** - `my-reservations.php`で自分の予約履歴を確認できます

## セットアップ手順

### 1. Google Cloud Consoleでプロジェクトを作成

1. [Google Cloud Console](https://console.cloud.google.com/)にアクセス
2. 新しいプロジェクトを作成（または既存のプロジェクトを選択）
3. 「APIとサービス」→「認証情報」に移動

### 2. OAuth 2.0 クライアントIDを作成

1. 「認証情報を作成」→「OAuth クライアント ID」を選択
2. 同意画面を設定（初回のみ）
   - ユーザータイプ: 外部
   - アプリ名: 食堂予約システム（任意）
   - サポートメール: あなたのメールアドレス
   - その他の必須項目を入力
3. OAuth クライアント IDを作成
   - アプリケーションの種類: **ウェブアプリケーション**
   - 名前: 食堂予約システム（任意）
   - 承認済みのJavaScript生成元: 
     - `https://your-domain.infinityfreeapp.com`（本番環境のURL）
     - `http://localhost`（ローカル開発用、オプション）
   - 承認済みのリダイレクトURI:
     - `https://your-domain.infinityfreeapp.com`（本番環境のURL）
     - `http://localhost`（ローカル開発用、オプション）

### 3. クライアントIDを取得

作成したOAuth 2.0 クライアントIDをコピーします（例: `123456789-abcdefghijklmnop.apps.googleusercontent.com`）

### 4. コードにクライアントIDを設定

以下のファイルで `YOUR_GOOGLE_CLIENT_ID` を実際のクライアントIDに置き換えてください:

1. **reservation.php** (2箇所)
   - 行: `data-client_id="YOUR_GOOGLE_CLIENT_ID"`
   - 行: `const GOOGLE_CLIENT_ID = 'YOUR_GOOGLE_CLIENT_ID';`

2. **my-reservations.php** (2箇所)
   - 行: `data-client_id="YOUR_GOOGLE_CLIENT_ID"`
   - 行: `const GOOGLE_CLIENT_ID = 'YOUR_GOOGLE_CLIENT_ID';`

### 例

```javascript
// 変更前
const GOOGLE_CLIENT_ID = 'YOUR_GOOGLE_CLIENT_ID';

// 変更後
const GOOGLE_CLIENT_ID = '123456789-abcdefghijklmnop.apps.googleusercontent.com';
```

```html
<!-- 変更前 -->
<div id="g_id_onload"
    data-client_id="YOUR_GOOGLE_CLIENT_ID"
    ...>

<!-- 変更後 -->
<div id="g_id_onload"
    data-client_id="123456789-abcdefghijklmnop.apps.googleusercontent.com"
    ...>
```

## 使用方法

### 予約ページでのログイン

1. `reservation.php`にアクセス
2. 「Googleアカウントでログイン」セクションで「Googleでログイン」ボタンをクリック
3. Googleアカウントを選択してログイン
4. ログイン後、予約フォームの「お名前」欄に自動的に名前が入力されます

### 予約履歴の確認

1. `my-reservations.php`にアクセス
2. Googleアカウントでログイン（初回のみ）
3. 自分の予約履歴が表示されます

## ファイル構成

- `api/auth.php` - 認証API（ログイン/ログアウト/セッション管理）
- `api/user-reservations.php` - ユーザー別の予約履歴取得API
- `my-reservations.php` - 予約履歴表示ページ
- `reservation.php` - 予約ページ（ログイン機能追加）
- `reservation-script.js` - 予約スクリプト（ユーザーID保存機能追加）
- `data/users.json` - ユーザー情報保存ファイル（自動生成）

## 注意事項

1. **セキュリティ**: 本番環境では、Google IDトークンの検証を適切に実装することを推奨します。現在の実装は簡易版です。

2. **HTTPS**: InfinityFreeではHTTPSが利用可能です。本番環境では必ずHTTPSを使用してください。

3. **セッション**: PHPセッションを使用しているため、サーバーのセッション設定を確認してください。

4. **データ保存**: ユーザー情報は `data/users.json` に保存されます。このファイルは適切に保護してください。

## トラブルシューティング

### ログインできない

- Google Cloud ConsoleでクライアントIDが正しく設定されているか確認
- 承認済みのJavaScript生成元に正しいドメインが登録されているか確認
- ブラウザのコンソールでエラーメッセージを確認

### 名前が自動入力されない

- ログイン状態を確認（ページを再読み込み）
- ブラウザのコンソールでエラーメッセージを確認
- セッションが有効か確認

### 予約履歴が表示されない

- ログインしているか確認
- 予約時にログインしていたか確認（ログインしていない場合、履歴に表示されません）
- `data/reservations.json`に`userId`フィールドが含まれているか確認

## 今後の改善点

- Google IDトークンの適切な検証（現在は簡易版）
- パスワードリセット機能（必要に応じて）
- メール通知機能
- 予約のキャンセル機能

