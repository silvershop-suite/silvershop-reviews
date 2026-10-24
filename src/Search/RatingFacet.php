<?php

declare(strict_types=1);

namespace SilverShop\Reviews\Search;

use SilverShop\Search\Facet\SearchFacet;
use SilverShop\Search\Query\SearchQuery;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\Queries\SQLSelect;

/**
 * "{n}★ and up" rating facet for silvershop/search. Ships in reviews (not the search core) so reviews owns its
 * filtering, and registers into the search FacetRegistry only when silvershop/search is installed (see
 * _config/search.yml); reviews stays standalone otherwise (search is a suggest, not a requirement).
 *
 * Products are matched by the mean of their APPROVED reviews, via a grouped aggregate over the (unversioned, so
 * always-live) review table. The matched subject class follows the data class of the list being searched. For very
 * large review volumes a denormalised average column would avoid the per-render aggregate — a planned optimisation.
 *
 * silvershop/search is a suggest, not a requirement. The class is declared only when the SearchFacet interface is
 * present, so a reviews install without search (e.g. a core-free site) boots cleanly — otherwise the config class
 * manifest would try to reflect a class implementing a missing interface and fatal.
 */
if (interface_exists(SearchFacet::class)) {

class RatingFacet implements SearchFacet
{
    use Configurable;

    /**
     * Rating thresholds offered, high to low.
     *
     * @var array<int, int>
     */
    private static array $thresholds = [4, 3, 2, 1];

    public function getName(): string
    {
        return 'rating';
    }

    public function getTitle(): string
    {
        return _t(self::class . '.TITLE', 'Rating');
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function apply(DataList $products, array $selected): DataList
    {
        $thresholds = $this->selectedThresholds($selected);
        if (!$thresholds) {
            return $products;
        }

        // Several selected thresholds are OR-ed, so the lowest wins ("4+ or 3+" == "3+").
        $ids = $this->productIdsWithMinRating($products->dataClass(), (float) min($thresholds));

        return $products->filter('ID', $ids ?: [-1]);
    }

    public function getOptions(DataList $context, SearchQuery $query): array
    {
        $selected = array_map('strval', $query->selected($this->getName()));
        $contextIds = array_map('intval', $context->column('ID'));
        $subjectClass = $context->dataClass();
        $options = [];

        // One aggregate query for the whole subject class, then count each threshold against the context in PHP —
        // instead of a GROUP BY … HAVING query per threshold.
        $averageByProduct = $contextIds ? $this->averageRatingByProduct($subjectClass) : [];

        foreach ((array) self::config()->get('thresholds') as $threshold) {
            $threshold = (int) $threshold;
            $count = 0;
            foreach ($contextIds as $id) {
                if (isset($averageByProduct[$id]) && $averageByProduct[$id] >= $threshold) {
                    $count++;
                }
            }
            $value = (string) $threshold;
            $active = in_array($value, $selected, true);
            if ($count === 0 && !$active) {
                continue;
            }
            $options[] = [
                'value' => $value,
                'label' => $this->label($threshold),
                'count' => $count,
                'active' => $active,
            ];
        }

        return $options;
    }

    /**
     * @param array<int|string> $selected
     * @return array<int, int>
     */
    private function selectedThresholds(array $selected): array
    {
        $thresholds = [];
        foreach ($selected as $value) {
            if (is_numeric($value)) {
                $thresholds[] = (int) $value;
            }
        }

        return $thresholds;
    }

    /**
     * IDs of $subjectClass records whose approved reviews have a mean rating >= $min.
     *
     * @param class-string $subjectClass
     * @return array<int, int>
     */
    /**
     * Average approved rating per subject (product) across the subject class, as ONE GROUP BY query — the batched
     * source for {@see getOptions()}'s per-threshold counts (which otherwise ran a GROUP BY … HAVING per threshold).
     *
     * @return array<int, float> productID => average rating
     */
    private function averageRatingByProduct(string $subjectClass): array
    {
        $classes = array_values(ClassInfo::subclassesFor($subjectClass));
        $placeholders = implode(',', array_fill(0, count($classes), '?'));

        $query = SQLSelect::create(['"SubjectID"', 'AVG("Rating") AS "AvgRating"'], '"SilverShop_Review"');
        $query->addWhere(['"Approved" = ?' => 1]);
        $query->addWhere(['"SubjectClass" IN (' . $placeholders . ')' => $classes]);
        $query->setGroupBy('"SubjectID"');

        $averages = [];
        foreach ($query->execute() as $row) {
            $averages[(int) $row['SubjectID']] = (float) $row['AvgRating'];
        }

        return $averages;
    }

    private function productIdsWithMinRating(string $subjectClass, float $min): array
    {
        $classes = array_values(ClassInfo::subclassesFor($subjectClass));
        $placeholders = implode(',', array_fill(0, count($classes), '?'));

        $query = SQLSelect::create('"SubjectID"', '"SilverShop_Review"');
        $query->addWhere(['"Approved" = ?' => 1]);
        $query->addWhere(['"SubjectClass" IN (' . $placeholders . ')' => $classes]);
        $query->setGroupBy('"SubjectID"');
        $query->addHaving(['AVG("Rating") >= ?' => $min]);

        $ids = [];
        foreach ($query->execute() as $row) {
            $ids[] = (int) $row['SubjectID'];
        }

        return $ids;
    }

    private function label(int $stars): string
    {
        return _t(self::class . '.STARS', '{stars}★ and up', ['stars' => $stars]);
    }
}

}
