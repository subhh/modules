$(document).ready(function() {
    /*
     * Get result count for inactive Tabs
     */
    $('ul.nav-tabs li a').each(function(){
        var $this = $(this);
        if ($this.attr('href') !== undefined) {
            var queryString = $this.attr('href').replace(/^.*\?/, '');
            var source = $this.data('source');
            jQuery.ajax({
                url:VuFind.path + '/AJAX/JSON?method=getResultCount',
                dataType:'json',
                data:{querystring:encodeURIComponent(queryString), source:source},
                success:function(data, textStatus){
                    $this.append(' (' + data.data.total.toLocaleString() + ')')
                }
            });
        }
    });
});