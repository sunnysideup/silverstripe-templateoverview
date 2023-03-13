<% if $MyMoreList %>
<% loop $MyMoreList %>
<li class="example moreDetailLI">
    <a href="$PreviewLink">$Title</a> |
    <a href="$CMSEditLink">IN CMS</a> |
    <a href="https://developers.facebook.com/tools/debug/sharing/?q=$PreviewLink">FB</a>
    | found in $Breadcrumbs ...
</li>
<% end_loop %>
<% else %>
    <li>
        no further data available
    </li>
<% end_if %>
