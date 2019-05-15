<!DOCTYPE html>

<html lang="$ContentLocale">

<head>
	<% base_tag %>
	<title>$Title &raquo; $SiteConfig.Title</title>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	$MetaTags(false)
</head>
<body>
	<div>
		<a href="#" class="start btn">Start</a>
	</div>
	<p><strong>Tests Done:</strong> <span id="NumberOfTests">0</span></p>
	<p><strong>Average Response Time:</strong> <span id="AverageResponseTime">0</span></p>

	<table class='checker-list table'>
		<thead>
			<tr>
				<th>Link</th>
				<th>HTTP&nbsp;response</th>
				<th>Response&nbsp;time</th>
				<th>Response</th>
				<th>W3&nbsp;check</th>
			</tr>
		</thead>
		<tbody>
			<% loop $Links %>
			<tr id="link-{$ItemCount}" class="link-item <% if $IsAdmin %>isAdmin<% else %>notAdmin<% end_if %>" data-is-admin="$IsAdmin" data-link="$Link.XML">
					<td class="link">
                        <a href="{$AbsoluteBaseURL}dev/tasks/smoketest/?test={$Link.XML}&amp;admin={$IsAdmin}" target='_blank'>☢</a> &nbsp;&nbsp;
						<a href="{$AbsoluteBaseURLMinusSlash}$Link" target="_blank">$Link</a>
					</td>
					<td class="http-response"></td>
					<td class="response-time"></td>
					<td class="content"></td>
					<td class="w3-check"></td>
				</tr>
			<% end_loop %>
		</tbody>
	</table>

	<h2>Want to add more tests?</h2>
	<p>
		By adding a public method <i>templateoverviewtests</i> to any controller,
		returning an array of links, they will be included in the list above.
	</p>

	<% if $OtherLinks %>
	<h3>Suggestions</h3>
	<p>Below is a list of suggested controller links.</p>
	<ul>
		$OtherLinks.RAW
	</ul>
	<% end_if %>
</body>
</html>
