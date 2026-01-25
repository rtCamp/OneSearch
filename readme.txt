=== OneSearch ===
Contributors: rtcamp, shreya0204, danish17, vishalkakadiya, rishavjeet, vishal4669, up1512001, justlevine, aviral-mittal
Donate link: https://rtcamp.com/
Tags: OnePress, OneSearch, Cross-site search, Multi-brand network, WordPress multisite, Federated search, Algolia
Requires at least: 6.8
Tested up to: 6.9
Stable tag: 1.0.0
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Description ==

OneSearch is part of the OnePress ecosystem, designed to enable cross-site search across multi-brand WordPress networks. It centralizes the discovery process, allowing users to search and retrieve relevant content from multiple connected sites from whichever site(s) in your brand network you choose to make searchable.

This plugin acts as the backend engine that powers the indexing, querying, brand network management, and filtering logic needed for federated search.

== Installation ==

1. Download the latest OneSearch plugin from the GitHub releases and install it on your WordPress sites.
2. Activate the plugin. For multisite installations, make sure to Network Activate the plugin.
3. Visit the Dashboard > OneSearch > Settings page to configure the Governing and Brand sites.

== Frequently Asked Questions ==

= Does OneSearch support CPTs (custom post types)? =

Yes. For each brand site, you can select which built-in and custom post types to index from the Indices and Search settings.

= Do I need to manually index a newly published post? =

No. Posts are automatically indexed when they are published, and removed when they are deleted or otherwise unpublished (e.g. trashed, changed to draft, etc.).

= Are updates to an already indexed post automatically handled? =

Yes. Any updates made to a post are automatically synced with the Algolia index.

= How are the search results ranked? =

Search results are ranked by Algolia's relevance algorithm. However, OneSearch boosts results from the current site you're searching on, ensuring more relevant local content appears first. You can further customize ranking and relevance through Algolia's dashboard.

== Screenshots ==

@todo

== Changelog ==

For the full changelog, please visit <a href="https://github.com/rtCamp/OneLogs/blob/main/CHANGELOG.md" target="_blank">GitHub repository</a>.

== Support ==

For support, feature requests, and bug reports, please visit our [GitHub repository](https://github.com/rtCamp/OneSearch).

== Contributing ==

OneSearch is open source and welcomes contributions. Visit our [GitHub repository](https://github.com/rtCamp/OneSearch) to contribute code, report issues, or suggest features.

Development guidelines and contributing information can be found in our repository documentation.
