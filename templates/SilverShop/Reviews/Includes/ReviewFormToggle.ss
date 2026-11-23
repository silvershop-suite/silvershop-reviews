<%-- Progressive enhancement: collapse the "write a review" form behind the "Write a review" button and reveal
     it on click. Rendered only when Review.collapse_write_form is on and the visitor may review. Without
     JavaScript the button stays hidden and the form shows as usual. Override this template to change the
     behaviour, or set Review.collapse_write_form = false to drop it. --%>
<script>
(function(){
    var wrap = document.currentScript.closest('.product-reviews__form, .reviews__form');
    if(!wrap) return;
    var btn = wrap.querySelector('[data-review-toggle]');
    var body = wrap.querySelector('[data-review-formbody]');
    if(!btn || !body) return;

    body.hidden = true;
    btn.hidden = false;
    btn.addEventListener('click', function(e){
        e.preventDefault();
        body.hidden = false;
        btn.hidden = true;
        var first = body.querySelector('input,select,textarea');
        if(first) first.focus();
    });
})();
</script>
