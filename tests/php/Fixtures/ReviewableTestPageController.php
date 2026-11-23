<?php

namespace SilverShop\Reviews\Tests\Fixtures;

use SilverShop\Reviews\Extension\ReviewablePageControllerExtension;
use SilverStripe\CMS\Controllers\ContentController;
use SilverStripe\Dev\TestOnly;

/**
 * Renders only the module's generic reviews include, so the test needs no theme.
 *
 * @extends ContentController<ReviewableTestPage>
 */
class ReviewableTestPageController extends ContentController implements TestOnly
{
    private static array $extensions = [
        ReviewablePageControllerExtension::class,
    ];

    public function index()
    {
        return $this->renderWith('SilverShop\\Reviews\\Includes\\Reviews');
    }
}
