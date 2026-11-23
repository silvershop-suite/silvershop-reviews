<?php

declare(strict_types=1);

namespace SilverShop\Reviews\Extension;

use SilverShop\Reviews\Form\ReviewForm;
use SilverShop\Reviews\Model\Review;
use SilverShop\Reviews\Service\ReviewPolicy;
use SilverStripe\Core\Extension;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Security;

/**
 * The public review form for a page whose record carries {@see ReviewablePageExtension}. Add it to that
 * page's controller class; the reviewed subject is always the page being viewed (`data()`), so it can't be
 * swapped through the request. Pair it with `<% include SilverShop\Reviews\Reviews %>` in the page template.
 *
 * @extends Extension<\SilverStripe\CMS\Controllers\ContentController>
 */
class ReviewablePageControllerExtension extends Extension
{
    private static array $allowed_actions = [
        'ReviewForm',
    ];

    public function ReviewForm(): ?ReviewForm
    {
        $subject = $this->subject();

        if (!$subject || !ReviewPolicy::canReview($subject, Security::getCurrentUser())) {
            return null;
        }

        return ReviewForm::create($this->getOwner(), 'ReviewForm', $subject);
    }

    public function CanReview(): bool
    {
        $subject = $this->subject();

        return $subject !== null && ReviewPolicy::canReview($subject, Security::getCurrentUser());
    }

    public function ReviewGateMessage(): string
    {
        $subject = $this->subject();

        return $subject ? ReviewPolicy::gateMessage($subject, Security::getCurrentUser()) : '';
    }

    /**
     * Whether the form starts collapsed behind a "Write a review" button (progressive enhancement).
     */
    public function CollapseReviewForm(): bool
    {
        return (bool) Review::config()->get('collapse_write_form');
    }

    /**
     * The page record, when it is reviewable.
     */
    protected function subject(): ?DataObject
    {
        $record = $this->getOwner()->data();

        if (!$record instanceof DataObject || !$record->isInDB()) {
            return null;
        }

        return $record->hasExtension(ReviewablePageExtension::class) ? $record : null;
    }
}
