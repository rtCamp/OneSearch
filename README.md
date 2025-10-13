![Banner](https://rtcamp.com/wp-content/uploads/sites/2/2024/09/OneSearch-Banner.png)
# OneSearch [![Project Status: Active – The project has reached a stable, usable state and is being actively developed.](https://www.repostatus.org/badges/latest/active.svg)](https://www.repostatus.org/#active)

**Contributors:** [rtcamp](https://profiles.wordpress.org/rtcamp), [shreya0204](https://github.com/shreya0204), [danish17](https://github.com/danish17), [vishalkakadiya](https://github.com/vishalkakadiya), [rishavjeet](https://github.com/rishavjeet), [vishal4669](https://github.com/vishal4669), [up15122001](https://github.com/up1512001), [justlevine](https://github.com/justlevine), [aviral-mittal](https://github.com/aviral-mittal)

**Tags:** OnePress, OneSearch, Cross-site search, Multi-brand network, WordPress multisite, Federated search, WordPress REST API, Gutenberg, WordPress plugin

**License:** [GPL v2 or later](http://www.gnu.org/licenses/gpl-2.0.html)

> **OneSearch** is a [OnePress](https://rtcamp.com/onepress/) ecosystem plugin that enables **cross-site, brand-aware search** across a multi-brand WordPress network. It powers unified discovery across sites, powered by [Algolia](https://algolia.com/).

---

## 🧠 What is OneSearch?

**OneSearch** is part of the **OnePress ecosystem**, designed to enable **cross-site search** across multi-brand WordPress networks.  
It centralizes the discovery process, allowing users to search and retrieve relevant content from multiple connected sites — from any site(s) in your brand network.

This plugin acts as the **backend engine** that powers the indexing, querying, brand network management, and filtering logic needed for federated search.  

---

## 💡 Why OneSearch?

Managing content across multiple brands, regions, or business units often results in disconnected search experiences. This can lead to broken user journeys and lost discovery opportunities.

**OneSearch** solves this by enabling a **federated search layer** that bridges multiple sites, powered by [Algolia](https://algolia.com/) delivering a consistent and brand-respecting experience across the network.

### Benefits
- **Unified Search Layer:** Execute a single search query across multiple connected sites.
- **Brand Awareness:** Show source brand site and redirect user to the respective site.
- **Governance & Control:** Control visibility and indexing scope at the site or post-type level.
- **Developer Extensibility:** Easily register post types, taxonomies, and metadata for indexing.
- **Performance Optimized:** Lightweight REST architecture with cache-friendly responses.
- **Modular Design:** Extend and customize indexing and search behavior without core overrides.

---

## 🪄 Key Features

- **Cross-Site (Federated) Search:** Aggregate search results across multisite or standalone installations.  
- **Configurable Indexing:** Register which post types, taxonomies, or meta fields are searchable.  
- **Custom Blocks:** Gutenberg-ready blocks for search interfaces.  
- **Brand-Specific Filtering:** Enable brand-based search scopes for multi-brand networks.
- **Bring Your Own Key:**  Connect it with your Algolia instance for improved data sovereignty and reduced vendor lock-in.

---

## 🧾 Requirements

| Requirement   | Version |
|---------------|----------|
| WordPress     | >= 6.5 |
| PHP           | >= 7.4 |
| Tested Up To  | >= 6.8.2 |
| Stable Tag    | 1.0 |
| Prerequisites | Node.js (as per `.nvmrc`), npm, WordPress instance|

---

## 🧰 Installation
1. Download the latest OneSearch plugin from the [GitHub releases](https://github.com/rtCamp/OneDesign/releases).
2. Install using the WordPress plugin installer or upload the OneDesign directory to the `/wp-content/plugins/` directory.
3. For multisite installations, network activate the plugin through the ‘Plugins’ menu in WordPress.
4. For single site installations, activate the plugin through the ‘Plugins’ menu in WordPress.

## Setting Up OneSearch 
### Plugin Setup
1. Install and activate the OneDesign plugin on the governing site and brand sites.
2. On the brand sites, navigate to OneSearch.
3. Generate an API key and copy it.
4. On the governing site, navigate to OneSearch > Settings.
5. Click on 'Add Brand Site' and enter Site Name, Site URL, and API Key.

## Setting up Algolia
To connect your site with Algolia, follow these simple steps:
1. Visit [Algolia](https://www.algolia.com/) and create an account (if you don’t already have one).
2. After logging in, go to your [API Keys dashboard](https://dashboard.algolia.com/account/api-keys).
3. Copy the Application ID and Write API Key.
4. Paste both keys into the OneSearch Settings page under the 'Algolia Credentials' section.

### Configuring Indices and Search Scope
> Indices: The data (post types) stored to make site content searchable.
> Search Scope: Defines which sites can search or access other sites’ indexed data.

#### Configuring Indices
1. Head to OneSearch > Indices and Search.
2. Against each connected site, choose the post types you wish to index.
3. Click on 'Save' which will also index the data.

#### Configuring Search Scopes:
1. Go to OneSearch > Indices and Search and scroll to 'Site Search Configuration' section.
2. Turn on the toggle for the sites on which you want to enable OneSearch.
3. In the 'Search from' section, configure which sites can be searched from.

## Development & Contributing
OneSearch is under active development and maintained by [rtCamp](https://rtcamp.com).

Contributions are Welcome and encouraged! To learn more about contributing to OneSearch, please read the [Contributing Guide](https://github.com/rtCamp/OneSearch-internal/blob/main/docs/CONTRIBUTING.md).

For development guidelines, please refer to our [Development Guide](https://github.com/rtCamp/OneSearch-internal/blob/main/docs/DEVELOPMENT.md).

## Frequently Asked Questions
#### Does OneSearch support custom post types?
Yes, using OneSearch, you can index the default as well as custom post types.

#### Do I need to manually index a newly published post?
As long as the post type is included in the configured entities, new posts are automatically indexed.

#### Are updates to an already indexed post automatically handled?
Yes. Any updates made to a post are automatically synced with the Algolia index.

#### How are the search results ranked?
Search results are ranked by Algolia's relevance algorithm. However, OneSearch boosts results from the current site you're searching on, ensuring more relevant local content appears first.

## Get Involved
You can join the development and discussions on [GitHub](https://github.com/rtCamp/OneSearch). Feel free to report issues, suggest features, or contribute code.
