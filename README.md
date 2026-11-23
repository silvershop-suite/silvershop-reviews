# silvershop-suite/reviews

Product and shop reviews for [SilverShop](https://github.com/silvershop/silvershop-core) (SilverStripe CMS 6):
star ratings, moderation, verified purchases, review-invitation emails, photo reviews, plus/minus points
(pros & cons), helpful votes, a product Q&A, and optional bridges to external review providers (Trustpilot,
Kiyoh / Klantenvertellen, The Feedback Company).

## Requirements

- `silverstripe/framework` ^6 / PHP 8.3+
- `silvershop/core` ^6 is optional: without it the module still does company/website reviews (on `SiteConfig`)
  and reviews on any page or record (see below). Product reviews, verified purchases and invitation emails need it.

## Installation

```bash
composer require silvershop-suite/reviews
```

Then run a dev/build (`/dev/build?flush=1`). The extensions are applied automatically to `Product`,
`ProductController` and `SiteConfig`.

### Show reviews on the product page

Add the reviews block to your product template (e.g. `themes/<theme>/templates/SilverShop/Page/Layout/Product.ss`),
inside the product scope:

```ss
<% include SilverShop\Reviews\ProductReviews %>
```

This renders the rating summary, review list (with photos + helpful votes), the "write a review" form, and the
Q&A section. The template is self-styled; override it by copying it into your theme.

For the common pattern of a compact rating snippet just below the product title (stars + average + review count,
linking down to the reviews), add this under your `<h1>` — it renders nothing until the product has a review:

```ss
<% include SilverShop\Reviews\ProductRatingSummary %>
```

Optional drop-ins:

```ss
<%-- a compact store-rating badge for the footer/homepage --%>
<% include SilverShop\Reviews\ShopRating %>

<%-- Organization aggregate-rating JSON-LD, e.g. in the site <head> --%>
<% include SilverShop\Reviews\ShopRatingSchemaOrg %>
```

Product-level `aggregateRating` + review JSON-LD is emitted automatically by the product reviews include.

## Reviews on any page or record (no shop needed)

Anything can be reviewed: a treatment, a course, a venue, a service page. This works on a plain SilverStripe
site without `silvershop/core`. Make the page class reviewable and give its controller the form:

```yaml
App\Pages\TreatmentPage:
  extensions:
    - SilverShop\Reviews\Extension\ReviewablePageExtension

App\Pages\TreatmentPageController:
  extensions:
    - SilverShop\Reviews\Extension\ReviewablePageControllerExtension
```

Then add the section to the page template:

```ss
<% include SilverShop\Reviews\Reviews %>
```

That renders the rating summary, the per-star breakdown, the approved reviews (pros/cons, photos and helpful
votes when enabled) with pagination, an aggregateRating JSON-LD block and the "write a review" form. The page gets
a **Reviews** tab in the CMS with the moderation grid. Apply the extension to the classes that should be
reviewable, not to the base `Page` (products already have their own).

The reviewed record is always the page being viewed: the form takes its subject from the controller, never from
the request, so a review can't be posted against another record.

**Per-class settings.** `review_settings` on the reviewable class overrides any `Review` setting for that class
only; keys you leave out fall back to the global `Review` config:

```yaml
App\Pages\TreatmentPage:
  review_settings:
    allow_reviews: true
    moderation: true
    who_can_review: anyone      # anyone | members ('verified' needs a shop purchase check)
    require_content: true
    allow_photos: false
    allow_review_points: false
    schema_type: 'Service'      # JSON-LD @type, default 'Thing'; '' = emit no JSON-LD
```

**JSON-LD.** `$ReviewsSchemaOrg` names the record (title + URL) with its `aggregateRating`. If your site builds its
own schema graph, set `schema_type: ''` and use `$ReviewAggregateRating` (the `AggregateRating` node as an array) in
that graph instead. Note that Google only shows review stars for
[certain types](https://developers.google.com/search/docs/appearance/structured-data/review-snippet), and not for
"self-serving" reviews of an `Organization` or `LocalBusiness` on its own site; the markup is still valid.

**A record that isn't a page.** Add `ReviewablePageExtension` to the DataObject, and build the form in the
controller that shows it, with that record as the subject:

```php
use SilverShop\Reviews\Form\ReviewForm;

private static array $allowed_actions = ['ReviewForm'];

public function ReviewForm(): ReviewForm
{
    return ReviewForm::create($this, 'ReviewForm', $this->currentRecord());
}
```

Use `SilverShop\Reviews\Service\ReviewPolicy::canReview($record, $member)` and `::gateMessage()` for the
"may this visitor review" checks.

**Company reviews.** Reviews of the organisation itself already work through `SiteConfig` (the `shop-review` page,
`ShopRating` badge and `ShopRatingSchemaOrg`). Set `SilverShop\Reviews\Extension\ShopReviewExtension.schema_type`
to `LocalBusiness` for a local business.

## Routes

| Path | Purpose |
|---|---|
| `review/{token}` | Tokenised review-invitation landing (leave reviews for a purchased order) |
| `review/unsubscribe/{token}` | Opt the order out of invitations |
| `shop-review` | Public "review our shop" page |
| `review-vote/{reviewID}/{up\|down}` | Record a helpful vote |

## Moderation (CMS)

- **Reviews** admin (left menu) — moderate reviews, questions, and review invitations.
- Each review has a **Photos** tab; each question has an **Answers** tab (mark answers `IsStaff` for a "Shop" badge).
- **Settings → Shop reviews** — the store's own reviews + aggregate.

Reviews/questions are hidden until approved (see `moderation` below).

## Configuration

All options are set via YAML. Example `app/_config/reviews.yml`:

```yaml
SilverShop\Reviews\Model\Review:
  allow_reviews: true        # master switch for the whole reviews section (list + form + votes)
  moderation: true           # reviews hidden until approved
  who_can_review: 'anyone'   # 'anyone' | 'members' | 'verified' (must have purchased the product)
  require_content: false     # false = a star rating alone can be submitted (text optional); true = review text required
  collapse_write_form: true  # hide the review form behind a "Write a review" button (JS; no-JS shows the form)
  allow_review_points: true  # let reviewers add plus/minus points (pros/cons), one per line
  max_points: 5              # max plus points, and separately max minus points, kept per review
  submit_throttle_seconds: 30
  allow_votes: true          # helpful 👍/👎 votes
  allow_photos: false        # photo uploads on reviews — OFF by default (opt-in); set true to enable
  max_photos: 3
  max_photo_size: '2m'       # max size per uploaded photo
  reviews_per_page: 10       # reviews per page on the product page (0 = show all, no pagination)

SilverShop\Reviews\Model\ProductQuestion:
  allow_qna: true            # master switch for the whole Q&A section
  moderation: true           # questions hidden until approved
  who_can_ask: 'anyone'      # 'anyone' | 'members'
```

### Review-invitation emails

A cron-run task sends invitations (and one reminder) for eligible orders.

```yaml
SilverShop\Reviews\Service\ReviewInvitationService:
  trigger_statuses: [Sent, Complete]   # order statuses that make an order eligible
  delay_days: 14                       # wait this long after dispatch / paid / placed
  reminder_enabled: true
  reminder_delay_days: 7               # one reminder if still unanswered
  max_per_run: 50                      # safety cap per run
  from_email: ''                       # defaults to store-profile ContactEmail, then the framework admin email
```

Schedule the task daily (dry-run without `--run`):

```bash
# CLI
vendor/bin/sake tasks:SilverShop-Reviews-Task-SendReviewInvitationsTask --run --reminders
# or in a browser (dev): /dev/tasks/SilverShop-Reviews-Task-SendReviewInvitationsTask?run=1&reminders=1
# preview one order's email without sending: ...?preview=<orderID>
```

The sender identity (name + logo) comes from [`silvershop-suite/store-profile`](https://github.com/silvershop-suite/silvershop-store-profile)
when installed. Make sure a mail transport (`MAILER_DSN`) is configured for real delivery.

### External review providers (optional)

Delegate invitations and/or show an external aggregate rating. Pick one `active` provider and set its credentials.
Each adapter is inert until configured; a provider that handles invitations replaces the native invitation email.

```yaml
SilverShop\Reviews\Provider\ReviewProviderRegistry:
  active: 'trustpilot'       # '' = native reviews only; or 'kiyoh' / 'feedbackcompany'

SilverShop\Reviews\Provider\TrustpilotProvider:
  api_key: '...'
  business_unit_id: '...'
  invitations_token: '...'   # optional; enables invitation hand-off

SilverShop\Reviews\Provider\KiyohProvider:
  api_key: '...'
  location_id: '...'

SilverShop\Reviews\Provider\FeedbackCompanyProvider:
  client_id: '...'
  client_secret: '...'
  shop_id: '...'
```

Fetched ratings are cached (`SilverShop\Reviews\Provider\AbstractReviewProvider.cache_ttl`, default 3600s).

> Provider endpoints/fields follow each vendor's public docs and should be verified against their current API with
> a real account before production. All provider calls fail soft (never break page rendering).

## Spam protection

The public forms ship with a hidden **honeypot** + a per-session submission **throttle**
(`submit_throttle_seconds`). Install [`silverstripe/spamprotection`](https://github.com/silverstripe/silverstripe-spamprotection)
and configure a protector (reCAPTCHA, hCaptcha, …) and it is **enabled automatically** on every form. Turn the
built-in honeypot off if you prefer to rely solely on the protector:

```yaml
SilverShop\Reviews\Model\Review:
  use_honeypot: false
```

## Extending

The module fires extension hooks at the logical points, so you can add fields or adjust records without
overriding templates/controllers. Apply an `Extension` to the relevant class:

| Hook | Fired on | Signature |
|---|---|---|
| `updateReviewForm` | `SilverShop\Page\ProductController` | `($form)` |
| `updateReview` | `SilverShop\Page\ProductController` | `($review, $data)` — before write |
| `updateQuestionForm` | `SilverShop\Page\ProductController` | `($form)` |
| `updateQuestion` | `SilverShop\Page\ProductController` | `($question, $data)` — before write |
| `updateReviewForm` | `ReviewForm` and its controller (any reviewable page) | `($form)` |
| `updateReview` | `ReviewForm` and its controller (any reviewable page) | `($review, $data)` — before write |
| `updateShopReviewForm` | `ShopReviewController` | `($form)` |
| `updateShopReview` | `ShopReviewController` | `($review, $data)` — before write |
| `updateReviewInvitationForm` | `ReviewInvitationController` | `($form)` |
| `updateInvitationReview` | `ReviewInvitationController` | `($review, $order)` — before write |
| `updateInvitationEmailData` | `ReviewInvitationService` | `($data, $invitation, $isReminder)` |

The models (`Review`, `ProductQuestion`, `ProductAnswer`, …) also support the standard `updateCMSFields` hook and
any `DataObject` extension (e.g. `onBeforeWrite`).

Example:

```php
class MyReviewExtension extends SilverStripe\Core\Extension
{
    public function updateReviewForm($form)
    {
        $form->Fields()->push(SilverStripe\Forms\CheckboxField::create('AgreeTerms', 'I agree to the terms'));
    }
    public function updateReview($review, $data)
    {
        // ... adjust $review before it is written ...
    }
}
```

```yaml
SilverShop\Page\ProductController:
  extensions:
    - MyReviewExtension
```

### Templates

Every include is overridable by theme precedence (copy the path into your theme). Notable ones:

- `SilverShop\Reviews\ProductReviews` — the whole product-page reviews section.
- `SilverShop\Reviews\Reviews` — the reviews section for any reviewable page.
- `SilverShop\Reviews\ReviewFormToggle` — *only* the JS that collapses the review form behind the "Write a
  review" button. Override to change it, leave an empty file to disable, or set `Review.collapse_write_form = false`.
- `SilverShop\Reviews\ReviewRatingStars` — *only* the JS that turns the rating `<select>` into an interactive
  star picker (progressive enhancement; without JS the dropdown is used). Override or empty-file to change/disable.

CSS note: the module's inline styles size fonts with `em` (relative to the theme's body text), not `rem`, so they
render correctly whatever root font-size the theme uses (e.g. a `html { font-size: 10px }` / 62.5% theme).

## Testing

```bash
composer install
composer lint    # phpcs (PSR-12)
composer stan    # phpstan level 5
composer test    # phpunit
```

CI runs all three on PHP 8.3 and 8.4.

## Translations

All front-end and CMS strings go through `_t()` / `<%t %>`. Ships with **en, nl, de, fr, es, it** (`lang/*.yml`);
override or add locales the usual Silverstripe way.

## License

BSD-3-Clause

## Credits

Built and maintained by **Remy Vaartjes** (<remy@xd.nl>) — part of the **SilverShop-Suite**.
