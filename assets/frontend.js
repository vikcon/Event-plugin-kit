document.addEventListener('DOMContentLoaded', function () {

  // A) Rename the past button text
  document.querySelectorAll('.tec-past-widget .elementor-post__read-more').forEach(function(btn){
    btn.textContent = 'Watch Recording';
  });

  // B) Move excerpt (date/time) before title
  document.querySelectorAll('article.tribe_events .elementor-post__text').forEach(function(wrap){
    const title = wrap.querySelector('.elementor-post__title');
    const excerpt = wrap.querySelector('.elementor-post__excerpt');
    if (title && excerpt) wrap.insertBefore(excerpt, title);
  });

  // C) Link "Watch Recording" to Event Website URL (_EventURL) for past widget only
  document.querySelectorAll('.tec-past-widget article.tribe_events').forEach(function(article){
    const btn = article.querySelector('.elementor-post__read-more');
    if (!btn) return;

    const m = (article.className || '').match(/post-(\d+)/);
    if (!m) return;

    const postId = m[1];

    fetch('/wp-json/wp/v2/tribe_events/' + postId)
      .then(r => r.json())
      .then(post => {
        if (post && post.meta && post.meta._EventURL) {
          btn.href = post.meta._EventURL;
          btn.target = '_blank';
          btn.rel = 'noopener';
        }
      })
      .catch(()=>{});
  });

});
