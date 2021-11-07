<h1 id="allclasses">Templates used on this website ($TotalCount): </h1>
<ul id="ClassList">
<% loop $ListOfAllClasses %>
    <li id="sectionFor-$ClassName">
        <div style="background-image: url({$Icon}); background-repeat: no-repeat!important; background-position: top left; height: 32px; width: 32px; float: right; ">

        </div>
        <% if Count %>
        <strong>$Count x $Name - template</strong>
        <ul>
            <li>
                $Description
            </li>
            <% if ShowAll %>
            <li>
                <a href="$PreviewLink">$Title</a>
            </li>
            <li>
                <a href="$CMSEditLink">CMS view</a>
            </li>
            <% else %>
            <li>
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
                        $AllowedChildren
                    </li>
                    <li><em>example:</em>
                        <ul>
                            <li>
                                <a href="$LiveLink">$Title</a>
                            </li>
                            <li>
                                <a href="$PreviewLink">Preview</a>
                            </li>
                            <li>
                                <a href="$CMSEditLink">Edit in CMS</a>
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
                        <a href="#" class="typo-seeless" rel="entry-for-$URLSegment">less examples</a>
                    </li>
                    <% end_if %>
                    <li>
                        <ol id="entry-for-$URLSegment" class="MoreDetailOL">
                            <li style="display: none;">&nbsp;</li>
                        </ol>
                    </li>
                </ul>
            </li>
            <% end_if %>
        </ul>
        <% else %>
        There are no instances of $ClassName templates.
        <% end_if %>
    </li>
<% end_loop %>
</ul>
