<?php

declare(strict_types=1);

namespace SilverShop\Reviews\Form;

use SilverShop\Reviews\Model\Review;
use SilverShop\Reviews\Model\ReviewImage;
use SilverShop\Reviews\Service\ReviewPolicy;
use SilverStripe\Assets\Image;
use SilverStripe\Assets\Upload;
use SilverStripe\Assets\Upload_Validator;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\RequestHandler;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\EmailField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FileField;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\Validation\RequiredFieldsValidator;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Security;

/**
 * A "write a review" form for any reviewable record: a page, a SiteConfig, any DataObject.
 *
 * The reviewed subject is handed in by the code that builds the form, never read from the request, so a
 * visitor can't redirect a review to another record. Build it from a controller method that resolves the
 * subject on every request, e.g. `ReviewForm::create($this, 'ReviewForm', $this->data())`
 * ({@see \SilverShop\Reviews\Extension\ReviewablePageControllerExtension} does that for pages).
 *
 * Gating, moderation, photos and pros/cons follow the subject's own `review_settings` (falling back to the
 * global `Review` config); the honeypot, session throttle and optional silverstripe/spamprotection apply as
 * on the product form. Hooks: `updateReviewForm($form)` and `updateReview($review, $data)`, fired on both the
 * form and its controller.
 */
class ReviewForm extends Form
{
    public const SUBMIT_ACTION = 'doSubmitReview';

    private const HONEYPOT = 'HPWebsite';

    private const PHOTO_PREFIX = 'Photo';

    private const THROTTLE_KEY = 'ReviewLastSubmit';

    protected DataObject $subject;

    public function __construct(RequestHandler $controller, string $name, DataObject $subject)
    {
        $this->subject = $subject;
        $member = Security::getCurrentUser();

        $fields = FieldList::create(
            DropdownField::create(
                'Rating',
                _t(self::class . '.Rating', 'Your rating'),
                array_combine(range(5, 1), range(5, 1))
            )->setEmptyString(_t(self::class . '.ChooseRating', '(choose)')),
            TextField::create('Title', _t(self::class . '.Title', 'Title')),
            TextareaField::create('Content', _t(self::class . '.Content', 'Your review'))->setRows(5)
        );

        if ($this->setting('allow_review_points')) {
            $fields->push(
                TextareaField::create('Pros', _t(self::class . '.Pros', 'What was good? (one per line)'))->setRows(3)
            );
            $fields->push(
                TextareaField::create('Cons', _t(self::class . '.Cons', 'What could be better? (one per line)'))
                    ->setRows(3)
            );
        }

        if ($member) {
            $fields->push(HiddenField::create('AuthorName', '', $member->getName()));
            $fields->push(HiddenField::create('AuthorEmail', '', $member->Email));
        } else {
            $fields->push(TextField::create('AuthorName', _t(self::class . '.AuthorName', 'Your name')));
            $fields->push(
                EmailField::create('AuthorEmail', _t(self::class . '.AuthorEmail', 'Your email'))
                    ->setDescription(_t(self::class . '.EmailPrivate', 'Not published.'))
            );
        }

        if ($this->setting('allow_photos')) {
            for ($i = 1; $i <= $this->maxPhotos(); $i++) {
                $label = $i === 1 ? _t(self::class . '.AddPhoto', 'Add a photo') : '';
                $fields->push(FileField::create(self::PHOTO_PREFIX . $i, $label));
            }
        }

        if (Review::HoneypotEnabled()) {
            $fields->push($this->honeypotField());
        }

        $required = ['Rating'];
        if ($this->setting('require_content')) {
            $required[] = 'Content';
        }
        if (!$member) {
            $required[] = 'AuthorName';
            $required[] = 'AuthorEmail';
        }
        // lets the template show a visible required marker (the validator enforces them)
        foreach ($required as $requiredField) {
            $fields->dataFieldByName($requiredField)?->addExtraClass('is-required');
        }

        parent::__construct(
            $controller,
            $name,
            $fields,
            FieldList::create(FormAction::create(self::SUBMIT_ACTION, _t(self::class . '.Submit', 'Submit review'))),
            RequiredFieldsValidator::create($required)
        );

        Review::applySpamProtection($this);
        $this->extend('updateReviewForm', $this);
        $controller->extend('updateReviewForm', $this);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function doSubmitReview(array $data, Form $form): HTTPResponse
    {
        // Honeypot tripped: pretend success, write nothing.
        if (!empty($data[self::HONEYPOT])) {
            return $this->finish(_t(self::class . '.Thanks', 'Thank you for your review.'), 'good');
        }

        $member = Security::getCurrentUser();
        if (!ReviewPolicy::canReview($this->subject, $member)) {
            return $this->finish(ReviewPolicy::gateMessage($this->subject, $member), 'bad');
        }

        $session = $this->getSession();
        $throttle = (int) Review::config()->get('submit_throttle_seconds');
        $last = (int) $session->get(self::THROTTLE_KEY);
        if ($throttle > 0 && $last && (time() - $last) < $throttle) {
            return $this->finish(
                _t(self::class . '.TooFast', 'Please wait a moment before submitting another review.'),
                'bad'
            );
        }

        $rating = (int) ($data['Rating'] ?? 0);
        if ($rating < 1 || $rating > 5) {
            return $this->finish(_t(self::class . '.BadRating', 'Please choose a rating between 1 and 5.'), 'bad');
        }

        $authorEmail = $member ? (string) $member->Email : trim((string) ($data['AuthorEmail'] ?? ''));
        $moderation = (bool) $this->setting('moderation');

        $review = Review::create();
        $review->Rating = $rating;
        $review->Title = trim((string) ($data['Title'] ?? ''));
        $review->Content = trim((string) ($data['Content'] ?? ''));
        $review->AuthorName = $member ? $member->getName() : trim((string) ($data['AuthorName'] ?? ''));
        $review->AuthorEmail = $authorEmail;
        $review->Approved = !$moderation;
        $review->Verified = ReviewPolicy::hasPurchased($this->subject, $member, $authorEmail ?: null);
        // the subject comes from the server side, whatever the request carries
        $review->SubjectID = (int) $this->subject->ID;
        $review->SubjectClass = $this->subject->ClassName;
        if ($member) {
            $review->MemberID = $member->ID;
        }
        $this->extend('updateReview', $review, $data);
        $this->getController()?->extend('updateReview', $review, $data);
        $review->write();

        if ($this->setting('allow_review_points')) {
            Review::savePointsFromText($review, (string) ($data['Pros'] ?? ''), 'Pro');
            Review::savePointsFromText($review, (string) ($data['Cons'] ?? ''), 'Con');
        }

        $this->saveReviewPhotos($review);

        $session->set(self::THROTTLE_KEY, time());

        return $this->finish(
            $moderation
                ? _t(self::class . '.ThanksModerated', 'Thank you! Your review will appear once it has been approved.')
                : _t(self::class . '.Thanks', 'Thank you for your review.'),
            'good'
        );
    }

    protected function setting(string $key): mixed
    {
        return ReviewPolicy::setting($this->subject, $key);
    }

    protected function maxPhotos(): int
    {
        return max(1, (int) $this->setting('max_photos'));
    }

    protected function finish(string $message, string $type): HTTPResponse
    {
        $this->sessionMessage($message, $type);

        return $this->getController()->redirectBack();
    }

    /**
     * Save uploaded photos as ReviewImages (image types only, size-capped), up to the configured maximum.
     */
    protected function saveReviewPhotos(Review $review): void
    {
        if (!$this->setting('allow_photos')) {
            return;
        }

        for ($i = 1; $i <= $this->maxPhotos(); $i++) {
            $tmp = $_FILES[self::PHOTO_PREFIX . $i] ?? null;
            if (!is_array($tmp) || empty($tmp['tmp_name']) || ($tmp['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                continue;
            }

            $image = Image::create();
            $upload = Upload::create();
            $validator = Upload_Validator::create();
            $validator->setAllowedExtensions(['jpg', 'jpeg', 'png', 'gif', 'webp']);
            $validator->setAllowedMaxFileSize((string) $this->setting('max_photo_size'));
            $upload->setValidator($validator);

            if ($upload->loadIntoFile($tmp, $image, 'review-photos')) {
                $reviewImage = ReviewImage::create();
                $reviewImage->ReviewID = $review->ID;
                $reviewImage->ImageID = $image->ID;
                $reviewImage->Sort = $i;
                $reviewImage->write();
            }
        }
    }

    /**
     * A fully hidden honeypot (label + input): real visitors never see it, bots fill it in.
     */
    protected function honeypotField(): LiteralField
    {
        return LiteralField::create(
            self::HONEYPOT . '_hp',
            '<div style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;" aria-hidden="true">'
            . '<label>Website <input type="text" name="' . self::HONEYPOT . '" autocomplete="off" tabindex="-1"'
            . ' value=""></label></div>'
        );
    }
}
