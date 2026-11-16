# アーキテクチャ決定記録 — 横断的事項

> 対象：バックエンド全体のアーキテクチャ（Laravel 12）とコード構成。
> ドメイン固有の決定は各ファイル（`auth-audit-decisions.md`、
> `frontend-audit-decisions.md`）に記載する。
>
> 形式は他のADRファイルと同じ：記録は承認後に変更不可とし、
> 見直しは `Supersedes` で参照する新しいADRとして追加する。
>
> 番号付け `ARCH-N` は、`auth-audit-decisions.md` の AUTH-N と
> 重複しないよう独立させている。

---

### ARCH-1: モジュラーモノリス — ドメインごとのフォルダ ＋ モジュールごとの ServiceProvider

**Status:** Accepted

**背景：**
実装済みなのは auth モジュールのみ（しかも未完成）だが、DBスキーマ
（`schema_final.sql`）にはすでに将来のドメインが定義されている：Auth（`users`）、Catalog
（`categories`、`products`、`product_variants`、`product_images`）、Cart
（`carts`、`cart_items`）、Orders（`orders`、`order_items`、
`order_status_history`）、Payments（`payments`）、Wishlist（`wishlists`）。
Catalog、Cart、Checkout、Payments が加わるとコード量は数倍に増えるため、
移行コストが安い今のうちに構造を決める。「独自のルートとマイグレーションを持つ
プラグイン／モジュール」というモデルは CakePHP の Plugins でお馴染みであり、
Laravel で最も近いネイティブな対応物は、`loadRoutesFrom` /
`loadMigrationsFrom` を使う ServiceProvider である。

**決定：**

1. **ドメインごとのフォルダ：** `app/Modules/<Name>/`、名前空間は `App\Modules\<Name>`
   （`composer.json` の標準的なPSR-4で、追加設定は不要）。
2. **モジュールごとの ServiceProvider：** `app/Modules/<Name>/Providers/<Name>ServiceProvider.php`。
   `boot()` でルート（`loadRoutesFrom`）とマイグレーション
   （`loadMigrationsFrom`）を読み込む。プロバイダは `bootstrap/providers.php` に登録する。

モジュールの構造：

```text
app/Modules/Auth/
├── Actions/
├── DTO/
├── Database/
│   └── Migrations/
├── Http/
│   ├── Controllers/
│   ├── Requests/
│   └── Resources/
├── Listeners/
├── Models/
├── Notifications/
├── Providers/
│   └── AuthServiceProvider.php
├── Routes/
│   └── api.php
└── Services/
```

横断的なインフラ（例：`ApiException`、`ValidationErrorCodeMapper`、
基底クラス）は、どのモジュールにも属さず `app/Shared/` に置く。

**境界のルール：**

- 共通スキーマのFKに基づくEloquentリレーションは許可する（`Order::belongsTo(User::class)`）。
- 他ドメインに対するビジネス操作は、そのモジュールの公開
  Action/Service、DTO、またはイベントを通してのみ行う。隣接モジュールの
  内部クラスを直接使ってはならない。
- DBもスキーマも1つ。モジュールごとの別DB・別スキーマは持たない。

**理由（アーキテクチャ上の利点）：**

- **フレームワークネイティブ：** `loadRoutesFrom` と `loadMigrationsFrom` は
  ServiceProvider の標準メソッドであり、サードパーティのパッケージも
  自作のマジックも不要。
- **自己完結したブロック：** ドメインのルート、マイグレーション、コード、イベントが
  同じ場所にまとまる。フロントエンドの FSD との接点：両側でドメイン単位に分割する。
- **今なら安い：** モジュール1つの移行は5つの移行よりはるかに簡単。
- **切り出しへの道筋：** 境界がすでに引かれているため、必要になれば
  モジュールを別の composer パッケージやサービスに切り出せる。

**Trade-off（トレードオフ）：**

- **マイグレーションの順序：** Laravel は全モジュールのマイグレーションパスを集め、
  ファイルを名前（timestamp）で並べ替える。FK を持つマイグレーションは、参照先の
  テーブルより後の timestamp にする必要がある：`users` → `addresses` → `categories` →
  `products` → `product_variants` → `carts` → `orders` → `payments`。
  循環FK（`users` ↔ `addresses`）は、`schema_final.sql` と同様に、両テーブル作成後の
  別マイグレーションとして残す。
- **イベントディスカバリ：** リスナーは `app/Listeners` に置かれなくなる。
  `bootstrap/app.php` の自動検出パスをモジュール単位のパターンに置き換える必要がある
  （`withEvents(discover: [__DIR__.'/../app/Modules/*/Listeners'])`）。具体的な
  構文は導入時にドキュメントで確認する。
- **ボイラープレート：** モジュールごとに ServiceProvider とフォルダ構造が1セット必要。
- **境界は道具ではなく規律で守る：** PHP は隣接モジュールのクラスの
  インポートを禁止しない。
- **既存 auth の移行：** 名前空間の変更、テストとマイグレーションの移動、
  `User` モデルへの参照の更新（例：`config/auth.php`）。

**意図的に却下したもの：**

- **ローカル composer パッケージ（path repository）：** モジュールごとの独自バージョン管理と
  `composer.json` は、別の利用者やチームがいる場合にのみ見合う。
- **`nwidart/laravel-modules`：** ネイティブで実現できることの上に載る非公式パッケージ。
- **モジュールごとの別DBまたは別スキーマ：** 共通スキーマおよびドメイン間のFKと矛盾する。

**記録時点で未決定の事項：**

- `addresses` をどのモジュールに入れるか（Auth か、独立した Profile/Account か）。
- 自動的な境界チェック（アーキテクチャテスト）が必要か — 境界が
  破られ始めたら再検討する。

**見直しの条件（リファクタリングのトリガー）：**

1. 境界が頻繁に破られ、自動チェックが必要になった場合。
2. 特定のモジュールに別の利用者やチームが現れた場合。その際は
   composer パッケージまたはサービスへ切り出す。
3. モジュール別のマイグレーションが支障をきたし始めた場合（順序の衝突）。その際は
   共通のマイグレーションフォルダに戻す。

---

_Last updated: 2026-11-11_
