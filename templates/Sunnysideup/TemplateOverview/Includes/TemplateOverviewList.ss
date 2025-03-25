<h1 id="allclasses">Templates used on this website ($TotalTemplateCount templates in $TotalPageCount pages): </h1>
<% if $HasElemental %>
    <p>Also see
    <% if $IsElemental %>
        <a href="/admin/templates">pages</a>
    <% else %>
        <a href="/admin/templates-elemental">elemental</a>
    <% end_if %>
    </p>
    <p>There is also a smoke test system that allows you to <a href="/dev/tasks/smoketest">check all links available</a>.
<% end_if %>

<ul id="ClassList">
<% loop $ListOfAllClasses %>
    <li id="sectionFor-$ClassName">

        <% if $Icon %>
        <div style="background-image: url({$Icon}); background-repeat: no-repeat!important; background-position: top left; height: 32px; width: 32px; float: right; ">
            <i class="fas $Icon"></i>
        </div>
        <% end_if %>

        <% if $Count > 0 %>
        <strong>$Count x $Name - template</strong>
        <ul>
            <li>
                <% if $Description %>$Description<% else %>No description provided<% end_if %>
            </li>
            <% if ShowAll %>
            <li>
                <a href="$PreviewLink">$Title</a>
            </li>
            <li>
                <a href="$CMSEditLink">CMS view</a>
            </li>
            <% else %>
            <li>technical details
                <ul>
                    <li>
                        <em>class:</em>
                        $ClassName
                    </li>
                    <li>
                        <em>can create more:</em>
                        $MoreCanBeCreated
                    </li>
                    <li>
                        <em>allowed children:</em>
                        $AllowedChildren.Count
                    </li>
                    <li>
                        <em>allowed actions:</em>
                        $AllowedActions
                    </li>
                </ul>
            <li><em>example:</em>
                <ul>
                    <li>
                        <a href="$LiveLink">$Title</a> | <a href="$CMSEditLink">Edit in CMS</a>
                    </li>
                </ul>
            </li>

            <%-- <a href="https://developers.facebook.com/tools/debug/sharing/?q=$PreviewLink">in FB ...</a> --%>
            <% if Count > 1 %>
            <li class="typo-more">
                <em>show:</em>
                <a href="$ControllerLink/showmore/$ID" class="typo-seemore" rel="entry-for-$URLSegment">more examples</a>
            </li>
            <li class="typo-less">
                <em>hide:</em>
                <a href="#" class="typo-seeless prevent-default" rel="entry-for-$URLSegment">less examples</a>
            </li>
            <li class="more-details-holder">
                <ol id="entry-for-$URLSegment" class="MoreDetailOL">
                    <li style="display: none;">&nbsp;</li>
                </ol>
            </li>
            <% end_if %>

            <% end_if %>
        </ul>
        <% else %>
        There are no instances of $ClassName templates.
        <% end_if %>
    </li>
<% end_loop %>
</ul>
