<% if IncludeTemplateOverviewDevelopmentFooter %><% if ID %>
<div id="TemplateOverviewPageDevelopmentFooter">
	<h3>Templates</h3>
	<p>
		This page uses the <a href="{$TemplateOverviewPage.Link}showmore/$ID" class="IncludeTemplateOverviewDevelopmentFooterClickHere">$ClassName</a> template.
		See <a href="{$TemplateOverviewPage.Link}#sectionFor-$ClassName">complete list</a>.
			<!-- <a href="#" onclick="templateoverviewoverlay.addExtension('{$ClassName}'); " id="overlayFor{$ClassName}" class="overlayLink">add a design overlay.</a> -->
	</p>
	<ul id="TemplateOverviewPageDevelopmentFooterLoadHere"><li class="hiddenListItem">&nbsp;</li></ul>
	<h4>Choose template: </h4>
	<ol id="TemplateOverviewPrevNextList">
		<% loop TemplateList %><li style="background-image: url({$Icon});"><% if Count %><a href="{$FullLink}?flush=1" title="example: $Title.ATT" class="$LinkingMode"><% else %><a title="-- there are no $ClassName pages --"><% end_if %>$ClassName ($Count)</a></li><% end_loop %>
	</ol>
	<% if TemplateOverviewBugs %>
	<h3>Known Bugs</h3>
	<ul id="TemplateOverviewBugs">
	<% loop TemplateOverviewBugs %>
		<li><a href="$Link">$Title</a></li>
	<% end_loop %>
	</ul>
	<% end_if %>
	<p class="clear"><a href="$BugManagementLink">Record a bug</a></p>
</div>
<% end_if %><% end_if %>

