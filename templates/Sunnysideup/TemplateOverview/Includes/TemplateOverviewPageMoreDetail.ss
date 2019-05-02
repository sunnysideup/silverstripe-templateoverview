<% loop Results %>
<li class="example moreDetailLI">
    <a href="$PreviewLink">$Title</a> |
    <a href="$CMSEditLink">IN CMS</a> |
    <a href="https://developers.facebook.com/tools/debug/sharing/?q=$PreviewLink">FB</a>
</li>
<% end_loop %>
