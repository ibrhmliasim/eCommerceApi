# 未解決課題レジスター — 発見された、解決待ちの問題

> これは ADR ではない。開発・監査の過程で見つかった、アーキテクチャ上の
> 決定が必要だがまだ決定されていない事項をここに記録する。
>
> 問題が解決したら、該当する `*-audit-decisions.md` ファイルに新しい ADR として
> 記載し、ここの記録には `Resolved` の印と AUTH 番号への参照を付ける。
>
> バックエンド（Laravel）とフロントエンド（Next.js）の両方に関わる — 対象ドメインは auth に限定されない。

---

## 記録フォーマット

```
### ISSUE-N: 短いタイトル

**Domain:** Backend / Frontend / Fullstack
**Found in:** 発見した場所（ファイル、チャット、日付）
**Status:** Open / Decided (-> AUTH-X) / Won't Fix

**問題：**
具体的に何が問題なのか。

**なぜ重要か：**
修正しなかった場合の現実的な影響。

**解決案：**
1. ...
2. ...

**傾いている方向：**（暫定的な意見があれば。最終決定ではない）
```

---

## 未解決の問題

### ISSUE-1: ソフトデリート時に email の一意性が破綻する

**Domain:** Backend

**Found in:** `AuthService.php` の分析、チャット「Meeting Chat」、2026-07-31

**Status:** Open

**問題：**
`RegisterRequest` 内の `Rule::unique('users', 'email')->whereNull('deleted_at')` は、
ソフトデリート済みユーザーの email での登録を許可する。しかし `schema_final.sql` の
SQL制約 `email VARCHAR(255) NOT NULL UNIQUE` は `deleted_at` を知らないため、
`INSERT` を物理的にブロックする — Laravel は「OK」と言い、MySQL は「ダメ」と言う。
結果は 500（`server.internal_error`）で失敗するが、ビジネスロジック（AUTH-11）上は
通常の登録として成功すべきケースである。

**なぜ重要か：**
仮定上のエッジケースではない — ユーザーが古いアカウント削除後に同じ email を
再利用しようとした最初の機会に発生する。さらに ISSUE-2（下記参照）とも交差する —
所有者でない人が email を「確保」できてしまうなら、ソフトデリートのシナリオが
問題を悪化させる。

**解決案：**
1. 複合ユニークインデックス `(email, deleted_at)` — 脆い：同じ email を持つ2人の
   ソフトデリート済みユーザーが同じ timestamp になると、依然として衝突する
   （可能性は低いが、テストでの高速な delete/create の繰り返しでは起こり得る）。
2. ソフトデリート時の email 匿名化（`deleted_{id}_{original_email}`） —
   DB上で元の email を物理的に解放し、Laravel と SQL の不整合も生じない。
   より一般的な手法（GDPR 重視のシステム）。

**傾いている方向：** 案2 — より堅牢で、timestamp の精度に依存しない。

---

### ISSUE-2: 無防備な登録により他人の email を「確保」できてしまう

**Domain:** Backend（アーキテクチャ上の決定）、AUTH-7、AUTH-8 に影響

**Found in:** ISSUE-1 の議論、チャット「Meeting Chat」、2026-07-31

**Status:** See Below

**問題：**
AUTH-8 では、登録に email 検証済みであることを要求せず、アクセスは即座に
付与される（ソフトオンボーディング）。つまり誰でも、そのメールボックスへの
アクセス権がないまま `someone-else@gmail.com` で登録できる。アカウントが削除も
検証もされない限り、その email の本当の所有者は自分のメールで
アカウントを作成できない — ブロックは最初の登録の時点で発生し、
ソフトデリートとは無関係である（ISSUE-1 は症状の1つにすぎない）。

**なぜ重要か：**
AUTH-8（UX のための緩やかなオンボーディング）と、ユーザーの基本的な期待
（「これは私の email なのに、なぜ使えないのか」）との直接的な矛盾。
実際の本番環境では、サポートチケットや評判リスクの原因になり得る。

**解決案：**
1. 現状のままにして、TTL クリーンアップを追加する（cron で N 日より古い
   未検証アカウントを削除） — AUTH-8 の UX を維持しつつ、ブロック期間を制限する。
2. 完全なアクセスの前に email 検証を厳格に要求する（AUTH-8 の見直し） —
   より安全だが、登録時の摩擦が増える。
3. ハイブリッド：アクセスは即時（現状どおり）だが、email は「予約済みだが
   所有されていない」と見なす — N 時間以内に検証されなければ、email は
   再登録のために自動的に解放される（アカウント全体を削除する必要はない）。

**傾いている方向：** 別セッションが必要 — 将来のアカウント復元
（`User::withTrashed()`、すでに `AuthService::login()` の TODO にある）と直接関連する。

---

### ISSUE-3: 例外処理アーキテクチャ — 複雑さ vs 面接での説明しやすさ

**Domain:** Backend、アーキテクチャ方針

**Found in:** `error_code` / `ValidationErrorCodeMapper` の分析、チャット「Meeting Chat」、2026-07-31

**Status:** Open

**問題：**
現在の error_code アーキテクチャ（`ApiException`、`ValidationErrorCodeMapper`、
一元化された `bootstrap/app.php`）は、フロントエンドをバックエンドから
完全に i18n 非依存にできるが、標準的な Laravel のパターン
（`FormRequest` 内の `messages()`、作者の前のプロジェクトで使用）よりも
かなり複雑である。発見時点で、作者は `ValidationErrorCodeMapper`
（ルール名からコードへのマッピング）の仕組みを、段階的な解説なしには
自信を持って説明できずにいる。

**なぜ重要か：**
ポートフォリオの目的は、面接ですべての決定を擁護できることである。
その場で説明しにくいアーキテクチャは、目標とするポジションがそこまでの
i18n 非依存性を必要としない場合、投下した時間に見合わないリスクがある。

**解決案：**
1. error_code アーキテクチャを維持し、理解を自信が持てるレベルまで深める —
   decoupled/i18n のケースでより強く、自由に説明できれば
   アーキテクチャの成熟度をよりよく示せる。
2. FormRequest の `messages()` に戻す — 擁護しやすく、主流の Laravel の
   慣行に近いが、将来の多言語対応時にバックエンドで言語ごとに翻訳を
   重複して持つ必要がある。

**傾いている方向：** まだ未決定 — 説明の練習に時間が必要であり、
ロールバックを急がない。

---

### ISSUE-4: Docker と Nginx による本番相当環境への移行

**Domain:** Fullstack / Infra  

**Found in:** リポジトリ `eCommerceApi`（開発インフラを `php artisan serve` から Docker + Nginx に移行） [cite: 532]  

**Status:** Open  

**問題：** バックエンドを、ローカル環境（ランダムなポートでの `php artisan serve`）から、Docker コンテナ（PHP-FPM、Nginx、MySQL、Redis）と、80番ポート（`http://localhost`）での Nginx による単一のエントリポイントを用いた本番相当（Prod-like）のインフラに移行する[cite: 532]。  
これは decoupled アーキテクチャの根本的な変更である：リクエストのプロキシのロジック、ヘッダー（Host、X-Forwarded-For、X-Real-IP）の受け渡し、CORS、そして Laravel Sanctum の認証（cookie-based auth）の動作が変わり、フロントエンド（Next.js）の設定に直接影響する。

**なぜ重要か：** 1. **Dev/Prod の差異の解消（Dev/Prod Parity）：** `artisan serve` でのローカル開発は、ネットワーク通信上の実際の問題（Sanctum の CSRF クッキーの受け渡し、HTTPS/HTTP ヘッダー、リダイレクト時の Nginx の挙動、キュー Queues のタイムアウト）を隠してしまう。
2. **フロントエンドへの影響：** API のベース URL とエントリポイントの変更により、フロントエンドアプリのネットワーク層（Axios / Native Fetch インスタンス、`.env.local` / `NEXT_PUBLIC_API_URL`、Sanctum CSRF クッキーの処理）の再設定が必要になる。

**解決案：** 1. **バックエンド用のローカル Prod-like Docker ＋ Nginx（現在のステップ）：** バックエンドリポジトリで PHP-FPM、Nginx、MySQL、Redis のコンテナを起動する。Nginx は 80 番ポートで PHP-FPM のリバースプロキシとして機能する。Next.js のフロントエンドは `http://localhost` にアクセスするよう切り替える。
2. **共通の Dev-Stack（Monorepo/Infra-repo アプローチ）：** `/api/*` へのリクエストを Laravel コンテナに、それ以外の `/` へのリクエストを Next.js コンテナに振り分ける単一の Nginx プロキシを作成し、本番ドメインを完全に再現する。
3. **ローカル開発での Docker をやめる：** 日常の開発では `php artisan serve` を残し、Docker は CI/CD とデプロイの段階でのみ使用する。*（環境差によるエラーのリスクが残るため非推奨）*。

**傾いている方向：** **案1** を採用し、その後 Next.js と統合する。基本的な設定ファイルはすでにリポジトリ `eCommerceApi` に作成済み[cite_start][cite: 532]：
```text
eCommerceApi/
├── Dockerfile.dev
├── docker-compose.yml
├── .env.docker
└── docker/
      ├── entrypoint.sh
      └── nginx.conf

```

---

## Resolved（アーカイブ）

### ISSUE-2: 無防備な登録により他人の email を「確保」できてしまう

**Domain:** Backend（アーキテクチャ上の決定）、AUTH-7、AUTH-8 に影響

**Found in:** ISSUE-1 の議論、チャット「Meeting Chat」、2026-07-31

**Status:** Resolved

**問題：**

当初のアーキテクチャ（v1）では、`POST /register` が email の検証前に即座に MySQL に `User` を作成していた。これにより、誰でもそのメールボックスへのアクセス権がないまま `someone-else@gmail.com` で登録できた。`users.email` には `UNIQUE` 制約があるため、そのような未検証の `User` は事実上 email を予約してしまい、本当の所有者の登録を妨げる可能性があった。さらに、こうしたレコードのライフサイクルも問題だった：登録が未完了の場合、`User` が MySQL に残り、別途クリーンアップのロジックが必要だった。

**なぜ重要か：**

旧来のアプローチは、ソフトオンボーディングとユーザーの基本的な期待との間に矛盾を生んでいた：ユーザーがその email を使う権利が証明される前に、email が使用済みと見なされていた。Cron による TTL クリーンアップという案はブロック期間を制限するものの、根本原因を取り除くものではなかった：一時的な登録は、やはりまず `users` に入ってしまう。

**解決：**

問題は **zero-DB-write pending registration** への移行によって解消された：

1. `POST /register` は MySQL に `User` を作成しない。
2. 登録データ（`email`、`hashed password`、`first_name`、`last_name`、`phone`）は、Redis にキー `registration:{token}` で TTL 付きで一時保存される。
3. resend には、インデックス `registration:email:{email}` → `token` を使用する。
4. 検証 URL は `user_id` ではなく `registration token` に署名する。
5. email が検証されるまで、`users` テーブルに対応するレコードは存在しない。
6. 検証が成功すると、バックエンドは `email_verified_at = now()` を設定した状態で MySQL に `User` を作成する。
7. DB の commit が成功した後、pending registration は Redis から削除される。
8. email が検証されない場合、Redis の TTL により pending registration は自動的に削除される。

**結果：**

未検証の登録は、もはや MySQL 上で email を予約しない。
ユーザーが他人のまたは存在しない email を指定して検証しなかった場合、TTL の経過後に pending registration は自動的に消える。MySQL には、削除、クリーンアップ、匿名化の必要な `User` が残らない。このように、zero-DB-write は、クリーンアップの仕組みで影響を抑えるのではなく、ISSUE-2 の根本原因を解消する。

**関連する決定：**

* **AUTH-15:** Zero-DB-write pending registration — User は verification の後にのみ作成される。
* **AUTH-17:** `verified` の段階的な適用 ＋ guest-first browsing。
* **AUTH-16:** Verification email → verified User の作成後に Welcome email。
* **AUTH-18:** `/me` によるセッション状態。

**Trade-off（トレードオフ）：**

* Redis が登録のクリティカルパスになる。
* Pending registration は、TTL が切れるまで `hashed password` を Redis に保持する。
* Resend には、別途 Redis の email インデックスが必要。
* すでに使用済みの verification link を再度クリックした場合の挙動には、別途決定が必要（`410 Gone` または短命の `verified:{token}` マーカー）。
* 既存 email に対する `422` による User Enumeration のリスクは残り、AUTH-11 に別途記載されている。

**ISSUE-1 との関係：**

Redis 上の pending registration はアカウントではなく、Account Restoration には関与しない。`soft-delete` と `withTrashed()` は、すでに存在する verified な `User` にのみ適用される。

**決定の状況：**

ISSUE-2 は、zero-DB-write registration へのアーキテクチャ移行によってクローズされた。未検証の `User` のための追加のクリーンアップインフラは不要である。
