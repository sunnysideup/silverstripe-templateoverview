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
    <aside>
        <h3>Alternative Views</h3>
        <p>
            <a href="/dev/tasks/smoketest/?htmllist=1" target="_blank">basic list</a>,
            <a href="/dev/tasks/smoketest/?htmllist=1&limit=999999&nobackend=true" target="_blank">all front-end pages</a>,
            <a href="/dev/tasks/smoketest/?nofrontend=1" target="_blank">back-end only</a>.
            <a href="/dev/tasks/smoketest/?sitemaperrors=1" target="_blank">site map errors</a>.
            <br />
            (GET variable options are: limit, nofrontend, nobackend, htmllist, sitemaperrors)
        </p>
        <p>
            There is also a list of <a href="/admin/templates">templates</a> available.
        </p>
        <p>
            If you really want test without caution, you can <a href="/dev/tasks/write-all-data-objects">write all dataobjects</a> to the database.
        </p>
    </aside>
    <% if $HasEnvironmentVariable %><a href="#" class="start btn prevent-default">Start</a><% else %>
        <span style="color: red">You must set the <strong>SS_ALLOW_SMOKE_TEST</strong> environment variable to <strong>TRUE</strong> to run the tests below!</span>
    <% end_if %>
    <p class="stats">
        <strong>Total Tests:</strong> <span id="NumberOfTests">0</span>,
        <strong>Tests Done:</strong> <span id="NumberOfTestsDone">0</span>,
        <strong>Average Response Time:</strong> <span id="AverageResponseTime">0</span>,
        <strong>Number of errors:</strong> <span id="NumberOfErrors">0</span>,
        <strong>Error Percentage:</strong> <span id="ErrorRate">0%</span>
    </p>

    <table class='checker-list table'>
        <thead>
            <tr>
                <th class="clickable" onclick="SmokeTester.sortTable('.test', this)">Test</th>
                <th class="clickable" onclick="SmokeTester.sortTable('.link', this)">Link</th>
                <th class="clickable" onclick="SmokeTester.sortTable('.http-response', this)">HTTP&nbsp;response</th>
                <th class="clickable" onclick="SmokeTester.sortTable('.response-time', this)">Response&nbsp;time</th>
                <th class="clickable" onclick="SmokeTester.sortTable('.content', this)">Response</th>
                <th class="clickable" onclick="SmokeTester.sortTable('.w3-check', this)">>W3&nbsp;check</th>
            </tr>
        </thead>
        <tbody>
            <% loop $Links %>
            <tr id="link-{$ItemCount}" class="link-item <% if $IsCMSLink %>isCMSLink<% else %>isNonCMSLink<% end_if %>" data-is-cms-link="$IsCMSLink" data-link="$Link.XML">
                    <td class="test">
                        <span>$Pos</span>
                    <a href="{$Up.AbsoluteBaseURLMinusSlash}/admin/templateoverviewsmoketestresponse/testone/?test={$Link.XML}&amp;iscmslink={$IsCMSLink}" target='_blank'><% if $IsCMSLink %>✎<% else %>🌐<% end_if %></a>
                    </td>
                    <td class="link">
                        <a href="{$Up.AbsoluteBaseURLMinusSlash}$Link" target="_blank">$Link</a>
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
        By adding a public method <i>templateoverviewtests</i> to any controller or page,
        returning an array of links, they will be included in the list here.
        Alternatively, you can add links via yml:
    </p>
    <pre>
Sunnysideup\\TemplateOverview\\Api\\AllLinks:
  custom_links:
    - /some-other-link-you-want-to-test-1/
    - /some-other-link-you-want-to-test-2/
    </pre>
    <% if $OtherLinks %>
    <h3>Suggestions</h3>
    <p>Below is a list of suggested controller links.</p>
    <ul>
        $OtherLinks.RAW
    </ul>
    <% end_if %>
</body>
</html>
