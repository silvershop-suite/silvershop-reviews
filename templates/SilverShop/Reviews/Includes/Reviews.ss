<%-- Reviews section for any reviewable page (ReviewablePageExtension on the record, ReviewablePageControllerExtension
     on its controller): rating summary, per-star breakdown, approved reviews with pagination and helpful votes, and the
     write-a-review form. Include it in the page template with <% include SilverShop\Reviews\Reviews %>. --%>
<% if $ReviewsEnabled %>
<section class="reviews" id="reviews">
    <style>
        .reviews{margin:2em 0;border-top:1px solid #e2e2e2;padding-top:1.5em}
        .reviews h2{margin:0 0 1em}
        .reviews__summary{display:flex;align-items:center;gap:.75em;margin-bottom:1em;flex-wrap:wrap}
        .reviews__avg{font-size:2em;font-weight:700;line-height:1}
        .reviews__stars{position:relative;display:inline-block;font-size:1.25em;line-height:1;white-space:nowrap}
        .reviews__stars-off{color:#d0d0d0}
        .reviews__stars-on{position:absolute;top:0;left:0;overflow:hidden;color:#f5a623}
        .reviews__count{color:#666}
        .reviews__breakdown{list-style:none;margin:0 0 1.5em;padding:0;max-width:20em}
        .reviews__breakdown li{display:flex;align-items:center;gap:.5em;color:#666}
        .reviews__bar{flex:1;height:.5em;background:#eee;border-radius:.25em;overflow:hidden}
        .reviews__bar span{display:block;height:100%;background:#f5a623}
        .reviews .review{border-top:1px solid #eee;padding:1em 0}
        .reviews .review__stars{color:#f5a623;letter-spacing:.125em}
        .reviews .review__verified{margin-left:.5em;color:#2e7d32;font-weight:600}
        .reviews .review__title{margin:.4em 0 .2em}
        .reviews .review__meta{margin:0 0 .4em;color:#767676}
        .reviews .review__body{white-space:pre-line}
        .reviews .review__points{list-style:none;margin:.6em 0 .2em;padding:0;display:flex;flex-direction:column;gap:.3em}
        .reviews .review__point{display:flex;align-items:baseline;gap:.5em}
        .reviews .review__point-mark{flex:0 0 1.3em;width:1.3em;height:1.3em;line-height:1.3em;text-align:center;border-radius:50%;font-weight:700;color:#fff;font-size:.78em}
        .reviews .review__point--pro .review__point-mark{background:#2e7d32}
        .reviews .review__point--con .review__point-mark{background:#c0392b}
        .reviews .review__photos{display:flex;gap:.4em;margin:.6em 0;flex-wrap:wrap}
        .reviews .review__photos img{border-radius:.25em;object-fit:cover}
        .reviews .review__helpful{margin:.5em 0 0;color:#767676;display:flex;align-items:center;gap:.5em}
        .reviews .review__vote{display:inline;margin:0}
        .reviews .review__vote button{background:none;color:#555;border:1px solid #ccc;border-radius:.25em;padding:.1em .5em;cursor:pointer}
        .reviews__pagination{display:flex;gap:.3em;align-items:center;margin:1em 0;flex-wrap:wrap}
        .reviews__pagination a,.reviews__pagination strong,.reviews__pagination span{padding:.15em .6em;border:1px solid #ddd;border-radius:.25em;text-decoration:none;color:#555}
        .reviews__pagination strong{background:#f5a623;border-color:#f5a623;color:#222}
        .reviews__form{margin-top:1.5em;max-width:32em}
        .reviews__form .is-required label::after{content:" *";color:#c0392b}
    </style>

    <h2><%t SilverShop\Reviews.Heading "Reviews" %></h2>

    <% if $HasReviews %>
        <div class="reviews__summary">
            <span class="reviews__avg">$AverageRating</span>
            <span>/ 5</span>
            <span class="reviews__stars" aria-hidden="true">
                <span class="reviews__stars-off">★★★★★</span>
                <span class="reviews__stars-on" style="width:{$AverageRatingPercent}%">★★★★★</span>
            </span>
            <span class="reviews__count"><%t SilverShop\Reviews.Count "{count} review(s)" count=$RatingCount %></span>
        </div>

        <ul class="reviews__breakdown">
            <% loop $RatingBreakdown %>
                <li>
                    <span>$Stars★</span>
                    <span class="reviews__bar"><span style="width:{$Percent}%"></span></span>
                    <span>$Count</span>
                </li>
            <% end_loop %>
        </ul>

        $ReviewsSchemaOrg
    <% else %>
        <p><%t SilverShop\Reviews.NoneYet "No reviews yet. Be the first to leave one." %></p>
    <% end_if %>

    <% loop $PaginatedReviews %>
        <article class="review">
            <header>
                <span class="review__stars" aria-label="$Rating / 5">$StarsString</span>
                <% if $Verified %><span class="review__verified"><%t SilverShop\Reviews.VerifiedCustomer "✓ Verified customer" %></span><% end_if %>
            </header>
            <% if $Title %><h3 class="review__title">$Title</h3><% end_if %>
            <p class="review__meta">$AuthorName &middot; $Created.Nice</p>
            <div class="review__body">$Content</div>

            <% if $HasPoints %>
                <ul class="review__points">
                    <% loop $ProsList %><li class="review__point review__point--pro"><span class="review__point-mark" aria-hidden="true">+</span> $Text.XML</li><% end_loop %>
                    <% loop $ConsList %><li class="review__point review__point--con"><span class="review__point-mark" aria-hidden="true">&minus;</span> $Text.XML</li><% end_loop %>
                </ul>
            <% end_if %>

            <% if $HasImages %>
                <div class="review__photos">
                    <% loop $Images %><% if $Thumbnail %>$Thumbnail<% end_if %><% end_loop %>
                </div>
            <% end_if %>

            <% if $VotesEnabled %>
                <div class="review__helpful">
                    <span><%t SilverShop\Reviews.Helpful "Was this helpful?" %></span>
                    <form method="post" action="$VoteLink('up')" class="review__vote">$VoteSecurityField<button type="submit">&#128077; $HelpfulUp</button></form>
                    <form method="post" action="$VoteLink('down')" class="review__vote">$VoteSecurityField<button type="submit">&#128078; $HelpfulDown</button></form>
                </div>
            <% end_if %>
        </article>
    <% end_loop %>

    <% with $PaginatedReviews %>
        <% if $MoreThanOnePage %>
            <nav class="reviews__pagination" aria-label="<%t SilverShop\Reviews.PagesLabel 'Review pages' %>">
                <% if $NotFirstPage %><a href="{$PrevLink}#reviews" rel="prev">&laquo;</a><% end_if %>
                <% loop $PaginationSummary(4) %>
                    <% if $CurrentBool %><strong>$PageNum</strong><% else_if $Link %><a href="{$Link}#reviews">$PageNum</a><% else %><span>&hellip;</span><% end_if %>
                <% end_loop %>
                <% if $NotLastPage %><a href="{$NextLink}#reviews" rel="next">&raquo;</a><% end_if %>
            </nav>
        <% end_if %>
    <% end_with %>

    <div class="reviews__form">
        <% if $CanReview %>
            <% if $CollapseReviewForm %><input type="submit" class="action" data-review-toggle value="<%t SilverShop\Reviews.WriteHeading 'Write a review' %>" hidden /><% end_if %>
            <div class="reviews__formbody"<% if $CollapseReviewForm %> data-review-formbody<% end_if %>>
                <h3><%t SilverShop\Reviews.WriteHeading "Write a review" %></h3>
                $ReviewForm
                <% include SilverShop\Reviews\ReviewRatingStars %>
            </div>
            <% if $CollapseReviewForm %><% include SilverShop\Reviews\ReviewFormToggle %><% end_if %>
        <% else_if $ReviewGateMessage %>
            <p class="review-gate">$ReviewGateMessage</p>
        <% end_if %>
    </div>
</section>
<% end_if %>
