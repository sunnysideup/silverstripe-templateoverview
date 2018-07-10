<h1 id="allclasses">Templates used on this website ($TotalCount): </h1>
<ul id="ClassList">
<% loop ListOfAllClasses %>
    <% if Count %>
    <li style="background-image: url({$Icon}); background-repeat: no-repeat!important; background-position: top left; text-indent: 20px;" id="sectionFor-$ClassName">
        <span class="typo-heading">$Count x $ClassName - template</span>
        <% if ShowAll %>
        <span class="typo-fullLink"><a href="$PreviewLink">$Title</a></span> | <a href="$CMSEditLink">CMS view</a>
        <% else %>
        <span class="typo-fullLink">
            <em>example:</em>
            <a href="$PreviewLink">$Title</a> |
            <a href="$CMSEditLink">IN CMS</a> |
            <a href="https://developers.facebook.com/tools/debug/sharing/?q=$PreviewLink">in FB ...</a>
        </span>
        <% if Count > 1 %>
        <span class="typo-more"><em>more:</em> <a href="$TypoURLSegment/showmore/$ID" class="typo-seemore" rel="entry-for-$URLSegment">more examples</a></span>
        <span class="typo-less"><em>less:</em> <a href="#" class="typo-seeless" rel="entry-for-$URLSegment">hide it again!</a></span>
        <% end_if %>
        <ol id="entry-for-$URLSegment" class="MoreDetailOL"><li style="display: none;">&nbsp;</li></ol>
        <% end_if %>
    </li>
    <% else %>
    <li style="background-image: url({$Icon}); background-repeat: no-repeat!important; background-position: top left; text-indent: 20px;">There are no instances of $ClassName templates.</li>
    <% end_if %>
<% end_loop %>
</ul>
