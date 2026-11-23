<?php

declare(strict_types=1);

namespace SilverShop\Reviews\Service;

use SilverShop\Reviews\Model\Review;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

/**
 * Who may review a subject, resolved per subject: a reviewable record can override any `Review` setting
 * (`allow_reviews`, `moderation`, `who_can_review`, `require_content`, `allow_photos`, ...) through its
 * class's `review_settings` config (see {@see \SilverShop\Reviews\Extension\ReviewablePageExtension}); every
 * key it leaves out falls back to the global `Review` config.
 */
class ReviewPolicy
{
    public static function setting(DataObject $subject, string $key): mixed
    {
        if ($subject->hasMethod('reviewSetting')) {
            return $subject->reviewSetting($key);
        }

        return Review::config()->get($key);
    }

    public static function canReview(DataObject $subject, ?Member $member): bool
    {
        if (!self::setting($subject, 'allow_reviews')) {
            return false;
        }

        return match (self::setting($subject, 'who_can_review')) {
            'members' => $member !== null,
            // 'verified' needs a way to check a purchase, which only shop subjects (Products) have
            'verified' => $member !== null && self::hasPurchased($subject, $member),
            default => true,
        };
    }

    /**
     * Why the review form is hidden for $member, or '' when they may review.
     */
    public static function gateMessage(DataObject $subject, ?Member $member): string
    {
        // reviews switched off entirely: the surface is hidden, so there is nothing to explain
        if (!self::setting($subject, 'allow_reviews') || self::canReview($subject, $member)) {
            return '';
        }

        if (self::setting($subject, 'who_can_review') === 'verified') {
            return _t(self::class . '.GateVerified', 'Only verified customers can leave a review.');
        }

        return _t(self::class . '.GateMembers', 'Please log in to leave a review.');
    }

    public static function hasPurchased(DataObject $subject, ?Member $member, ?string $email = null): bool
    {
        return $subject->hasMethod('reviewerHasPurchased') && $subject->reviewerHasPurchased($member, $email);
    }
}
