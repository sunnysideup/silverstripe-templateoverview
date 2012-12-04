<ul>
	<li class="description moreDetailLI">
		<ul class="templateOverviewDescription">
			<li>$Description ... <a href="$ModelAdminLink" target="todo">edit this description and upload images</a></li>
			<% if ToDoListHyperLink %><li><a href="$ToDoListHyperLink.URL" class="updateToDoListLink" target="todo">update to do list</a></li><% end_if %>
		</ul>
		<ul id="TemplateOverviewImages">
			<% if Image1ID %><li><span class="mediumThumb"><a href="$Image1.URL" rel="prettyPhoto[$ClassNameLink]" target="todo">$Image1.SetWidth(100)</a></span></li><% end_if %>
			<% if Image2ID %><li><span class="mediumThumb"><a href="$Image2.URL" rel="prettyPhoto[$ClassNameLink]" target="todo">$Image2.SetWidth(100)</a></span></li><% end_if %>
			<% if Image3ID %><li><span class="mediumThumb"><a href="$Image3.URL" rel="prettyPhoto[$ClassNameLink]" target="todo">$Image3.SetWidth(100)</a></span></li><% end_if %>
			<% if Image4ID %><li><span class="mediumThumb"><a href="$Image4.URL" rel="prettyPhoto[$ClassNameLink]" target="todo">$Image4.SetWidth(100)</a></span></li><% end_if %>
			<% if Image5ID %><li><span class="mediumThumb"><a href="$Image5.URL" rel="prettyPhoto[$ClassNameLink]" target="todo">$Image5.SetWidth(100)</a></span></li><% end_if %>
			<% if Image6ID %><li><span class="mediumThumb"><a href="$Image6.URL" rel="prettyPhoto[$ClassNameLink]" target="todo">$Image6.SetWidth(100)</a></span></li><% end_if %>
			<% if Image7ID %><li><span class="mediumThumb"><a href="$Image7.URL" rel="prettyPhoto[$ClassNameLink]" target="todo">$Image7.SetWidth(100)</a></span></li><% end_if %>
		</ul>
	</li>
</ul>
