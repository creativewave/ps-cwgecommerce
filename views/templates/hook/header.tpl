<noscript>
    <iframe
        src="//www.googletagmanager.com/ns.html?id={$containerId}{$dataLayerQuery}"
        height="0"
        width="0"
        style="display:none;visibility:hidden"
    >
    </iframe>
</noscript>
<script>
    (function(w, d) {ldelim}
        w['dataLayer'] = [{$dataLayer}]
        w['dataLayer'].push({ldelim}'gtm.start': new Date().getTime(), event: 'gtm.js'{rdelim})
        var l = d.getElementsByTagName('script')[0]
        var s = d.createElement('script')
        s.src = '//www.googletagmanager.com/gtm.js?id={$containerId}'
        l.parentNode.insertBefore(s, l)
    {rdelim})(window, document)
</script>
