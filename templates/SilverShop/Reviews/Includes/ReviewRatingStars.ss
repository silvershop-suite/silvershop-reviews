<%-- Progressive enhancement: turn the review form's Rating dropdown into an interactive star picker.
     Without JavaScript the plain <select> is used. Override this template to restyle/replace, or leave an
     empty file to keep the dropdown. Accessible: role=radiogroup/radio, keyboard (arrows), roving tabindex,
     mirrors the select's required state. --%>
<style>
    .rating-stars{display:inline-flex;gap:.1em;line-height:1}
    .rating-stars__star{background:none;border:none;padding:0 .04em;cursor:pointer;color:#d0d0d0;font-size:1.8em;line-height:1}
    .rating-stars__star.is-on{color:#f5a623}
    .rating-stars__star:focus-visible{outline:2px solid #1a56db;outline-offset:2px;border-radius:3px}
</style>
<script>
(function(){
    var scope = document.currentScript.closest('.product-reviews__form, .reviews__form') || document;
    var select = scope.querySelector('select[name="Rating"]');
    if(!select || select.getAttribute('data-stars-ready')) return;
    select.setAttribute('data-stars-ready','1');

    var wrap = document.createElement('div');
    wrap.className = 'rating-stars';
    wrap.setAttribute('role','radiogroup');

    // Accessible name from the field's label, and mirror the select's required state.
    var lblEl = (select.id && document.querySelector('label[for="'+select.id+'"]'));
    if(!lblEl){ var fld = select.closest('.field'); if(fld){ lblEl = fld.querySelector('label'); } }
    wrap.setAttribute('aria-label', (lblEl ? lblEl.textContent.replace(/\s+/g,' ').trim() : '') || 'Rating');
    if(select.required || select.getAttribute('aria-required') === 'true'){ wrap.setAttribute('aria-required','true'); }

    var stars = [];
    var current = parseInt(select.value, 10) || 0;

    function paint(n){
        for(var i=0;i<5;i++){
            stars[i].classList.toggle('is-on', i < n);
            stars[i].setAttribute('aria-checked', (i+1) === current ? 'true' : 'false');
        }
    }
    function roving(){
        var idx = current > 0 ? current - 1 : 0;
        for(var i=0;i<5;i++){ stars[i].tabIndex = (i === idx) ? 0 : -1; }
    }
    function set(n){
        current = n;
        select.value = String(n);
        paint(n);
        roving();
        select.dispatchEvent(new Event('change', {bubbles:true}));
    }

    for(var i=1;i<=5;i++){
        (function(val){
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'rating-stars__star';
            b.setAttribute('role','radio');
            b.setAttribute('aria-label', val + ' / 5');
            b.textContent = '★';
            b.addEventListener('mouseenter', function(){ paint(val); });
            b.addEventListener('focus', function(){ paint(val); });
            b.addEventListener('click', function(){ set(val); });
            wrap.appendChild(b);
            stars.push(b);
        })(i);
    }

    wrap.addEventListener('mouseleave', function(){ paint(current); });
    wrap.addEventListener('keydown', function(e){
        if(e.key === 'ArrowRight' || e.key === 'ArrowUp'){ e.preventDefault(); set(Math.min(5, (current||0)+1)); stars[current-1].focus(); }
        else if(e.key === 'ArrowLeft' || e.key === 'ArrowDown'){ e.preventDefault(); set(Math.max(1, (current||1)-1)); stars[current-1].focus(); }
    });

    select.parentNode.insertBefore(wrap, select);
    select.style.display = 'none';
    paint(current);
    roving();
})();
</script>
