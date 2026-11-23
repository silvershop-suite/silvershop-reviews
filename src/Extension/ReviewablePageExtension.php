<?php

declare(strict_types=1);

namespace SilverShop\Reviews\Extension;

use SilverShop\Reviews\Model\Review;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\LiteralField;
use SilverStripe\ORM\FieldType\DBHTMLText;

/**
 * Makes any page or DataObject reviewable, with no shop required: a treatment, a course, a venue, a service.
 * Adds the `Reviews` relation (through the polymorphic {@see Review} `Subject`), the shared star aggregates,
 * an aggregateRating JSON-LD block and a CMS "Reviews" tab with a moderation grid.
 *
 * Apply it to the specific classes that should be reviewable (not to the base Page: Products already have
 * {@see ReviewableProductExtension}), and add {@see ReviewablePageControllerExtension} to the page's controller
 * for the public form. Per-class settings override the global `Review` config:
 *
 * ```yaml
 * App\Pages\TreatmentPage:
 *   extensions:
 *     - SilverShop\Reviews\Extension\ReviewablePageExtension
 *   review_settings:
 *     moderation: true
 *     who_can_review: members
 *     schema_type: 'Service'   # '' = no JSON-LD (e.g. your own schema layer uses ReviewAggregateRating)
 * ```
 *
 * @extends ReviewableExtension<\SilverStripe\ORM\DataObject>
 */
class ReviewablePageExtension extends ReviewableExtension
{
    private static array $has_many = [
        'Reviews' => Review::class . '.Subject',
    ];

    /**
     * schema.org @type of the reviewed item when the owner sets no `review_settings.schema_type`.
     */
    private static string $default_schema_type = 'Thing';

    /**
     * A review setting for this record: the owner class's `review_settings[$key]` when set, otherwise the
     * global `Review` config.
     */
    public function reviewSetting(string $key): mixed
    {
        $settings = $this->getOwner()->config()->get('review_settings');

        if (is_array($settings) && array_key_exists($key, $settings)) {
            return $settings[$key];
        }

        return Review::config()->get($key);
    }

    public function ReviewsEnabled(): bool
    {
        return (bool) $this->reviewSetting('allow_reviews');
    }

    /**
     * The schema.org AggregateRating node for this record (empty when it has no approved reviews), for sites
     * that assemble their own JSON-LD graph instead of using {@see ReviewsSchemaOrg()}.
     *
     * @return array<string, mixed>
     */
    public function ReviewAggregateRating(): array
    {
        return $this->HasReviews() ? $this->aggregateRatingNode() : [];
    }

    /**
     * A JSON-LD block naming this record with its aggregate rating. Empty when there are no approved reviews
     * (never emit a zero rating) or when the schema type is set to ''.
     */
    public function ReviewsSchemaOrg(): DBHTMLText
    {
        $html = DBHTMLText::create();
        $type = $this->schemaType();

        if ($type === '' || !$this->HasReviews()) {
            return $html;
        }

        $owner = $this->getOwner();
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => $type,
            'name' => (string) $owner->getTitle(),
        ];

        if ($owner->hasMethod('AbsoluteLink')) {
            $schema['url'] = (string) $owner->AbsoluteLink();
        } elseif ($owner->hasMethod('Link')) {
            $schema['url'] = Director::absoluteURL((string) $owner->Link());
        }

        $schema['aggregateRating'] = $this->aggregateRatingNode();

        // JSON_HEX_TAG|JSON_HEX_AMP escape < > & so the content can't break out of the <script> block.
        $json = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
        $html->setValue('<script type="application/ld+json">' . $json . '</script>');

        return $html;
    }

    public function updateCMSFields(FieldList $fields): void
    {
        if (!$this->getOwner()->isInDB()) {
            $fields->addFieldToTab('Root.Reviews', LiteralField::create('ReviewsSaveFirst', $this->cmsNotice(_t(
                self::class . '.SaveFirst',
                'Save this record first to manage its reviews.'
            ))));

            return;
        }

        $summary = sprintf(
            '<p class="message" style="margin-top:1.5em">%s</p>',
            _t(
                self::class . '.CMSSummary',
                'Average rating: {avg} / 5 from {count} approved review(s).',
                ['avg' => $this->AverageRating(), 'count' => $this->RatingCount()]
            )
        );

        $fields->addFieldsToTab('Root.Reviews', [
            $this->buildReviewsGrid('Reviews', _t(self::class . '.Reviews', 'Reviews')),
            LiteralField::create('ReviewsSummary', $summary),
            LiteralField::create('ReviewsInfo', $this->cmsNotice(_t(
                self::class . '.ReviewsInfo',
                'Approved reviews appear on the site and count towards the rating. Add a review here, or let '
                . 'visitors submit their own, then tick "Approved" to publish it.'
            ))),
        ]);
    }

    protected function schemaType(): string
    {
        $settings = $this->getOwner()->config()->get('review_settings');

        if (is_array($settings) && array_key_exists('schema_type', $settings)) {
            return trim((string) $settings['schema_type']);
        }

        return (string) Config::inst()->get(static::class, 'default_schema_type');
    }

    private function cmsNotice(string $text): string
    {
        return '<p class="message notice" style="margin-top:1.5em;display:flex;align-items:flex-start;gap:.5em">'
            . '<span class="font-icon-info-circled" aria-hidden="true"></span><span>' . $text . '</span></p>';
    }
}
