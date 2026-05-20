=== AI Provider for LM Studio ===
Contributors:      modemlooper
Tags:              ai, lm-studio, llm, local-ai, connector
Requires at least: 7.0
Tested up to:      7.0
Stable tag:        1.1.1
Requires PHP:      7.4
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

LM Studio provider for the WordPress AI Client.

== Description ==

This plugin provides [LM Studio](https://lmstudio.ai/) integration for the WordPress AI Client. It lets WordPress sites use large language models running locally via LM Studio for text and image generation and other AI capabilities.

LM Studio exposes an [OpenAI-compatible API](https://lmstudio.ai/docs/api), and this provider uses that API to communicate with any model you have loaded into LM Studio.

**Features:**

* Text generation with any LM Studio model
* Image generation with supported models
* Automatic model discovery from your LM Studio instance
* Function calling support
* Structured output (JSON mode) support
* Settings page for host URL and seeing available models
* Works without an API key for local instances

**Requirements:**

* PHP 7.4 or higher
* WordPress 7.0 or higher
* LM Studio running locally on your machine

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/ai-provider-for-lm-studio/`.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to **Settings > LM Studio** to configure the host URL and see available models.

== Frequently Asked Questions ==

= How do I install LM Studio? =

Visit [lmstudio.ai](https://lmstudio.ai/) to download and install LM Studio for your platform. Once installed, load a model and the provider will automatically discover it.

= Do I need an API key? =

No. For local LM Studio instances, no API key is needed. The plugin automatically handles authentication for local setups.

= How do I change the LM Studio host URL? =

By default, the provider connects to `http://localhost:1234`. You can change this in two ways:

1. Set the `LM_STUDIO_HOST` environment variable (takes precedence).
2. Go to **Settings > LM Studio** in the WordPress admin and enter your host URL.

== Screenshots ==

1. Settings > LM Studio screen showing available AI models and Host URL configuration.

== Changelog ==

== Upgrade Notice ==

= 1.0.0 =

Initial release.
