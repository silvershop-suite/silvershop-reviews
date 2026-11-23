<?php

namespace SilverShop\Reviews\Tests\Fixtures;

use SilverShop\Reviews\Extension\ReviewablePageExtension;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\TestOnly;

/**
 * A plain (non-shop) page made reviewable, as a site would do for e.g. a treatment or course page.
 */
class ReviewableTestPage extends SiteTree implements TestOnly
{
    private static string $table_name = 'ReviewsTest_ReviewablePage';

    private static array $extensions = [
        ReviewablePageExtension::class,
    ];
}
