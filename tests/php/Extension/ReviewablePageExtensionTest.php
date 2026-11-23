<?php

namespace SilverShop\Reviews\Tests\Extension;

use SilverShop\Reviews\Model\Review;
use SilverShop\Reviews\Service\ReviewPolicy;
use SilverShop\Reviews\Tests\Fixtures\ReviewableTestItem;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Security\Member;

/**
 * Any record can carry reviews: aggregates, per-class settings, JSON-LD and the CMS tab, without a shop.
 */
class ReviewablePageExtensionTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected static $extra_dataobjects = [
        ReviewableTestItem::class,
    ];

    private function item(): ReviewableTestItem
    {
        $item = ReviewableTestItem::create(['Title' => 'Carpal tunnel release']);
        $item->write();

        return $item;
    }

    private function review(ReviewableTestItem $item, int $rating, bool $approved = true): void
    {
        Review::create([
            'Rating' => $rating,
            'Approved' => $approved,
            'SubjectID' => $item->ID,
            'SubjectClass' => $item->ClassName,
        ])->write();
    }

    public function testAggregatesCountOnlyApprovedReviewsOfThisRecord(): void
    {
        $item = $this->item();
        $other = $this->item();
        $this->review($item, 5);
        $this->review($item, 4);
        $this->review($item, 1, false);
        $this->review($other, 1);

        $this->assertTrue($item->HasReviews());
        $this->assertSame(2, $item->RatingCount());
        $this->assertEqualsWithDelta(4.5, $item->AverageRating(), 0.01);
        $this->assertSame(1, (int) $item->RatingBreakdown()->filter('Stars', 5)->first()->Count);
    }

    public function testClassSettingsOverrideTheGlobalReviewConfig(): void
    {
        $item = $this->item();
        Config::modify()->set(Review::class, 'moderation', true);
        Config::modify()->set(Review::class, 'who_can_review', 'anyone');

        $this->assertTrue($item->reviewSetting('moderation'), 'falls back to Review config');

        Config::modify()->set(ReviewableTestItem::class, 'review_settings', [
            'moderation' => false,
            'who_can_review' => 'members',
        ]);

        $this->assertFalse($item->reviewSetting('moderation'));
        $this->assertSame('members', $item->reviewSetting('who_can_review'));
        $this->assertSame(10, (int) $item->reviewSetting('reviews_per_page'), 'unset keys still fall back');
    }

    public function testPolicyGatesFollowTheSubject(): void
    {
        $item = $this->item();
        $member = Member::create(['Email' => 'reviewer@example.com']);
        $member->write();

        $this->assertTrue(ReviewPolicy::canReview($item, null));

        Config::modify()->set(ReviewableTestItem::class, 'review_settings', ['who_can_review' => 'members']);
        $this->assertFalse(ReviewPolicy::canReview($item, null));
        $this->assertSame('Please log in to leave a review.', ReviewPolicy::gateMessage($item, null));
        $this->assertTrue(ReviewPolicy::canReview($item, $member));

        // 'verified' needs a purchase check, which a non-shop record doesn't have
        Config::modify()->set(ReviewableTestItem::class, 'review_settings', ['who_can_review' => 'verified']);
        $this->assertFalse(ReviewPolicy::canReview($item, $member));
        $this->assertSame('Only verified customers can leave a review.', ReviewPolicy::gateMessage($item, $member));

        Config::modify()->set(ReviewableTestItem::class, 'review_settings', ['allow_reviews' => false]);
        $this->assertFalse($item->ReviewsEnabled());
        $this->assertFalse(ReviewPolicy::canReview($item, $member));
        $this->assertSame('', ReviewPolicy::gateMessage($item, $member), 'nothing to explain when switched off');
    }

    public function testJsonLdUsesTheConfiguredTypeAndCanBeSwitchedOff(): void
    {
        $item = $this->item();
        $this->assertSame('', (string) $item->ReviewsSchemaOrg()->getValue(), 'no rating, no JSON-LD');
        $this->assertSame([], $item->ReviewAggregateRating());

        $this->review($item, 5);
        $this->review($item, 3);

        $json = $this->jsonLd((string) $item->ReviewsSchemaOrg()->getValue());
        $this->assertSame('Thing', $json['@type']);
        $this->assertSame('Carpal tunnel release', $json['name']);
        $this->assertSame('4', $json['aggregateRating']['ratingValue']);
        $this->assertSame(2, $json['aggregateRating']['reviewCount']);
        $this->assertSame($json['aggregateRating'], $item->ReviewAggregateRating());

        Config::modify()->set(ReviewableTestItem::class, 'review_settings', ['schema_type' => 'Service']);
        $this->assertSame('Service', $this->jsonLd((string) $item->ReviewsSchemaOrg()->getValue())['@type']);

        Config::modify()->set(ReviewableTestItem::class, 'review_settings', ['schema_type' => '']);
        $this->assertSame('', (string) $item->ReviewsSchemaOrg()->getValue());
        $this->assertNotEmpty($item->ReviewAggregateRating(), 'the node stays available for a custom graph');
    }

    public function testJsonLdEscapesMarkupInTheName(): void
    {
        $item = ReviewableTestItem::create(['Title' => '</script><b>x</b>']);
        $item->write();
        $this->review($item, 5);

        $html = (string) $item->ReviewsSchemaOrg()->getValue();
        $this->assertStringNotContainsString('</script><b>', $html);
        $this->assertSame('</script><b>x</b>', $this->jsonLd($html)['name']);
    }

    public function testCmsTabHasTheModerationGridOnceSaved(): void
    {
        $unsaved = ReviewableTestItem::create();
        $this->assertNotNull($unsaved->getCMSFields()->fieldByName('Root.Reviews.ReviewsSaveFirst'));

        $fields = $this->item()->getCMSFields();
        $grid = $fields->fieldByName('Root.Reviews.Reviews');
        $this->assertInstanceOf(GridField::class, $grid);
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonLd(string $html): array
    {
        $this->assertSame(1, preg_match('#^<script type="application/ld\+json">(.+)</script>$#s', $html, $match));

        return json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);
    }
}
