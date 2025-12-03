jQuery(document).ready(function(){
    document.querySelectorAll(".kb-ckeditor").forEach(function(el){
        ClassicEditor.create(el, {
            language: 'he',
            toolbar: [ 'heading', '|', 'bold', 'italic', 'link', '|', 'bulletedList', 'numberedList', '|', 'undo', 'redo', 'imageUpload' ]
        })
        .then(editor => {
            el.editorInstance = editor;
            editor.editing.view.document.on('paste', (evt, data) => {
                setTimeout(() => checkImages(editor), 500);
            });
        })
        .catch(error => { console.error(error); });
    });

    function checkImages(editor) {
        const root = editor.model.document.getRoot();
        const range = editor.model.createRangeIn(root);
        for (const item of range.getItems()) {
            if (item.isElement && (item.name === 'imageBlock' || item.name === 'imageInline')) {
                const src = item.getAttribute('src');
                if (src && src.startsWith('data:image')) {
                    upload(src, item, editor);
                }
            }
        }
    }

    function upload(data, img, editor) {
        jQuery.ajax({
            url: kbAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'upload_pasted_image',
                imagedata: data,
                nonce: kbAjax.nonce
            },
            success: function(r){
                if(r.success) {
                    editor.model.change(writer=>{
                        writer.setAttribute('src', r.data.url, img);
                        writer.setAttribute('alt', '', img);
                    });
                } else alert('בעיה בהעלאת תמונה');
            }
        });
    }
});
