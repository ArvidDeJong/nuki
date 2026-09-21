---
title: "FAQ"
nav_order: 14
description: "Short answers about darvis/nuki: what it is, versions, the API token, several NUKI accounts, safety, webhooks, users per lock, demo mode and testing."
faq: true
---

# Frequently asked questions

{% for item in site.data.faq %}
## {{ item.q }}

{{ item.a | markdownify }}
{% endfor %}
