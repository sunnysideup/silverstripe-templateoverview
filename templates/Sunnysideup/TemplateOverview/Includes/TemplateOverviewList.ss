<h1 id="allclasses">Templates used on this website ($TotalCount): </h1>
<ul id="ClassList">
<% loop $ListOfAllClasses %>
    <li style="margin-top: 45px; padding-top: 0; background-image: url({$Icon}); background-repeat: no-repeat!important; background-position: top left; text-indent: 20px;" id="sectionFor-$ClassName">
    <% if Count %>
    <span class="typo-heading">$Count x $Name - template</span>
        <% if ShowAll %>
        <span class="typo-fullLink"><a href="$PreviewLink">$Title</a></span> | <a href="$CMSEditLink">CMS view</a>
        <% else %>
        <span class="typo-fullLink">
        <em>class:</em> $ClassName
        </span>
        <span class="typo-fullLink">
            <em>example:</em>
            <a href="$LiveLink">$Title</a> |
            <a href="{$LiveLink}json/?pretty=1">JSON</a> |
            <a href="$PreviewLink">Preview</a> |
            <a href="$CMSEditLink">Edit in CMS</a>
            <%-- <a href="https://developers.facebook.com/tools/debug/sharing/?q=$PreviewLink">in FB ...</a> --%>
        </span>
        <% if Count > 1 %>
        <span class="typo-more">show: <a href="$ControllerLink/showmore/$ID" class="typo-seemore" rel="entry-for-$URLSegment">more examples</a></span>
        <span class="typo-less">hide: </em> <a href="#" class="typo-seeless" rel="entry-for-$URLSegment">less examples</a></span>
        <% end_if %>
        <ol id="entry-for-$URLSegment" class="MoreDetailOL"><li style="display: none;">&nbsp;</li></ol>
        <% end_if %>
    <% else %>
        There are no instances of $ClassName templates.</li>
    <% end_if %>
    </li>
<% end_loop %>
</ul>
