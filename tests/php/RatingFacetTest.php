<?php

declare(strict_types=1);

namespace SilverShop\Reviews\Tests;

use SilverShop\Reviews\Extension\ReviewableProductExtension;
use SilverShop\Reviews\Model\Review;
use SilverShop\Reviews\Search\RatingFacet;
use SilverShop\Search\Query\SearchQuery;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

class RatingFacetTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected static $extra_dataobjects = [
        RatingFacetTestProduct::class,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        // The rating facet plugs into silvershop-suite/search (which itself needs silvershop/core); skip when absent.
        if (!interface_exists('SilverShop\\Search\\Facet\\SearchFacet')) {
            $this->markTestSkipped('silvershop-suite/search is not installed');
        }
    }

    private function seed(): void
    {
        $five = RatingFacetTestProduct::create(['Title' => 'Five']);
        $five->write();
        $three = RatingFacetTestProduct::create(['Title' => 'Three']);
        $three->write();
        $unrated = RatingFacetTestProduct::create(['Title' => 'Unrated']);
        $unrated->write();

        // "Five": two approved 5s → avg 5.
        $this->review($five, 5, true);
        $this->review($five, 5, true);
        // "Three": one approved 3, one unapproved 1 → approved avg 3.
        $this->review($three, 3, true);
        $this->review($three, 1, false);
        // "Unrated": one unapproved 4 → no approved reviews.
        $this->review($unrated, 4, false);
    }

    private function review(DataObject $subject, int $rating, bool $approved): void
    {
        $review = Review::create();
        $review->Rating = $rating;
        $review->Approved = $approved;
        $review->SubjectClass = $subject->ClassName;
        $review->SubjectID = $subject->ID;
        $review->write();
    }

    public function testApplyMatchesMeanOfApprovedReviews(): void
    {
        $this->seed();
        $facet = new RatingFacet();

        $this->assertSame(1, $facet->apply(RatingFacetTestProduct::get(), ['4'])->count(), 'only the 5-avg product is 4+');
        $this->assertSame(2, $facet->apply(RatingFacetTestProduct::get(), ['3'])->count(), '5-avg and 3-avg are 3+');
        $this->assertSame(2, $facet->apply(RatingFacetTestProduct::get(), ['4', '3'])->count(), 'OR → lowest wins');
    }

    public function testApplyWithNoSelectionIsUnfiltered(): void
    {
        $this->seed();

        $this->assertSame(3, (new RatingFacet())->apply(RatingFacetTestProduct::get(), [])->count());
    }

    public function testOptionsCarryCountsAndActiveFlag(): void
    {
        $this->seed();

        $options = (new RatingFacet())->getOptions(
            RatingFacetTestProduct::get(),
            new SearchQuery(filters: ['rating' => ['4']])
        );
        $byValue = [];
        foreach ($options as $option) {
            $byValue[$option['value']] = $option;
        }

        $this->assertSame(1, $byValue['4']['count'], '4+ → one product');
        $this->assertTrue($byValue['4']['active']);
        $this->assertSame(2, $byValue['3']['count'], '3+ → two products');
        $this->assertFalse($byValue['3']['active']);
    }
}

/**
 * A minimal writable, product-reviewable subject — stands in for a Product (which needs a page tree the module's
 * isolated test run does not have).
 */
class RatingFacetTestProduct extends DataObject implements TestOnly
{
    private static $table_name = 'Reviews_RatingFacetTestProduct';

    private static $db = [
        'Title' => 'Varchar',
    ];

    private static $extensions = [
        ReviewableProductExtension::class,
    ];
}
