<?php

namespace SilverShop\Reviews\Tests\Form;

use SilverShop\Reviews\Model\Review;
use SilverShop\Reviews\Tests\Fixtures\ReviewableTestPage;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * The generic review form on a plain (non-shop) page: renders through the module's include, writes reviews
 * against the page being viewed, and honours the page class's own review settings.
 */
class ReviewFormTest extends FunctionalTest
{
    protected $usesDatabase = true;

    protected static $extra_dataobjects = [
        ReviewableTestPage::class,
    ];

    private ReviewableTestPage $page;

    protected function setUp(): void
    {
        parent::setUp();

        Config::modify()->set(Review::class, 'submit_throttle_seconds', 0);
        Config::modify()->set(Review::class, 'collapse_write_form', false);

        $this->page = ReviewableTestPage::create(['Title' => 'Carpal tunnel', 'URLSegment' => 'carpal-tunnel']);
        $this->page->write();
        $this->page->publishRecursive();
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function submit(array $extra = []): void
    {
        $this->post($this->page->Link('ReviewForm'), array_merge([
            'Rating' => 4,
            'Title' => 'Helped a lot',
            'Content' => 'Quick recovery.',
            'AuthorName' => 'Patient',
            'AuthorEmail' => 'patient@example.com',
            'action_doSubmitReview' => 'Submit review',
        ], $extra));
    }

    public function testThePageRendersTheReviewsSectionAndForm(): void
    {
        $body = (string) $this->get($this->page->Link())->getBody();

        $this->assertStringContainsString('id="reviews"', $body);
        $this->assertStringContainsString('Form_ReviewForm', $body);
        $this->assertStringContainsString('name="HPWebsite"', $body);
        $this->assertStringContainsString('No reviews yet. Be the first to leave one.', $body);
    }

    public function testASubmissionIsStoredAgainstTheViewedPageWhateverTheRequestSays(): void
    {
        $siteConfig = SiteConfig::current_site_config();

        $this->submit(['SubjectID' => $siteConfig->ID, 'SubjectClass' => SiteConfig::class]);

        $review = Review::get()->first();
        $this->assertNotNull($review, 'the review was written');
        $this->assertSame($this->page->ID, (int) $review->SubjectID);
        $this->assertSame(ReviewableTestPage::class, $review->SubjectClass);
        $this->assertSame(4, (int) $review->Rating);
        $this->assertFalse((bool) $review->Approved, 'moderated by default');
        $this->assertFalse((bool) $review->Verified, 'no purchase check on a non-shop page');
    }

    public function testThePageClassCanSwitchModerationOff(): void
    {
        Config::modify()->set(ReviewableTestPage::class, 'review_settings', ['moderation' => false]);

        $this->submit();
        $this->assertTrue((bool) Review::get()->first()->Approved);

        $body = (string) $this->get($this->page->Link())->getBody();
        $this->assertStringContainsString('Helped a lot', $body, 'the published review is listed');
        $this->assertStringContainsString('application/ld+json', $body);
    }

    public function testMembersOnlyHidesTheFormFromVisitors(): void
    {
        Config::modify()->set(ReviewableTestPage::class, 'review_settings', ['who_can_review' => 'members']);

        $body = (string) $this->get($this->page->Link())->getBody();
        $this->assertStringNotContainsString('Form_ReviewForm', $body);
        $this->assertStringContainsString('Please log in to leave a review.', $body);

        $this->submit();
        $this->assertSame(0, Review::get()->count(), 'a direct post is refused too');

        $this->logInWithPermission('SOME_MEMBER');
        $this->assertStringContainsString('Form_ReviewForm', (string) $this->get($this->page->Link())->getBody());
    }

    public function testHoneypotAndInvalidRatingsWriteNothing(): void
    {
        $this->submit(['HPWebsite' => 'http://spam.example']);
        $this->submit(['Rating' => 9]);

        $this->assertSame(0, Review::get()->count());
    }

    public function testRepeatSubmissionsAreThrottled(): void
    {
        Config::modify()->set(Review::class, 'submit_throttle_seconds', 60);

        $this->submit();
        $this->submit();

        $this->assertSame(1, Review::get()->count());
    }

    public function testReviewsSwitchedOffHidesTheSection(): void
    {
        Config::modify()->set(ReviewableTestPage::class, 'review_settings', ['allow_reviews' => false]);

        $body = (string) $this->get($this->page->Link())->getBody();
        $this->assertStringNotContainsString('id="reviews"', $body);
    }
}
