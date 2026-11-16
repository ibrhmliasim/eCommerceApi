# アーキテクチャ決定記録 — Auth モジュール

> 対象：ユーザー登録、メール認証、パスワードリセット、セッション管理
> スタック：Laravel 12、Sanctum SPA、MySQL、Redis
>
> **このドキュメントの読み方（AUTH の運用、Michael Nygard 方式）：**
> ここの記録は承認後に変更不可とする。決定が見直される場合、
> 古い記録は編集も削除もせず、`Status: Superseded by AUTH-N` と記し、
> 新しい決定は番号が増える新しい AUTH として、後方への参照
> （`Supersedes AUTH-N`）付きで追加する。
> これにより思考の履歴が保たれる：「現在何が決まっているか」だけでなく、
> 「以前何が決まり、なぜそれをやめたのか」も分かる。

---

## AUTH — アーキテクチャ決定記録

### AUTH-0: Laravel Sanctum による SPA Cookie ベース（Stateful）認証の採用

**Status:** Accepted

**背景：**
decoupled アーキテクチャ（フロントエンドは Next.js、バックエンドは Laravel API）の
認証を設計するにあたり、本番環境（VPS）では、単一のベースドメインとサブドメインによる
トポロジーを採用している：

- Frontend: `yourshop.com`（ローカルでは `yourshop.test:3000`）
- Backend API: `api.yourshop.com`（ローカルでは `api.yourshop.test:8000`）

Stateless 認証（Personal Access Tokens / ヘッダーでの Bearer JWT）と、
Stateful 認証（Laravel Sanctum によるセッションクッキー）のどちらかを選ぶ必要があった。

**決定：**
戦略的な選択として、標準の Laravel セッションガード（`Auth::guard('web')`）と
ミドルウェア `EnsureFrontendRequestsAreStateful` を用いた
**SPA Cookie-based Auth（Stateful）** を採用した。

**理由（アーキテクチャ上の利点）：**

1. **鉄壁のセキュリティ（XSS からの保護）：** トークンを使う場合、フロントエンドは
   それを `localStorage` や `sessionStorage` に保存せざるを得ない。XSS 攻撃が成功した場合
   （サードパーティのライブラリ経由で悪意ある JS スクリプトが注入される場合）、
   ユーザーのトークンは即座に盗まれる。
   `HttpOnly` と `Secure` フラグ付きのクッキーは、JavaScript から**物理的に読み取れない**。
   フロントエンドで XSS の脆弱性が発生しても、攻撃者はユーザーのセッションを奪えない。
2. **自動的なクロスドメイン共有：** Next.js と Laravel は同じベースドメインを共有するため、
   セッションクッキーは `.yourshop.com`（先頭にドット）のレベルで設定される。
   ユーザーのブラウザは、このクッキーを Next.js からバックエンドへの
   すべての API リクエストにネイティブかつ自動的に付与する。
   トークンの管理や更新のためのカスタム JS コードを書く必要がない。
3. **Framework-Native な組み込み CSRF 対策：**
   Sanctum は標準で、フロントエンドに `/sanctum/csrf-cookie` エンドポイントへの
   事前リクエストを強制する。これにより暗号学的な `XSRF-TOKEN` が生成され、
   e-commerce API をクロスサイトリクエストフォージェリから保護する
   （ユーザーがフィッシングサイトに誘導され、あなたのショップでの購入や
   パスワード変更のリクエストを密かに送信させられる攻撃）。
4. **DB の I/O 負荷の軽減（Redis との組み合わせ）：**
   リクエストごとにトークン照合のため SQL データベースを叩く Personal Access Tokens と異なり、
   Sanctum のセッションは Redis（メモリ）に保存される。これにより、
   高負荷な e-commerce エンドポイントでも API が即座に応答する。

**トレードオフ／代替案の却下（Trade-offs）：**

- **トークン（Bearer Tokens）の不採用：** `$user->createToken()` による生成は意図的に採用しなかった。
  このプロジェクトはサードパーティ連携向けの公開 API ではなく、現段階では
  モバイルアプリも存在しないため。モバイルクライアント（iOS/Android）が登場した場合、
  Sanctum により、Next.js 向けの現在のクッキーのロジックを壊すことなく、
  同じルートで Bearer トークンをシームレスに処理できる。
- **ローカル開発の複雑さ：** ブラウザがクロスドメインのクッキーを正しく扱えるよう、
  デフォルトの `localhost` ではなく `hosts` ファイルによるローカルドメイン
  （`yourshop.test`）の設定が必要。

**設定コントラクト（重要な設定）：**

- `config/cors.php`: `supports_credentials => true`（ブラウザが CORS リクエストでクッキーを送ることを許可）。
- `config/cors.php`: `allowed_origins => [env('FRONTEND_URL')]`（信頼する Next.js ドメインの厳格な制限）。
- `.env`: `SANCTUM_STATEFUL_DOMAINS=yourshop.com,api.yourshop.com`（Sanctum がセッション検証を有効にするドメインのリスト）。

---

### AUTH-1: TrustProxies 設定を伴う Signed URL の使用（メール認証とパスワードリセット）— v1

**Status:** Superseded by AUTH-13

**決定：** 標準の `URL::temporarySignedRoute` ＋ プロキシの信頼（`$middleware->trustProxies(at: '*')`）。

**理由（アーキテクチャ上の利点）：**

- **シンプルでネイティブ：** Laravel の組み込みの仕組み（HMAC-SHA256 による署名 ＋ ミドルウェア `signed`）を使う。何も発明せず、すべて「標準で」動く。
- **DB 不要（stateless）：** トークンやコードを保存しない。Laravel は署名と `APP_KEY` でリンクを検証する → テーブルも cron も余計なクエリも不要。
- **OTP より安全：** 6桁のコードのように総当たりできない。URL を少しでも変更すれば署名が壊れ → アクセスは拒否される。
- **プロキシ配下での正しい動作：** `trustProxies` が `http/https` とドメインの問題を（`X-Forwarded-*` 経由で）解決し、`403 Invalid Signature` エラーをなくす。

**Trade-off（トレードオフ）：**

- メールには署名付きの完全な URL（`?signature=...`）が送られる。これは機密データであり、ブラウザの履歴に残る可能性がある。

**リスク軽減（Mitigation）：**

- **フロントエンドをプロキシとして使う：** Next.js がリンクを受け取り、データをバックエンドに送信し、API のフローを直接公開しない。
- **URL のクリーンアップ：** 認証後に `router.replace('/auth/verify-email?status=success')` を実行し、署名をブラウザ履歴から消す。

---

### AUTH-2: メール認証用の POST 専用エンドポイント

**Status:** Accepted

**決定：** 従来の `GET` ではなく、`POST /api/v1/auth/email/verify` メソッドを使用する。

**理由（メールスキャナーロボットからの保護）：**

- **GET メソッドの問題：** 現代の企業向けメールクライアントやアンチウイルス（Outlook Safe Links、Gmail Scanner、Slack unfurl）は、フィッシングを確認するため、ログや受信メール内のすべてのリンクを自動的に「クリック」する。認証が `GET` で動作する場合、ユーザーがメールを開く前に、ロボットが勝手にメールを認証してしまう。
- **POST による解決：** スキャナーロボットは、ページを解析するために `GET` リクエストのみを実行する。状態を変更する `POST` リクエストを送る機能は物理的になく、送ることもない。これにより、実際のユーザーがフロントエンドのページを訪れたときにのみ、メールが認証されることが保証される。

**Trade-off（トレードオフ）：**

- Laravel の標準の動作の変更（フレームワークはデフォルトで認証用の GET リンクを生成するため）。ルーティングのカスタマイズと、UI 層と API 層の明確な分離が必要。

**コントラクトとフロントエンドの責務（Mitigation）：**

- Next.js のページ（`/auth/verify-email`）は、純粋に UI のシェルとしてのみ機能する。
- ページのマウント（読み込み）時、またはボタンのクリック時に、フロントエンドはクエリパラメータを取得し、バックエンド API に対して必ず `POST` リクエストを発行しなければならない。マウント時の自動 POST は許可される。スキャナーロボットは、API リクエストを送るためのフロントエンドの複雑な JS コードを実行しないため。

---

### AUTH-3: 認証リンクの有効期間（TTL）を60分に設定 — v1

**Status:** Superseded by AUTH-14

**決定：** `now()->addMinutes(60)`

**理由（セキュリティとビジネスコンバージョンのバランス）：**

- **UX とコンバージョン：** 10〜15分の制限（パスワードリセットの標準）は、e-commerce では「Link Expired」の誤ったエラーの割合を高める。メールサーバーからのメールは（特にセールのピーク時には）顧客に届くまで5〜7分かかることがある。ユーザーが気を取られることもある。1時間の制限は、正当な購入者にとっての障壁を最小化する。
- **業界標準：** アカウントの初回認証（登録）のシナリオでは、60分は大手プラットフォームの間で一般的な標準である。この操作は、重要なデータ（パスワードや支払いカード）の変更への即時アクセスを開くものではないため。

**Trade-off（トレードオフ）：**

- 一時的な「攻撃の窓」が広がる。署名（`signature`）がログや履歴に漏れた場合、攻撃者にはユーザーになりすましてリクエストを送ろうとする1時間丸々の猶予がある。

**リスクが許容できる理由（ビジネスロジックレベルの保護）：**

- **バックエンドの冪等性：** Laravel の `markEmailAsVerified()` メソッドは、状態を変更する純粋な関数のように動作する。本物のユーザーがすでにメールを認証していれば（通常1〜2分で済む）、攻撃者が同じリンクで再度リクエストしても、`409 Conflict`（`email_already_verified`）エラーが返る。
- このように、60分の「攻撃の窓」は、正当なユーザーが認証フローを正常に完了した瞬間に **0分** に縮む。リンクは即座に再利用不能になる。

---

### AUTH-4: MustVerifyEmail のアーキテクチャコントラクトと Registered イベントの手動ディスパッチ — v1

**Status:** Superseded by AUTH-15

**決定：**
`User` モデルでの標準インターフェース `Illuminate\Contracts\Auth\MustVerifyEmail` の実装と、サービス層（`AuthService`）内での `event(new Registered($user))` の明示的な手動呼び出しの組み合わせ。

**理由（トランザクションの制御とフローの予測可能性）：**

- **ACID の原則による分離：**
  モデルの自動トリガー（Eloquent Observers）と異なり、`AuthService` での手動ディスパッチは、認証通知が、ユーザー登録のプロセスが完全に終了し、関連するすべてのトランザクションが DB に正常にコミットされた後にのみ送信されることを保証する。
- **Race Conditions からの保護：**
  これにより、システムのリスナーがイベントを拾い、ユーザー作成のトランザクションがまだ閉じていない間にメールを送ろうとする、という重大なバグを防げる。その時点では、バックグラウンドワーカーがデータベースでユーザーを見つけられず、`ModelNotFoundException` で失敗する可能性がある。

**クリーンなモデルコントラクト：**
`sendEmailVerificationNotification()` メソッドは、デフォルトの通知クラスをカスタムの `VerifyEmailNotification`（Next.js の相対リンクに適合させたもの、AUTH-1 参照）に差し替える目的でのみ、`User` クラス内でオーバーライドされている。

**Trade-off（トレードオフ）：**
API を拡張する際のヒューマンファクターが高まる。将来、代替の登録方法（例：Socialite / OAuth / Google Sign-In 経由）が追加された場合、開発者は新しいメソッドで `event(new Registered($user))` を手動で呼び出すのを忘れてはならない — さもないと、認証メールが送信されない。

**設定からの教訓 — インフラストラクチャ：**
Laravel 11/12+ では、旧来の `EventServiceProvider` は廃止された。システムイベント `Registered` の手動ディスパッチを、フレームワーク組み込みのリスナー（`SendEmailVerificationNotification`）に正しく結び付けるため、`bootstrap/app.php` に自動検出が設定されている：

```php
->withEvents(discover: [
    __DIR__.'/../app/Listeners',
])
```

**これにより、本番環境でコマンド `php artisan event:cache` によるイベント紐付けの正しいキャッシュが保証される。**

---

### AUTH-5: MVP／開発段階でのメール通知の同期送信

**Status:** Accepted

**決定：** カスタム通知で `ShouldQueue` を使わず、メールの送信を同期的に（HTTP リクエストの中で）実行する。

**理由（開発の実用主義とデバッグ上の障壁）：**

- **Queue には worker が必要：** インターフェース `ShouldQueue` はタスク自体を実行せず、キューに入れるだけである。プロセス（`php artisan queue:work` または `queue:listen`）が起動していなければ、タスクは処理されず、メールも送信されない。
- **`database` ドライバーの問題：** `database` ドライバーを使うと、タスクがテーブル `jobs` に溜まり、実際には送信されていないのに、システムが動いているかのような錯覚を生む。
- **Operational Complexity（運用の複雑さ）：** dev/CI でのキューの維持には、ワーカーの起動と監視（多くは Docker 内で）が必要であり、環境を複雑にし、ビジネスロジックの開発から注意をそらす。
- **デバッグの透明性：** 同期モードでは、SMTP のエラー（誤ったキー、タイムアウト、ネットワークの問題）が HTTP レスポンスやログにすぐ現れ、迅速なフィードバックが得られる。

**Trade-off（トレードオフ）：**

- **スレッドのブロック（Latency）：** メール送信がリクエストの中で行われるため、登録のレスポンス時間が増える（約200〜500ms）。MVP とテスト環境では許容できる。

**キューへの復帰条件（リファクタリングのトリガー）：**
このアーキテクチャ上の決定は、プロジェクトが **Production/Staging** の段階に移行し、次の条件が満たされたときに見直される：

1. キューブローカーとして **Redis** を使用する（`QUEUE_CONNECTION=redis`）。
2. 安定した worker プロセスがある（**Supervisor** または **Laravel Horizon** 経由）。
3. 負荷が増え、同期送信がレスポンス時間と UX に悪影響を及ぼし始める。

**補足（2026-07-31）：** `ShouldQueue` は、最初から `VerifyEmailNotification`/`WelcomeNotification` に実装済みである — `QUEUE_CONNECTION=sync` の場合、インターフェースはアーキテクチャ上存在するが非アクティブである（ジョブは同じプロセス内で即座に実行される）。これは「async ではなく sync を選んだ」のではなく、「async の経路は用意されているが、有効化は後回しにしている」ということである — `architecture-learnings.md` を参照。

---

### AUTH-6: verify/resend のビジネスロジックをコントローラーに置く

**Status:** Accepted

**決定：** `AuthService` に切り出さない

**理由：** メール認証は独立したドメインである。これを `AuthService` と混ぜると SRP に違反する

**Trade-off：** 「薄いコントローラー」のルールからのわずかな逸脱

**境界：** `AuthService` は register/login/logout を担当する。認証は別の責務である

**見直しの条件（リファクタリングのトリガー）：**
認証のロジックが複雑なビジネス要件を抱え始めた場合。例：メール認証に対するボーナスポイントの付与、API リクエストによる CRM システム（Bitrix/Amo）での購入者プロフィールの自動作成、マーケティングメールのチェーン送信。これらの条件が生じたら、ロジックはコントローラーから、専用の `EmailVerificationService`（ただし `AuthService` ではない）に切り出される。

---

### AUTH-7: 登録時の Welcome メールと認証リンクの同時送信 — v1

**Status:** Superseded by AUTH-16

**決定：** イベント `Registered` の発生と同時に、2つの独立した通知（`VerifyEmailNotification` と `WelcomeNotification`）を送信する。

**理由（素早いエンゲージメントという UX 優先）：**

- **即時のオンボーディング：** ユーザーは、自分のアカウントが作成されたという確認（Welcome）をすぐに受け取り、同時にアカウント有効化のための技術的なリンクも受け取る。これにより、反応が良く素早いインターフェースという印象が生まれる。
- **マーケティング上の文脈：** e-commerce では、購入者の関心を「熱い」段階で捉えることが重要である。Welcome メールには、最初のウェルカム割引クーポンをすぐに含めることができ、ユーザーが認証を早く済ませ、購入のためにサイトに戻るよう促せる。

**Trade-off（リスクとトレードオフ）：**

- **メールの結合（Email Threading）：** メールクライアント（Gmail、Outlook）は、同じ送信者と極めて短い時間差のため、2通を1つのスレッドにまとめることがある。そのため、ユーザーは Welcome テンプレートの下に隠れた技術的な認証リンクに、物理的に気づかない可能性がある。
- **SMTP ドメインの評判への負荷：** まだ認証されていない（偽の、または「壊れた」可能性のある）ユーザーへのメール送信は、SMTP ゲートウェイに余計なトラフィックを生み、バックエンドのドメインのスパムスコアをわずかに下げる可能性がある。

**リスク軽減（Mitigation）：**

- **件名の分離（Subject Lines）：** メールロボットによるスレッド結合の可能性を最小化するため、根本的に異なる件名（例：_「YourShop へのご登録ありがとうございます！」_ と _「アカウントを有効にするためメールアドレスをご確認ください」_）を使う。
- **フロントエンドでの視覚的な強調：** 登録成功後の Next.js の画面は、ユーザーの注意を厳格に引きつける：_「確認用のメールを送信しました。ウェルカムボーナスをご利用になる前に、メールをご確認ください」_。

**見直しの条件（リファクタリングのトリガー）：**

1. プロダクト分析（Product Analytics）が、ユーザーが2通のメールの中で「迷子になる」ことが原因で、認証の完了率（Verification Rate < 70〜80%）が低いことを示した場合。
2. **順次送信フロー（Sequential Flow）** への移行：ボーナス付きの Welcome メールは、イベント `Illuminate\Auth\Events\Verified`（メールの認証が成功）が発火した後にのみ送信する。これは、購読者データベースの衛生の観点から、よりクリーンな慣行とされる。

---

### AUTH-8: ミドルウェア 'verified' の段階的な適用（重要なビジネスプロセスの分離）— v1

**Status:** Superseded by AUTH-17

**決定：** ユーザーのセッション全体をグローバルにブロックするのではなく、ミドルウェア `verified` を、注文の確定と支払いのエンドポイントにのみ、ピンポイント（段階的）に割り当てる。

**理由（UX の自由度とビジネスコンバージョンの優先）：**

- **「認証の行き止まり」の防止（Dead End UX）：** 基本的なルート（`/me`、`/logout`）をブロックすると、登録時にメールを間違えたユーザーは、アカウントからログアウトすることも、データを変更することもできなくなる。システムは、アカウントがどのような状態であっても、プロフィールとセッション終了の仕組みへのアクセスを維持しなければならない。
- **カートへの障壁の低減（Soft Onboarding）：** e-commerce では、厳しい技術的制約なしに、ユーザーが自由にカートを構成し、カタログを閲覧し、マイページを見られるようにすることが極めて重要である。カートへの追加は購入の意思であり、認証の要求で中断してはならない。
- **明確な責任の境界：** メール認証は、ビジネスを偽の注文、決済ゲートウェイへのスパム、詐欺（Fraud Prevention）から守る。したがって、保護はトランザクション（Checkout / Payments）への移行の段階でのみ有効になる。

**API ルートへのアクセスコントラクト：**

| API ルートのグループ      | エンドポイント                              | `auth:sanctum` が必要 | `verified` が必要 | 根拠                                                                                    |
| :------------------------ | :------------------------------------------ | :-------------------: | :---------------: | :-------------------------------------------------------------------------------------- |
| **Public Catalog**        | `GET /products`, `/categories`              |          ❌           |        ❌         | ゲストと SEO 用の検索ロボットが利用可能。                                               |
| **Auth & Verification**   | `/login`, `/register`, `/verify`, `/resend` |          ❌           |        ❌         | アクセス管理の基本的な仕組み。                                                          |
| **Session & Profile**     | `GET /me`, `POST /logout`, `/profile/edit`  |          ✅           |        ❌         | プロフィールデータの変更とログアウトを許可する。                                        |
| **Cart Operations**       | `GET/POST /cart`                            |          ✅           |        ❌         | カートへの追加に、確認済みの email は不要。                                             |
| **Checkout & Financials** | `POST /checkout`, `/orders`, `/payments`    |          ✅           |        ✅         | **重要ゾーン。** トランザクションの実行は、認証済みの顧客にのみ許可される。            |

**Trade-off（トレードオフ）：**

- 未認証のユーザーが、カートや住所のテーブルに「ゴミ」レコードを生成する可能性がある（チェックアウト前に保存される場合）。データベースへの負荷は、放棄されたカートを削除するバックグラウンドのクリーンアッププロセスで制御される。

**いつ追加し、管理するか：**
このコントラクトは直ちに発効する。注文確定用のコントローラー（`OrderController`、`CheckoutController`）の作成時に、ミドルウェア `verified`（またはリダイレクトの代わりに JSON 403 を返すカスタムの API 拡張）を、API のルーティングレベルで導入しなければならない。

---

### AUTH-9: パスワード変更時の全デバイスでのセッション無効化

**Status:** Accepted

**決定：** パスワードのリセット時、またはユーザーの BAN 時に、`DB::table('sessions')->where('user_id', $user->id)->delete()` によってレコードを直接削除する。

**理由（Database Driver に対する実用主義）：**

- **即時の効果：** 現在のスタックはセッションに `database` ドライバーを使用しており（テーブル `sessions` に明示的なフィールド `user_id` がある）、1つの SQL クエリで、すべてのデバイス（デスクトップ、モバイル、タブレット）上のユーザーのアクティブなトークン／セッションがすべて即座に破棄される。次のリクエストで、Next.js のフロントエンドは正当な `401 Unauthorized` を受け取る。
- **KISS（Keep It Simple, Stupid）：** この解決策は、サービス／コントローラーにちょうど1行のコードを必要とするだけで、システムに余計な論理的負荷を一切かけない。

**Trade-off（トレードオフ）：**

- **スケール時のロック：** オンライン数が増えると、リレーショナルテーブル `sessions` への `DELETE` 操作の頻発が、行ロック（row locks）を引き起こし、ディスク（I/O）に負荷をかける可能性がある。
- **ドライバーへの依存：** このコードは SQL テーブルの構造に強く依存しており、プロジェクトが高性能な `SESSION_DRIVER=redis` に切り替わった時点で、完全に動作しなくなる。

**どのように見直されるか（Production-ready な Redis 戦略）：**
Redis に移行する際、ユーザーテーブルにフィールド `session_version` を導入したり、カスタムのミドルウェアを書いたりは**しない**（ユーザーのあらゆる操作のたびに DB への余計な `SELECT` クエリが発生し、キャッシュの意味がなくなるため）。

代わりに、Redis の機能を使って **セッションインデックス（User-to-Session Mapping）** を実装する：

1. 認証時に、ユーザーのセッションハッシュが、キー `user:sessions:{user_id}` の Redis の Set 構造に自動的に追加される。
2. パスワード変更時に、バックエンドは $O(1)$ の1回の操作でこの Set を問い合わせ、特定のユーザーのすべてのセッションハッシュを取得し、それらに対して `DEL` を実行する。
3. これにより、メインの MySQL データベースにまったく触れることなく、数マイクロ秒ですべてのデバイスが無効化される。

---

### AUTH-10: パスワードリセットでのレスポンスの統一（Unified Error Messages）とサイドチャネル攻撃からの保護

**Status:** Accepted

**決定：** Laravel の内部ブローカーの動作結果がどうであれ（成功、存在しない Email、ブローカー内部の Throttle）、エンドポイント `password/forgot` に対して、テキスト `auth.reset_link_sent` を含む同一の `200 OK` レスポンスを返す。

**理由（Side-Channel State Attacks からの保護）：**

- ブローカーの応答 `Password::RESET_THROTTLED` に対してステータス `429` を返したり、エラーテキストを変えたりすると、システムはアカウントが存在するという事実を漏らしてしまう。攻撃者は、内部のトークンタイマーの挙動によって email の有効性を判定できてしまう。コントローラー内でブローカーの状態を完全に隠蔽することで、情報漏洩ゼロ（Zero-Knowledge）が保証される。

**リスク軽減（SMTP の保護）：**

- 雪崩のようなスパムからサーバーを守るため、エンドポイント `password/forgot` と `password/reset` は、ネットワークミドルウェア `throttle:5,1` で保護されている。
- レート制限は、厳密に受信 IP アドレスのレベルで機能する。同じクライアントが1分間に5回を超えるリクエストを行った場合、ミドルウェアは、リクエストボディで渡される email アドレスに関係なく、厳格に **`429 Too Many Requests`** を返す。これにより、ネットワーク保護がデータベースのビジネスロジックから完全に分離される。

---

### AUTH-11: UX とソフトデリートのサポートのため、登録段階での User Enumeration を許容する

**Status:** Accepted

**決定：** email の一意性の標準的なバリデーション `Rule::unique('users', 'email')->whereNull('deleted_at')` を使い、明示的なエラー `422 Unprocessable Entity`（_「この email はすでに使われています」_）を返す。

**理由（プロダクトの UX とデータ衛生の優先）：**

- **プロダクトの UX：** 登録時に email の存在を隠そうとすること（例：`200 OK` を返し、「すでに登録されています」という隠れたメールを送る）は、実際の顧客にとってのフローを致命的に複雑にする。ユーザーは、ログインフォームに切り替えるか、タイプミスを直すために、入力エラーを即座に確認できなければならない。
- **ソフトデリートのビジネスロジック：** ユーザーにはソフトデリートを使う。顧客が古いプロフィールを削除した場合（`deleted_at` が null でない）、その email は暗号学的に解放される。スコープ `whereNull` により、元の顧客は、DB に衝突を起こすことなく、白紙の状態から再登録できる。

**Trade-off（情報セキュリティ上のリスク）：**

- エンドポイント `POST /api/v1/auth/register` は、総当たり攻撃（User Enumeration）に対して脆弱になる。攻撃者は、email アドレスのリストを、その所有者が当店の顧客かどうかについて、プログラムで確認できる。

**リスク軽減（Mitigation）：**

- **積極的な Rate Limiting：** 登録エンドポイントには、Redis による厳しいリクエスト制限 — `throttle:10,1`（1つの IP アドレス／セッションあたり1分間に最大10回の試行）— が課される。これにより、大規模なデータベースのブルートフォースは、攻撃者にとって経済的にも技術的にも非効率になる。
- **ドメインの非対称な保護：** 脆弱性は登録段階のみに限定されている。パスワードリセットのエンドポイント（**AUTH-10**）は、完全に保護されたままで、汎用的な generic レスポンスを返す。

**補足：** zero-DB-write registration（AUTH-17）への移行により、このリスクの意味合いは狭まった — `/register` での `422` は、単なる未完了の登録ではなく、既存のアクティブな `User` を意味するようになった。enumeration の脆弱性自体は残り、リスク軽減策は変わらない。

---

### AUTH-12: Login/Register のレスポンスボディを無視する — `/me` を唯一の真実の源とする — v1

**Status:** Superseded by AUTH-18

**背景：**
`AuthController::login()` と `AuthController::register()` は `UserResource` を返す — `AuthController::me()` が返すものとまったく同じ Resource である。技術的には、フロントエンドは `/login` と `/register` のレスポンスでユーザーの完全なデータをすぐに受け取るが、意図的にそれを使わない：`sessionApi.login()` と `sessionApi.register()` は `Promise<void>` として型付けされている。

レスポンスボディを読む代わりに、ミューテーションの `onSuccess` は `queryClient.invalidateQueries()` を呼び出し、別の `GET /me` を発火させる — つまり、バックエンドが同じ `UserResource` を、2回目のリクエストで再び組み立てて送信する。

**決定：**
ユーザーデータの唯一の真実の源（single source of truth）— `useMeQuery` のみ — のために、HTTP リクエストを意図的に重複させる。

**理由（アーキテクチャ上の利点）：**

1. **再訪への耐性：** ユーザーにすでにアクティブな Sanctum セッション（クッキーが有効）があり、サイトを開き直した場合 — `login`/`register` はまったく呼ばれない。「自分は誰か」を知る唯一の方法は、`/me` 経由でバックエンドに尋ねることである。もしデータが `login`/`register` からのみキャッシュに書き込まれるなら、再訪時にはキャッシュが空のままになる。
2. **タブ間の一貫性：** TanStack Query のキャッシュはブラウザのタブ間で共有されない。フォーム経由でログインしたタブは、すでに開いている隣のタブにユーザーデータを直接「共有」できない — 両方が `useMeQuery` を単一の情報源として頼る。
3. **競合状態からの保護：** もしデータが2か所（ミューテーションの `onSuccess` と `useMeQuery`）からキャッシュに書き込まれると、並列リクエストやプロフィール更新時に、どのバージョンがキャッシュ内で最新かという不整合のリスクが生じる。書き込み元が1つなら、この問題は完全に解消される。
4. **データ変更後の無効化の簡単さ：** プロフィールの更新や DB での role の変更は、理論上データを返し得るあらゆる場所から最新データを手動で渡す必要なく、`invalidateQueries` によって取り込まれる。

**Trade-off（トレードオフ）：**
* `login`/`register` のたびに HTTP のラウンドトリップが1回余分に発生する（通常の条件で約20〜100ms）。
* バックエンドは、1つの論理的なイベントに対して `UserResource` の組み立てを2回行う — DB への追加の負荷はない（`User` モデルはセッションからすでにメモリ上にある）。負荷はシリアライズのみ。

**見直しの条件（リファクタリングのトリガー）：**
負荷が増加し、login/register でのラウンドトリップ1回分の節約が測定可能なほど重要になった場合（例：テストや連携での高頻度の再認証）、`login`/`register` のレスポンスボディから直接 `queryClient.setQueryData()` を使う方式に移行し、新しい login イベントがないケースのフォールバックとして `useMeQuery` を残すことができる。現在のプロジェクトの規模では、そのような最適化は時期尚早である。

---

## V2 — Zero-DB-write 登録アーキテクチャ

> 以下の記録はすべて、ISSUE-2（`issues-list.md`）の発見 —
> `POST /register` での `User` の即時作成によるメールの不正確保（email squatting）—
> をきっかけとした見直しの中で承認された。以下の各 AUTH は、上記の
> 対応する v1 の記録を supersede する。相互参照を参照のこと。

---

### AUTH-13: Signed URL は user_id ではなく registration token に署名する

**Status:** Accepted (supersedes AUTH-1)

**決定：** `URL::temporarySignedRoute` は、署名の仕組みとして引き続き使用する（Laravel のプリミティブ自体に変更はない）が、署名の対象は `user_id` ではなく **`registration token`** とする — 認証メールの送信時点では、MySQL に `User` がまだ存在しないため（AUTH-15 参照）。`registration token` は、Redis 上の一時レコード `registration:{token}` の識別子である。`trustProxies` は `at: '*'` から `env('TRUSTED_PROXIES', '127.0.0.1')` に絞られた — すべてのプロキシを無差別に信頼しない、より安全なデフォルトである。

**理由（アーキテクチャ上の利点）：**

- v1 の利点（シンプル、ネイティブ、OTP より安全、プロキシ配下での正しい動作）はすべて変更なく維持される。
- **責務の分離：** Signed URL はリンクの真正性と有効期間を担当し、Redis はメール認証までの pending registration の一時保存を担当する。
- 認証が成功した後、registration は Redis から削除され、`User` は認証済みの email を持つ状態で、1つの DB トランザクション内で MySQL に作成される。

**Trade-off（トレードオフ）：**

- メールには署名付きの完全な URL（`?signature=...`）が送られる — v1 と同じリスク。
- **v1 と比べて新しい点：** Pending registration は Redis 上の stateful な状態であるため、Redis が登録のクリティカルパスの一部になる（詳細は AUTH-15）。

**リスク軽減（Mitigation）：** v1 から変更なし — プロキシとしてのフロントエンド、POST 専用の認証（AUTH-2）、`router.replace` による URL のクリーンアップ。

---

### AUTH-14: 認証リンクと pending registration の TTL — 単一のライフサイクル、60分

**Status:** Accepted (supersedes AUTH-3)

**決定：** TTL は同じ `60分` だが、今回は **2つ** のエンティティを同時に管理する：Signed URL（v1 と同様）と、Redis のレコード `registration:{token}`。

**理由（セキュリティ、UX、一貫性のバランス — v1 の UX／業界標準の根拠は維持される）：**

- **単一のライフサイクル：** Signed URL と Redis のレコード `registration:{token}` は同じ時間だけ存続する。TTL が切れると、リンクは無効になり、pending registration は Redis から自動的に削除される。
- **クリーンアップタスクが不要：** 未認証の登録は MySQL に入らないため、それらを削除するための Cron やスケジュールコマンドは不要である — v1 からの直接的な改善。v1 では冪等性（`409`）が唯一の保護であり、認証が最後まで完了しなかった場合には、「ゴミ」の `User` レコードが永久に DB に残っていた。

**Trade-off（トレードオフ）：** v1 と同じ、60分の「攻撃の窓」というリスク。

**TTL 経過後の挙動：**

- `registration:{token}` は Redis にもはや存在しない。
- Signed URL は無効と見なされる。
- `User` は作成されない — v1 では、この時点で `User` が未認証のまま DB にすでに存在していた。
- ユーザーは登録をやり直し、新しい認証リンクを受け取る必要がある。
- pending registration がまだ存在する間は、ユーザーは `resend` で認証メールの再送を要求できる。

**重要：** TTL `60分` は、あくまで初回登録とその認証フローに関するものである。パスワードリセットには独自のライフサイクルと TTL があり、この AUTH から自動的に継承されてはならない。

**未解決の問題（記録時点では未決定）：** `resend` を再度リクエストした場合の挙動 — TTL が延長されるのか、古いトークンの代わりに新しいトークンが発行されるのか、それとも全体の期間が切れるまで両方が有効なままなのか。`EmailVerificationController::resend()` の実装前に、明示的な決定が必要である。

---

### AUTH-15: Zero-DB-write の pending registration — User は認証後にのみ作成される

**Status:** Accepted (supersedes AUTH-4)

**決定：**
`POST /register` は、MySQL に `User` を作成せず、`event(new Registered($user))` もディスパッチしない — この段階では `$user` が物理的に存在しないため。その代わりに、データ（`email`、`hashed password`、`first_name`、`last_name`、`phone`）は、Redis にキー `registration:{token}` で、TTL（AUTH-14）付きで一時保存される。resend のために、追加のインデックス `registration:email:{email}` → `token` を使用する。`User` は、Signed URL の検証が成功した後にのみ、DB トランザクション内で、直ちに `email_verified_at = now()` を設定して MySQL に作成される。

**理由（症状にパッチを当てるのではなく、ISSUE-2 の根本原因を取り除く）：**

- v1 が `MustVerifyEmail` ＋ 手動の `Registered` ディスパッチに依存していたのは、まさに `User` が `/register` で即座に作成されていたからである。これが ISSUE-2 の原因である：未認証の、他人のものである可能性のあるレコードが、`users.email` の `UNIQUE` インデックスで `email` を即座に予約してしまう。
- Zero-DB-write はこれを完全に解消する：email が認証されるまで、`users` には予約・クリーンアップ・匿名化が必要なレコードが一切存在しない。
- Redis の TTL（AUTH-14）が未完了の登録を自動的に削除する — `users` のクリーンアップ用 cron はまったく不要であり、ISSUE-2 の検討で候補に上がって却下された TTL クリーンアップ方式とは異なる。
- v1 の race-condition 対策（リスナーが早すぎる場合の `ModelNotFoundException`）は自然に解消される — 認証の時点まで、DB に書き込むものがそもそもない。

**認証のコントラクト：**

- Signed URL（AUTH-13）は、`user_id` ではなく `registration token` を含む。
- バックエンドは、署名と、Redis 内の `registration:{token}` の存在を検証する。
- DB トランザクション内で、`email_verified_at = now()` を設定した `User` が作成される。
- commit が成功した後、pending registration は Redis から削除される。
- セッションは、認証が成功した後にのみユーザーに対して作成される。

**Trade-off（トレードオフ）：**

- 標準の Laravel の登録フロー（`Registered` → `SendEmailVerificationNotification`）をやめ、独自の pending-registration フローを採用する — `MustVerifyEmail` は、もはや初回登録の仕組みではない。
- **Redis が登録の必須の依存先になる** — v1 ではセッションの任意の高速化にすぎなかったものが、クリティカルパスになった。Redis が利用できないと、新規アカウントの作成が完全にブロックされる。
- Pending registration は、Redis に hashed password を含む（plaintext ではないが、MySQL の外にある機密データである）。

**責任の境界：** v1 から変更なし — `AuthService` → register/login/logout、メール認証 → 独立した `EmailVerificationController`（AUTH-6）、pending registration → Redis、ユーザー作成 → 認証フロー。

**未解決の問題（記録時点では未決定）：** 最初の試行の TTL が切れる前に、同じ email に対する2件の同時登録が衝突した場合の挙動 — 2回目の試行は `422`（email が最初の pending 登録によって「使用中」）を受け取るべきか、それとも `registration:email:{email}` を上書きする（それによってスロットを奪う）べきか？ どちらの選択肢も妥当であり、挙動は確定していない。

---

### AUTH-16: 認証メールと Welcome メールの順次送信

**Status:** Accepted (supersedes AUTH-7)

**決定：** 登録の段階では、`VerifyEmailNotification` のみを送信する。`WelcomeNotification` は、メールの認証が成功し、`User` が作成された後にのみ送信する。

**理由（v1 の2つのトレードオフを両方とも解消する。単なる UX の好みではない）：**

- v1 は、記録時点では同時送信が唯一の選択肢であり、見直しの明確なトリガーとして「認証が `User` の後に厳密に行われるなら、順次フローへ移行する」と定めていた。AUTH-15 により、このトリガーは不可避となった — 認証の前には `User` が物理的に存在しないため、`WelcomeNotification` は v1 で懸念された「メールの結合」の時点よりも前に送信することが**できない** — 件名の言い回しだけでなく、アーキテクチャ自体が問題を封じている。
- 未完了の、放棄された登録に対しては Welcome メールが送信されない — 以前は UX 上の希望（v1 のリスク軽減策）だったものが、今やアーキテクチャによる保証である（`User` がなければ、Welcome を送る対象がない）。
- 偽の可能性のあるアドレスへの余計な SMTP トラフィックが減る — v1 のトレードオフにあった「SMTP ドメインの評判への負荷」というリスクに対する直接的な改善。

**新しいフロー：**

```text
POST /register
    ↓
Redis pending registration
    ↓
VerifyEmailNotification
    ↓
ユーザーがリンクを開く
    ↓
POST /verify
    ↓
User の作成 + email_verified_at
    ↓
WelcomeNotification
    ↓
session の作成
```

**Trade-off（トレードオフ）：**

- Welcome メールが v1 より遅く届く（以前は即時だったが、リンクのクリック後になる）。
- ユーザーが email を一度も認証しなければ、Welcome メールはまったく送信されない — 想定どおりの挙動であり、バグではない。
- 認証フローの内部で `WelcomeNotification` を個別に呼び出す必要がある（ここでは使用しない `Registered` イベント経由ではない — AUTH-15 参照）。

**責任の境界：** `VerifyEmailNotification` → pending registration の作成後に送信される。`WelcomeNotification` → 認証済みの `User` の作成が成功した後にのみ送信される。

**見直しの条件：** v1 から変更なし — CRM 連携、ボーナス、マーケティングオートメーションにより、独立した application service／イベント駆動フローへの切り出しが必要になる可能性がある。

---

### AUTH-17: ミドルウェア 'verified' の段階的な適用 ＋ guest-first browsing

**Status:** Accepted (supersedes AUTH-8)

**決定が見直された理由：**

- **ISSUE-2（`issues-list.md`）：** email の認証前に `User` を作成すること（v1）により、誰でもそのメールボックスへのアクセス権なしに `someone-else@gmail.com` で登録できた。ユニークインデックス `users.email` は、レコードが削除されるまで、他人のアドレスを事実上予約していた。
- **TTL クリーンアップの問題：** Cron による未認証アカウントの削除という案が検討されたが、却下された。ブロック期間を制限するだけで、一時的な登録が `users` に残り続け、別途クリーンアップのインフラが必要になるため。
- **Account Restoration：** そのようなアプローチは、ソフトデリートのロジックや、`withTrashed()` による将来のアカウント復元（ISSUE-1）と交差してしまう。

**決定：**

1. **Guest-first browsing：** カタログはアカウントなしで利用できる。Wishlist はクライアント側（`localStorage`）に保存され、バックエンドのアイデンティティを必要としない — 登録／ログイン時に、別のタスクとしてアカウントに移行される。
2. **Zero-DB-write pending registration** — 仕組みは AUTH-15 に完全に記述されている。
3. **registration token への Signed URL** — 仕組みは AUTH-13 に完全に記述されている。
4. **認証後にのみセッション：** email の認証前は、ユーザーは `User` もセッションも持たない。`POST /register` の後は、メールを確認する必要があるというメッセージのみが表示される。

**更新された API ルートへのアクセスコントラクト**（AUTH-8 v1 の表を全面的に置き換える — 変わったのは `verified` 列だけでなく、guest/account のアクセスの意味合いである）：

| ルートのグループ | エンドポイント | 利用可能な対象 | `auth:sanctum` | `verified` | 根拠 |
|---|---|---|---:|---:|---|
| **Public Catalog** | `GET /products`, `/categories` | Guest + Account | — | — | SEO ロボットを含むすべての人が利用可能。 |
| **Wishlist (guest)** | `localStorage` | Guest | — | — | ユーザーの身元を必要としない。アカウントへの移行は別のタスク。 |
| **Registration & Verify** | `/register`, `/verify`, `/resend` | Guest → Account | ❌ | ❌ | `users` ではなく、Redis の pending registration を扱う。 |
| **Login** | `/login` | Account | ❌ | ❌ | 既存の、つまりすでに認証済みの `User` を必要とする。 |
| **Session & Profile** | `GET /me`, `POST /logout`, `/profile/edit` | Account | ✅ | ❌* | 未認証の `User` は MySQL にもはや存在しない。セッションは認証後にのみ発行される。 |
| **Cart Operations** | `GET/POST /cart` | Account | ✅ | ❌* | すでに認証を通過した既存のユーザーのみが利用可能。 |
| **Checkout & Financials** | `POST /checkout`, `/orders`, `/payments` | Account | ✅ | ✅ | **重要ゾーン。** トランザクションの実行は、認証済みのユーザーにのみ許可される。 |

\* `verified` は、`/me`、`/logout`、`/profile`、`/cart` については、実質的に冗長である。アーキテクチャ上、既存のすべての `User` はすでに認証済みの email を持つため。ミドルウェアは defense-in-depth として残してもよいが、第一の保護の仕組みではない。

**v1 との主な違い：** v1 では、「Cart Operations」は「verified を必要としない」という独立した項目だった。それは、要求する対象があったからこそである — ゲストは、アカウントなしでもカートに商品を入れることが許されていた（v1 の表には明示されていないが、ソフトオンボーディングによって暗黙に想定されていた）。v2 では、カートは `auth:sanctum` を必要とする — ゲストの操作（wishlist、閲覧）は完全にクライアント側に移されており、「未認証ユーザーによる DB 内のゴミレコード」（v1 のトレードオフ）は当てはまらなくなる：`User` が存在しない限り、ゴミを作る主体がいない。

---

**Trade-off（トレードオフ）：**

- **Resend verification：** もはや `user_id` に頼れないため、追加の Redis インデックス `registration:email:{email}` が必要。
- **再クリック：** 認証が成功した後、`registration:{token}` は削除される。そのため、再クリックしても、以前の `409 email_already_verified` を返すことはできない（v1 では `markEmailAsVerified()` の冪等性によって返されていた）。個別の対応 — 短命の `verified:{token}` マーカー、または `410 Gone` — が必要。**記録時点では未解決。**
- **Redis への依存：** AUTH-15 を参照。
- **User Enumeration：** AUTH-11 のリスクは、狭まった形で残る — そこの補足を参照。

**ISSUE-1（Account Restoration）との関係：** ソフトデリートと `withTrashed()` は、すでに存在する、認証済みのユーザーにのみ適用される。Redis 上の pending registration はアカウントではなく、復元のロジックには関与しない。

**未解決の問題、記録時点では未決定（`issues-list.md` を参照）：**

- 同じ email に対する2件の同時登録の衝突（AUTH-15 と重複）。
- `/resend` に対する、`/register` とは別個のレート制限（AUTH-11 は `/register` のみをカバー）。
- 将来の認証済みユーザーのソフトデリート（ISSUE-1）における `email_active` について、匿名化 vs generated column — 方向性は示されている（`withTrashed()` との衝突のため、匿名化ではなく generated column）が、正式な AUTH はまだ書かれていない。

---

### AUTH-18: `/me` によるセッション状態 — zero-DB-write 後の更新されたコントラクト

**Status:** Accepted (supersedes AUTH-12)

**背景：**
v1 では、`/register` は `UserResource`（`/me` と同じもの）を返していたが、フロントエンドは唯一の真実の源のためにそれを無視していた。zero-DB-write（AUTH-15）への移行後、これは単なるアーキテクチャ上の好みではなく、必然となった：`/register` は `UserResource` を**物理的に返せない**。この段階では `User` が存在せず、認証の後にのみ作成されるためである。

**決定：**
`GET /api/v1/auth/me` は、現在の認証済みユーザーの唯一の真実の源であり続ける — 立場自体は v1 から変わらないが、その根拠は拡張される。

- `sessionApi.login()` は、レスポンスボディの `UserResource` を使わない（v1 と同じ）。
- `sessionApi.register()` は、`UserResource` をそもそも**期待しない** — v1 のように「無視する」のではなく、`{ message: string }`（「メールを確認してください」）形式のレスポンスとして型付けされる。
- login が成功した後、フロントエンドは `useMeQuery` を通じて状態を更新する（v1 と同じ）。
- email の認証が成功した後も、フロントエンドは `useMeQuery` を通じて状態を更新する — v1 にはなかった新しいケース（認証は以前、初めてセッションを作成するのではなく、既存の `User` に印を付けるだけだった）。
- アプリの初回読み込み時、フロントエンドは常に `/me` を確認する（v1 と同じ）。

**理由（v1 の根拠1〜4 — 再訪への耐性、タブ間の一貫性、競合状態からの保護、無効化の簡単さ — は変更なく有効）：**

5. **新規 — zero-DB-write 登録との互換性：** `/register` の後、ユーザーはそもそもまだ認証されていない（「レスポンスのトークンを使わない」というだけでなく、「セッションが物理的に存在しない」）ため、フロントエンドは登録の一環として `/me` を取得しようとせず、pending 状態（「メールを確認してください」）を表示する。認証の後に、バックエンドが初めてセッションを作成し、その後 `/me` が最新の `UserResource` の情報源になる。

**フロントエンドのフロー（AUTH-15/17 に合わせて更新）：**

```text
Initial app load
      ↓
GET /me
      ↓
401 → Guest
200 → Authenticated User


POST /login
      ↓
Sanctum session
      ↓
invalidate/refetch /me
      ↓
Authenticated User


POST /register
      ↓
Redis pending registration
      ↓
Guest + "Check your email"


POST /verify
      ↓
User created + Sanctum session
      ↓
invalidate/refetch /me
      ↓
Authenticated User
```

**Trade-off（トレードオフ）：**

- login の後、そして**今回は**認証の後にも、追加の `GET /me` が発生する（v1 にはそのようなケースはなかった — トレードオフが1か所増えた）。
- `/register` は、もはや `UserResource` をまったく返さない（v1 では返していたが、無視されていた）。フロントエンドは、登録を独立した `pending verification` 状態として扱わなければならない — v1 に比べて新しい要件。v1 では、`/register` の後にフロントエンドがすぐにユーザーを認証済みと見なせた（`/me` で再確認するだけだった）。

**責任の境界：** v1 から変更なし — Auth のミューテーションはアクションを実行する、`/me` は現在の状態を伝える、TanStack Query は server state を保持する、Zustand は `User` の真実の源としては使わない。

**見直しの条件：** v1 から変更なし。

---

## Known / Accepted Risks（受容されたリスク）

> この表は、現在の（v2、zero-DB-write の）アーキテクチャを反映している。

| リスク | レベル | 軽減策 | ステータス |
| ----------------------------------------------------- | ------- | ------------------------------------------------------------------------------------------------------------------------------------------------------ | -------- |
| フロントのクエリ文字列内の Signed params | Medium | 認証後の `router.replace` ＋ フロントでの `Referrer-Policy` | Accepted |
| ロボットによる自動認証（Safe Links） | Medium | Next.js が受信した `GET` を捕捉し、バックエンドに明示的な `POST` リクエストを送る | Accepted |
| 認証 URL に対するリプレイ攻撃 | Low | 認証の成功後、`registration:{token}` は Redis から削除されるため、同じ token の再利用でアカウントを再作成することはできない | Accepted |
| 認証時の競合状態（Race condition） | Low | `User` の作成は、ユニークな `email` を持つ DB トランザクション内で実行され、pending registration は commit の成功後にのみ Redis から削除される | Accepted |
| 同期 SMTP が HTTP スレッドをブロックする | Low | 開発段階と MVP でのデバッグの容易さのための意図的な妥協（AUTH-5 参照） | Accepted |
| 登録中に Redis が利用できない | Medium | Redis はクリティカルパスの一部であり、pending registration の保存が成功しない限り、MySQL に registration は作成されない | Accepted |
| Pending registration が Redis に hashed password を含む | Medium | Password は hashed 形式でのみ保存され、registration の TTL は短く、Redis はキーの内容をログに記録してはならない | Accepted |

---

## Backlog — 次の段階のタスク

| タスク / 機能 | 実装のトリガー / アーキテクチャ上の意味 |
| --------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **ミドルウェア `verified` の導入** | 注文確定（`checkout`）と支払い（`orders/payments`）のエンドポイントの開発開始時。偽の注文からビジネスを守る。 |
| **`router.replace` によるブラウザ履歴のクリーンアップ** | Next.js フロントエンドでの認証 UI コンポーネントの実装時。認証後に「戻る」ボタンでユーザーがログインフォームに戻ってしまうバグを防ぐ。 |
| **`Referrer-Policy` ヘッダーの設定** | フロントエンド側（Vercel/Nginx）でのセキュリティ設定（Security Headers）。認証トークンがサードパーティの計測ツールに漏れるのを防ぐ。 |
| **メール通知の i18n（ローカライズ）** | ネットショップの国際市場への進出／Next.js フロントエンドでの多言語対応の追加時。 |
| **ソーシャルログイン（OAuth2 — Google）** | **Laravel Socialite** の統合。カートのコンバージョン最適化の段階に移るとき、ユーザーの登録を「ワンクリック」にして簡素化するために必要になる。 |
| **「ログイン状態を保持する」（Remember Me）の仕組み** | チェックボックス `remember` を `LoginDTO` 経由で Laravel のセッションガードに渡す。ブラウザのタブを閉じても Redis 内のユーザーのセッションが失効しないよう、フロントエンドの UX 改善に必要になる。 |
| **アクティビティとセッションのログ（Security Audit Log）** | テーブル `user_activities`（`ip`、`user_agent`、`logged_at`）の作成。e-commerce のセキュリティにとって重要で、将来、ユーザーのマイページでアクティブなセッションの一覧を表示し、メソッド `Auth::logoutOtherDevices()` を導入できるようになる。 |

---

_Last updated: 2026-09-18_
