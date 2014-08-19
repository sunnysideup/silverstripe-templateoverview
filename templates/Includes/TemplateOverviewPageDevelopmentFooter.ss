<% if IncludeTemplateOverviewDevelopmentFooter %>
<div id="TemplateOverviewPageDevelopmentFooter">
	<p>
		<% if TemplateOverviewPage.CanView %>This page uses the <a href="{$TemplateOverviewPage.Link}showmore/$ID" class="seeFullTemplateDetails">$ClassName</a>
		<a href="{$TemplateOverviewPage.Link}">template</a>.
		<% else %>
		<a href="{$TemplateOverviewPage.Link}">review templates</a>.
		<% end_if %>
	</p>
	<ul id="TemplateOverviewPageDevelopmentFooterLoadHere"><li class="hiddenListItem">&nbsp;</li></ul>
</div>
<% end_if %>

