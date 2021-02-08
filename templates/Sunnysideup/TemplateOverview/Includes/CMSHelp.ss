<h1>Help Guides</h1>
<h2>General</h2>
<p>General help with the Silverstripe Content Management System (CMS) is provided by <a href="http://userhelp.silverstripe.org/" target="_blank">Silvertripe Ltd</a>  (http://userhelp.silverstripe.org/).</p>
<ol>
    <li><a href="https://userhelp.silverstripe.org/en/3.3/managing_your_website/logging_in/">getting started - how to log in</a></li>
    <li><a href="https://userhelp.silverstripe.org/en/3.3/managing_your_website/overview/">the CMS basics</a></li>
    <li><a href="https://userhelp.silverstripe.org/en/3.3/creating_pages_and_content/pages/">editing pages </a></li>
    <li><a href="https://userhelp.silverstripe.org/en/3.3/creating_pages_and_content/web_content_best_practises/">best practices </a></li>
    <li><a href="https://userhelp.silverstripe.org/en/3.3/optional_features/forms/">forms</a></li>
    <li><a href="https://userhelp.silverstripe.org/en/3.3/managing_your_website/changing_and_managing_users/">managing users</a></li>
</ol>
<% if HelpFiles %>
    <h2 id="TOCHeading">Specific to $SiteTitle</h2>
    <p>Below is a list of securely stored help guides/manuals providing information about the functionality of your website.</p>
    <% loop HelpFiles %>
    <div class="$EvenOdd $FirstLast" id="Pos$Pos">
        <h4>$Title</h4>
        <p>
            Download:
            <a href="$Link" class="small">$FileName</a>
        </p>
    </div>
    <% end_loop %>
<% else %>
<p>There are no help files.</p>
<% end_if %>