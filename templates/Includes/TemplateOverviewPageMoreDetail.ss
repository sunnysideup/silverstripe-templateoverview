<% if MoreDetail %>
	<% with MoreDetail %>
<li class="description moreDetailLI">
	<p class="templateOverviewDescription">
		<% if Description %>$Description ... <% else %> [no description provided] <% end_if %>
		 <a href="$ModelAdminLink">edit template description and and upload images</a>
		<% if ToDoListHyperLink %> | <a href="$ToDoListHyperLink.URL" class="updateToDoListLink">update to do list</a><% end_if %>
	</p>
	<div id="TemplateOverviewImages">
		<% if Image1ID %><span class="mediumThumb"><a href="$Image1.URL" rel="prettyPhoto[$ClassNameLink]">$Image1.SetWidth(525)</a></span><% end_if %>
		<% if Image2ID %><span class="mediumThumb"><a href="$Image2.URL" rel="prettyPhoto[$ClassNameLink]">$Image2.SetWidth(525)</a></span><% end_if %>
		<% if Image3ID %><span class="mediumThumb"><a href="$Image3.URL" rel="prettyPhoto[$ClassNameLink]">$Image3.SetWidth(525)</a></span><% end_if %>
		<% if Image4ID %><span class="mediumThumb"><a href="$Image4.URL" rel="prettyPhoto[$ClassNameLink]">$Image4.SetWidth(525)</a></span><% end_if %>
		<% if Image5ID %><span class="mediumThumb"><a href="$Image5.URL" rel="prettyPhoto[$ClassNameLink]">$Image5.SetWidth(525)</a></span><% end_if %>
		<% if Image6ID %><span class="mediumThumb"><a href="$Image5.URL" rel="prettyPhoto[$ClassNameLink]">$Image6.SetWidth(525)</a></span><% end_if %>
		<% if Image7ID %><span class="mediumThumb"><a href="$Image5.URL" rel="prettyPhoto[$ClassNameLink]">$Image7.SetWidth(525)</a></span><% end_if %>
	</div>
</li>
	<% end_with %>
<% end_if %>
<% loop Results %>
<li class="example moreDetailLI">{$Pos}. <a href="$URLSegment">$Title</a> - <a href="/admin/show/$ID">CMS view</a></li>
<% end_loop %>

