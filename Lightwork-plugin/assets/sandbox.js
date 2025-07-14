jQuery(function($){

    $('.lw-field').draggable({helper:'clone'});
    function attachDroppable(){
        var doc = $('#lw-preview iframe')[0].contentDocument;
        $(doc).find('*').each(function(){
            try{ $(this).droppable('destroy'); }catch(e){}
            $(this).droppable({
                hoverClass:'lw-highlight',
                drop:function(e,ui){
                    var field = $(ui.draggable).data('field');
                    if(field){
                        this.setAttribute('id', field);
                        var clone = $(doc.body).clone();
                        clone.find('style,script').remove();
                        $('#lw-html').val(clone.html());
                        updatePreview();
                    }
                }
            });
        });
    }


    function updatePreview(){
        var html = $('#lw-html').val();
        var css = $('#lw-css').val();
        var js = $('#lw-js').val();
        var doc = $('#lw-preview iframe')[0].contentDocument;
        doc.open();
        doc.write(html + '<style>'+css+'</style><script>'+js+'<\/script>');
        doc.close();

        attachDroppable();

    }
    $('#lw-run').on('click', function(e){
        e.preventDefault();
        updatePreview();
    });
    $('#lw-save').on('click', function(e){
        e.preventDefault();
        $.post(lwSandbox.ajaxurl, {
            action:'lw_save_sandbox',
            nonce:lwSandbox.nonce,
            html:$('#lw-html').val(),
            css:$('#lw-css').val(),
            js:$('#lw-js').val()
        }, function(){
            alert('Saved');
        });
    });
    $('#lw-preview iframe').on('load', function(){
        var doc = this.contentDocument;
        $(doc).on('click', '*', function(ev){
            ev.preventDefault();
            var html = $('#lw-html').val();
            var tag = this.outerHTML.split(/\n/)[0];
            var idx = html.indexOf(tag);
            if(idx>=0){
                $('#lw-html')[0].setSelectionRange(idx, idx);
                $('#lw-html')[0].focus();
            }
            if(this.hasAttribute('style')){
                $('#lw-css').val(this.getAttribute('style'));
            }
        });
    });
    updatePreview();
});
