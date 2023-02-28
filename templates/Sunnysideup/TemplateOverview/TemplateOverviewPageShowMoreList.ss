<% if $MyMoreList %>
<% loop $MyMoreList %>
<li class="example moreDetailLI">
    <a href="$PreviewLink">$Breadcrumbs</a> |
    <a href="$CMSEditLink">IN CMS</a> |
    <a href="https://developers.facebook.com/tools/debug/sharing/?q=$PreviewLink">FB</a>
</li>
<% end_loop %>
<% else %>
    <li>
        no further data available
    </li>
<% end_if %>
