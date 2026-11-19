<?php

/**
 * silvershop/core is a SOFT dependency. The Review model, company/website reviews (on SiteConfig), the
 * standalone /shop-review form, helpful votes, Q&A, moderation admin, aggregate-rating JSON-LD and the
 * external-provider display adapters all work on any SilverStripe 6 site. Only the shop surfaces need core —
 * product reviews, the verified-purchase Review↔Order link, the order-triggered invitation stack — so they
 * are attached here behind class_exists() guards and are simply never wired when core is absent.
 *
 * This mirrors silvershop-suite/newsletter's guarded _config.php and keeps the module installable (and testable)
 * without silvershop/core, while lighting up every shop feature when it is present.
 */

use SilverShop\Reviews\Admin\ReviewsAdmin;
use SilverShop\Reviews\Extension\ProductReviewControllerExtension;
use SilverShop\Reviews\Extension\ReviewableProductExtension;
use SilverShop\Reviews\Extension\ReviewOrderExtension;
use SilverShop\Reviews\Model\Review;
use SilverShop\Reviews\Model\ReviewInvitation;
use SilverStripe\Core\Config\Config;

// Product review surfaces: the aggregate/verified-buyer extension on the Product, and the write-review +
// Q&A form on its controller.
if (class_exists(\SilverShop\Page\Product::class)) {
    \SilverShop\Page\Product::add_extension(ReviewableProductExtension::class);
}

if (class_exists(\SilverShop\Page\ProductController::class)) {
    \SilverShop\Page\ProductController::add_extension(ProductReviewControllerExtension::class);
}

// Verified reviews link back to the order they came from. The relation lives in an extension so the generic
// Review model is core-free; on an existing shop install it re-adds the identical OrderID column (no
// destructive dev/build), on a core-free site the column never exists.
if (class_exists(\SilverShop\Model\Order::class)) {
    Review::add_extension(ReviewOrderExtension::class);

    // The order-triggered invitation stack is inherently order-bound: surface its records in the moderation
    // admin only when core is present (the 'review' landing route is framework-safe and stays in routes.yml,
    // 404-ing cleanly on a core-free site since no invitations exist).
    Config::modify()->merge(ReviewsAdmin::class, 'managed_models', [
        ReviewInvitation::class,
    ]);
}
