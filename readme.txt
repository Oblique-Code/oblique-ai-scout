=== Oblique AI Scout ===
Contributors: obliquecode
Tags: ai, crawler, bot, analytics, gptbot, claudebot, perplexity, tracking, llm
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Track 56+ AI crawler visits to your WordPress site — GPTBot, ClaudeBot, PerplexityBot, Gemini, DeepSeek, and more. Zero external APIs. Your data stays local.

== Description ==

= Know exactly which AI bots crawl your content. =

Oblique AI Scout is a lightweight, privacy-first WordPress plugin that monitors AI/LLM crawler activity on your site. No SaaS. No external data sharing. Pure WordPress.

= Why You Need This =

AI-powered search engines and content tools are crawling the web at scale. Know which bots visit your site, what pages they discover, and where your content blind spots are — all from one clean dashboard.

= Key Features =

* **56+ AI Crawlers** — OpenAI, Anthropic, Google, Meta, Perplexity, Mistral, DeepSeek, xAI, Amazon, and many more
* **Live Dashboard** — Activity timeline, bot leaderboard, and 30-day trend chart in one view
* **Content Insights** — Discover which pages AI bots haven't found yet, with AI Discovery Scores
* **CSV Export** — Download your crawler logs any time
* **robots.txt Checker** — See which bots are allowed or blocked at a glance
* **Cache Setup Guide** — Auto-detects your caching plugin with exclusion instructions
* **Privacy First** — IP addresses anonymized, zero external API calls
* **Automatic Cleanup** — Daily cron prunes logs older than 90 days

= Supported AI Crawlers =

OpenAI (GPTBot, ChatGPT-User, OAI-SearchBot), Anthropic (ClaudeBot, Claude-Web, Claude-SearchBot), Google (Google-Extended, Gemini, Bard, CloudVertexBot), Meta (Meta-ExternalAgent, FacebookBot), Perplexity, Mistral, DeepSeek, xAI, Amazon (Amazonbot, NovaAct), Apple, ByteDance, Cohere, DuckDuckGo, HuggingFace, Devin, Diffbot, and 25+ more.

= How It Works =

1. Install and activate
2. Bot visits are automatically detected on every frontend page load
3. Check the AI Scout dashboard to see your activity timeline and bot leaderboard
4. Visit Settings to review robots.txt status and configure your cache

== Installation ==

1. Upload the `oblique-ai-scout` folder to `/wp-content/plugins/`
2. Activate through the 'Plugins' menu
3. Click 'AI Scout' in the admin sidebar

== Frequently Asked Questions ==

= Does this affect site performance? =

No. Detection runs a simple string match on the user agent header — only on frontend requests. No database queries on the frontend beyond the single insert.

= Where does my data go? =

Nowhere. All data stays in your WordPress database. No external APIs, no tracking pixels, no SaaS.

= How long is data retained? =

90 days by default, with daily automatic cleanup. Developers can filter `oblique_ai_scout_days_to_keep` to change this.

= Can I export the data? =

Yes. CSV export is available from the dashboard header.

= What are Content Insights? =

They identify published pages that AI bots have NOT visited. Each page gets an AI Discovery Score (0–100) based on content quality factors.

== Screenshots ==

1. Dashboard — Activity timeline, metric banner, and bot leaderboard
2. Content Insights — Undiscovered pages with circular score gauges
3. Settings — robots.txt status, data management, and cache exclusion setup

== Changelog ==

= 1.0.0 =
* Initial release
* 56+ AI bot detection (OpenAI, Anthropic, Google, Meta, Perplexity, Mistral, DeepSeek, xAI, Amazon, and more)
* Single-page dashboard with activity timeline, bot leaderboard, and 30-day trend chart
* Content Insights with AI Discovery Scores
* CSV export with memory-safe batching
* robots.txt live status checker
* IP tracking with GDPR-friendly anonymization
* Cache plugin auto-detection (WP Rocket, LiteSpeed, W3 Total Cache, WP Super Cache)
* Daily auto-cleanup cron with 90-day retention
* Privacy-first: zero external API calls

== Upgrade Notice ==

= 1.0.0 =
Initial release.

== License ==

Copyright (C) 2026 Oblique Code

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
