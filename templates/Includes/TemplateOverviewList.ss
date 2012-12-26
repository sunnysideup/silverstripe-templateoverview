<h1 id="allclasses">Templates used on this website ($TotalCount): </h1>
<ul id="classList">
<% loop ListOfAllClasses %>
	<% if Count %>
	<li style="background-image: url({$Icon});" id="sectionFor-$ClassName">
		<% if TemplateOverviewDescription %>
		<div class="images">
		<% with TemplateOverviewDescription %>
			<% if Image1ID %><span class="thumb"><a href="$Image1.URL" rel="prettyPhoto[$ClassNameLink]">$Image1.SetWidth(150)</a></span><% end_if %>
			<div class="littleImages">
				<% if Image2ID %><span class="littleThumb"><a href="$Image2.URL" rel="prettyPhoto[$ClassNameLink]">$Image2.SetWidth(25)</a></span><% end_if %>
				<% if Image3ID %><span class="littleThumb"><a href="$Image3.URL" rel="prettyPhoto[$ClassNameLink]">$Image3.SetWidth(25)</a></span><% end_if %>
				<% if Image4ID %><span class="littleThumb"><a href="$Image4.URL" rel="prettyPhoto[$ClassNameLink]">$Image4.SetWidth(25)</a></span><% end_if %>
				<% if Image5ID %><span class="littleThumb"><a href="$Image5.URL" rel="prettyPhoto[$ClassNameLink]">$Image5.SetWidth(25)</a></span><% end_if %>
			</div>
		<% end_with %>
		</div>
		<% end_if %>
		<span class="typo-heading">$Count x $ClassName - template</span>
		<% if ShowAll %>
		<span class="typo-fullLink"><a href="$FullLink">$FullLink</a> :: $Title</span>
		<% else %>
		<span class="typo-fullLink"><em>example:</em> <a href="$FullLink">$FullLink :: $Title</a></span>
		<span class="typo-more"><em>more:</em> <a href="$TypoURLSegment/showmore/$ID" class="typo-seemore" rel="entry-for-$URLSegment">more examples and details (if any)</a></span>
		<span class="typo-less"><em>less:</em> <a href="#" class="typo-seeless" rel="entry-for-$URLSegment">hide it again!</a></span>
		<ol id="entry-for-$URLSegment" class="MoreDetailOL"><li style="display: none;">&nbsp;</li></ol>
		<% end_if %>
	</li>
	<% else %>
	<li style="background-image: url({$Icon});">There are no instances of $ClassName templates.</li>
	<% end_if %>
<% end_loop %>
</ul>
