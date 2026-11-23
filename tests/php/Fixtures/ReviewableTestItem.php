<?php

namespace SilverShop\Reviews\Tests\Fixtures;

use SilverShop\Reviews\Extension\ReviewablePageExtension;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * A reviewable record that is not a page.
 *
 * @property string $Title
 */
class ReviewableTestItem extends DataObject implements TestOnly
{
    private static string $table_name = 'ReviewsTest_ReviewableItem';

    private static array $db = [
        'Title' => 'Varchar',
    ];

    private static array $extensions = [
        ReviewablePageExtension::class,
    ];
}
