(function($){
    if(typeof lwFieldData === 'undefined') return;
    $(function(){
        var overlay = $('<div id="lw-field-overlay"></div>').css({
            position:'fixed',top:0,left:0,right:0,bottom:0,background:'rgba(0,0,0,0.5)',
            'z-index':100000,display:'none',padding:'40px',overflow:'auto'
        });
        var list = $('<div id="lw-field-list"></div>').css({background:'#fff',padding:'20px'});
        overlay.append(list);
        $('body').append(overlay);

        $('<button id="lw-field-button" class="button">'+lwFieldData.label+'</button>').appendTo('.edit-post-header-toolbar').on('click',function(){
            overlay.toggle();
            if(overlay.is(':visible')){ buildList(); makeDroppable(); }
        });

        function buildList(){
            list.empty();
            lwFieldData.fields.forEach(function(f){
                if(!lwFieldData.mapping[f.name]){
                    var item = $('<div class="lw-field"></div>').text(f.label).attr('data-field',f.name).css({border:'1px solid #ccc',padding:'5px',margin:'5px',cursor:'move'});
                    list.append(item);
                    item.draggable({helper:'clone'});
                }
            });
        }

        function makeDroppable(){
            $('.editor-block-list__block').each(function(){
                var block = $(this);
                if(block.data('lw-droppable')) return;
                block.data('lw-droppable',true);
                block.droppable({
                    hoverClass:'lw-highlight',
                    drop:function(e,ui){
                        var field = ui.draggable.data('field');
                        if(!field) return;
                        var id = block.attr('id');
                        if(!id){
                            id = 'lw-'+Math.random().toString(36).substr(2,8);
                            block.attr('id',id);
                        }
                        $.post(lwFieldData.ajaxurl,{action:'lw_update_mapping',slug:lwFieldData.slug,field:field,selector:'#'+id,_ajax_nonce:lwFieldData.nonce});
                        ui.draggable.remove();
                        lwFieldData.mapping[field] = '#'+id;
                    }
                });
            });
        }
    });
})(jQuery);
