jQuery(document).ready(function($) {

    // הרחבה וכווץ קטגוריות
    $(document).on('click', '.toggle', function() {
        const $sublist = $(this).siblings('ul');
        $sublist.toggle();
        $(this).text($sublist.is(':visible') ? '▼' : '▶');
    });

    // מצב עריכה
    let editMode = false;
    $('#kb-edit-mode').on('click', function() {
        editMode = !editMode;
        $('.edit-cat').toggle(editMode);
        $(this).text(editMode ? 'צא ממצב עריכה' : 'עריכת קטגוריות');
    });

    // העברת מאמרים
    $('#kb-move-articles').on('click', function() {
        const selected = $('.kb-article-checkbox:checked').map(function() { return this.value; }).get();
        if (selected.length === 0) {
            alert('בחר מאמרים כלשהם קודם.');
            return;
        }
        const newCat = prompt('הכנס ID של קטגוריה היעד:');
        if (!newCat) return;
        $.post(kbAjax.ajaxurl, {
            action: 'kb_move_articles',
            article_ids: selected,
            new_category: newCat
        }, function(res) {
            alert(res.data);
            location.reload();
        });
    });

    // סגירה של כל הקטגוריות כברירת מחדל
    $('.kb-category-list ul').hide();
    $('.kb-category-list > li > .toggle').text('▼').siblings('ul').show();
});
